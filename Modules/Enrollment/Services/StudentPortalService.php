<?php

namespace Modules\Enrollment\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\Attendance\Entities\AttendanceRecord;
use Modules\Documents\Entities\Document;
use Modules\Documents\Entities\StudentCard;
use Modules\Enrollment\Entities\ClassEnrollment;
use Modules\Enrollment\Entities\ReenrollmentCampaign;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Http\Resources\ReenrollmentCampaignResource;
use Modules\Finance\Entities\Invoice;
use Modules\Finance\Entities\Payment;
use Modules\NotesEvaluations\Entities\Grade;
use Modules\NotesEvaluations\Services\Lycee\LyceeReportService;
use Modules\Timetable\Services\TimetableViewService;

/**
 * Agrégations en lecture seule du portail Élève.
 *
 * Toutes les requêtes sont filtrées sur l'élève passé en paramètre, qui doit
 * provenir de l'utilisateur authentifié (jamais d'un paramètre de requête).
 */
class StudentPortalService
{
    public function __construct(
        private TimetableViewService $timetableViewService,
        private LyceeReportService $lyceeReports
    ) {}

    public const ATTENDANCE_STATUS_LABELS = [
        'present' => 'Présent',
        'absent' => 'Absent',
        'late' => 'Retard',
        'excused' => 'Excusé',
    ];

    private const UNPAID_INVOICE_STATUSES = ['pending', 'partial', 'overdue'];

    private const DAY_NUMBERS = [
        'Lundi' => 1,
        'Mardi' => 2,
        'Mercredi' => 3,
        'Jeudi' => 4,
        'Vendredi' => 5,
        'Samedi' => 6,
        'Dimanche' => 7,
    ];

    /**
     * Inscription en classe active de l'élève (année scolaire active).
     */
    public function currentClassEnrollment(Student $student): ?ClassEnrollment
    {
        return $student->currentClassEnrollment()->with(['classe', 'academicYear'])->first();
    }

    /**
     * @return array{id: int, name: string|null, academic_year: string|null}|null
     */
    public function currentClassSummary(Student $student): ?array
    {
        $enrollment = $this->currentClassEnrollment($student);

        if (! $enrollment || ! $enrollment->classe) {
            return null;
        }

        return [
            'id' => $enrollment->classe->id,
            'name' => $enrollment->classe->name,
            'academic_year' => $enrollment->academicYear?->name,
        ];
    }

    /**
     * Relevé de notes lycée (évaluations publiées uniquement) : même forme que
     * GET /api/admin/lycee-grades/results/students/{student}. Semestre actif par défaut.
     *
     * @return array{report: array<string, mixed>, semesters: list<array<string, mixed>>}
     */
    public function lyceeGradesReport(Student $student, ?int $semesterId = null): array
    {
        $classe = $this->lyceeReports->studentClasse($student, $semesterId);
        $classe?->loadMissing('headTeacher');
        $semester = $this->lyceeReports->resolveSemester($classe, $semesterId);

        return [
            'report' => $this->lyceeReports->studentReport($student, $classe, $semester, true),
            'semesters' => $this->lyceeReports->semestersFor($classe),
        ];
    }

    /**
     * Moyenne générale (évaluations publiées) du semestre actif — dashboard Élève.
     */
    public function currentGeneralAverage(Student $student): ?float
    {
        return $this->lyceeReports->currentGeneralAverage($student);
    }

    /**
     * Notes publiées et visibles par l'élève (ancien modèle universitaire, conservé pour compatibilité).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function grades(Student $student): Collection
    {
        return Grade::query()
            ->where('student_id', $student->id)
            ->published()
            ->visibleToStudents()
            ->with(['evaluation.module', 'evaluation.semester'])
            ->orderByDesc('published_at')
            ->get()
            ->map(fn (Grade $grade): array => [
                'id' => $grade->id,
                'subject' => $grade->evaluation?->module?->name ?? $grade->evaluation?->name,
                'evaluation' => $grade->evaluation?->name,
                'type' => $grade->evaluation?->type,
                'score' => $grade->is_absent || $grade->score === null ? null : (float) $grade->score,
                'max_score' => $grade->evaluation?->max_score !== null ? (float) $grade->evaluation->max_score : 20.0,
                'coefficient' => $grade->evaluation?->coefficient !== null ? (float) $grade->evaluation->coefficient : 1.0,
                'is_absent' => (bool) $grade->is_absent,
                'comment' => $grade->comment,
                'semester' => $grade->evaluation?->semester?->name,
                'evaluation_date' => $grade->evaluation?->planned_date?->format('Y-m-d')
                    ?? $grade->published_at?->format('Y-m-d'),
            ])
            ->values();
    }

    /**
     * Moyenne pondérée ramenée sur 20 (null si aucune note chiffrée).
     *
     * @param  Collection<int, array<string, mixed>>  $grades
     */
    public function weightedAverage(Collection $grades): ?float
    {
        $scored = $grades->filter(fn (array $grade): bool => $grade['score'] !== null && $grade['max_score'] > 0);
        $totalCoefficient = $scored->sum('coefficient');

        if ($scored->isEmpty() || $totalCoefficient <= 0) {
            return null;
        }

        $weightedSum = $scored->sum(fn (array $grade): float => ($grade['score'] / $grade['max_score'] * 20) * $grade['coefficient']);

        return round($weightedSum / $totalCoefficient, 2);
    }

    /**
     * Pointages de présence de l'élève (du plus récent au plus ancien).
     *
     * attendance_records.student_id référence users.id (compte de l'élève), pas students.id.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function attendance(Student $student): Collection
    {
        if (! $student->user_id) {
            return collect();
        }

        return AttendanceRecord::query()
            ->where('student_id', $student->user_id)
            ->with(['session.subject', 'session.classe'])
            ->latest('id')
            ->get()
            ->map(fn (AttendanceRecord $record): array => [
                'id' => $record->id,
                'date' => $record->session?->session_date?->format('Y-m-d')
                    ?? $record->created_at?->format('Y-m-d'),
                'start_time' => $record->session?->start_time,
                'end_time' => $record->session?->end_time,
                'subject' => $record->session?->subject?->name,
                'status' => $record->status,
                'status_label' => self::ATTENDANCE_STATUS_LABELS[$record->status] ?? $record->status,
                'delay_minutes' => $record->delay_minutes,
                'notes' => $record->notes,
            ])
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $records
     * @return array{total: int, present: int, absent: int, late: int, excused: int}
     */
    public function attendanceSummary(Collection $records): array
    {
        return [
            'total' => $records->count(),
            'present' => $records->where('status', 'present')->count(),
            'absent' => $records->where('status', 'absent')->count(),
            'late' => $records->where('status', 'late')->count(),
            'excused' => $records->where('status', 'excused')->count(),
        ];
    }

    /**
     * Nombre d'absences sur les N derniers jours.
     */
    public function recentAbsencesCount(Student $student, int $days = 30): int
    {
        if (! $student->user_id) {
            return 0;
        }

        return AttendanceRecord::query()
            ->where('student_id', $student->user_id)
            ->where('status', 'absent')
            ->whereHas('session', fn ($query) => $query->where('session_date', '>=', now()->subDays($days)->toDateString()))
            ->count();
    }

    /**
     * Factures de l'élève avec lignes et paiements.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function invoices(Student $student): Collection
    {
        return Invoice::query()
            ->where('student_id', $student->id)
            ->where('status', '!=', 'draft')
            ->with(['items.feeType', 'payments', 'academicYear'])
            ->orderByDesc('invoice_date')
            ->get()
            ->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'academic_year' => $invoice->academicYear?->name,
                'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
                'due_date' => $invoice->due_date?->format('Y-m-d'),
                'total_amount' => (float) $invoice->total_amount,
                'paid_amount' => (float) $invoice->paid_amount,
                'balance' => max(0, (float) $invoice->total_amount - (float) $invoice->paid_amount),
                'status' => $invoice->status,
                'items' => $invoice->items->map(fn ($item): array => [
                    'description' => $item->description ?? $item->feeType?->name,
                    'fee_type' => $item->feeType?->name,
                    'amount' => (float) $item->amount,
                    'discount_amount' => (float) $item->discount_amount,
                ])->values(),
                'payments' => $invoice->payments->map(fn (Payment $payment): array => [
                    'id' => $payment->id,
                    'payment_date' => $payment->payment_date?->format('Y-m-d'),
                    'amount' => (float) $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'receipt_number' => $payment->receipt_number,
                ])->values(),
            ])
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $invoices
     * @return array{total_amount: float, total_paid: float, total_balance: float, currency: string}
     */
    public function invoiceTotals(Collection $invoices): array
    {
        $billable = $invoices->where('status', '!=', 'cancelled');

        return [
            'total_amount' => (float) $billable->sum('total_amount'),
            'total_paid' => (float) $billable->sum('paid_amount'),
            'total_balance' => (float) $billable->sum('balance'),
            'currency' => (string) config('services.cinetpay.currency', 'XOF'),
        ];
    }

    public function pendingInvoicesCount(Student $student): int
    {
        return Invoice::query()
            ->where('student_id', $student->id)
            ->whereIn('status', self::UNPAID_INVOICE_STATUSES)
            ->count();
    }

    /**
     * Documents officiels délivrés + pièces du dossier de l'élève.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function documents(Student $student): Collection
    {
        $official = Document::query()
            ->forStudent($student->id)
            ->issued()
            ->orderByDesc('issue_date')
            ->get()
            ->map(fn (Document $document): array => [
                'id' => $document->id,
                'source' => 'official',
                'type' => $document->document_type,
                'title' => $document->document_number
                    ? "{$document->document_type} — {$document->document_number}"
                    : $document->document_type,
                'document_number' => $document->document_number,
                'date' => $document->issue_date?->format('Y-m-d'),
                'is_validated' => true,
                'download_url' => $document->pdf_url,
            ]);

        $dossier = $student->documents()
            ->latest('id')
            ->get()
            ->map(fn ($document): array => [
                'id' => $document->id,
                'source' => 'dossier',
                'type' => $document->type,
                'title' => $document->original_filename ?? $document->type,
                'document_number' => null,
                'date' => $document->created_at?->format('Y-m-d'),
                'is_validated' => (bool) $document->is_validated,
                'download_url' => null,
            ]);

        return $official->concat($dossier)->values();
    }

    public function availableDocumentsCount(Student $student): int
    {
        return Document::query()->forStudent($student->id)->issued()->count();
    }

    /**
     * Créneaux de l'emploi du temps de la classe actuelle de l'élève (semestre actif),
     * délégués au module Timetable (même format que le portail Parent).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function timetable(Student $student): Collection
    {
        $timetable = $this->timetableViewService->forStudent($student);

        return collect($this->timetableViewService->toPortalList($timetable['slots']));
    }

    /**
     * Prochain cours à venir dans la semaine (à partir de $now).
     *
     * @param  Collection<int, array<string, mixed>>  $slots
     * @return array<string, mixed>|null
     */
    public function nextClass(Collection $slots, CarbonInterface $now): ?array
    {
        if ($slots->isEmpty()) {
            return null;
        }

        $nowKey = $now->dayOfWeekIso.' '.$now->format('H:i');

        $ordered = $slots->map(fn (array $slot): array => $slot + [
            'sort_key' => (self::DAY_NUMBERS[$slot['day_of_week']] ?? 9).' '.$slot['start_time'],
        ])->sortBy('sort_key')->values();

        $next = $ordered->first(fn (array $slot): bool => $slot['sort_key'] > $nowKey) ?? $ordered->first();

        unset($next['sort_key']);

        return $next;
    }

    /**
     * Dernière carte scolaire de l'élève.
     *
     * @return array<string, mixed>
     */
    public function card(Student $student): array
    {
        $card = StudentCard::query()
            ->where('student_id', $student->id)
            ->latest('id')
            ->first();

        return [
            'student_id' => $student->id,
            'matricule' => $student->matricule,
            'full_name' => $student->full_name,
            'classe' => $this->currentClassSummary($student),
            'card_status' => $card?->status ?? 'not_issued',
            'card_number' => $card?->card_number,
            'issued_at' => $this->formatDate($card?->getAttribute('issued_at')),
            'valid_until' => $this->formatDate($card?->getAttribute('valid_until')),
            'pdf_url' => null,
        ];
    }

    /**
     * Campagnes de réinscription ouvertes.
     *
     * @return array<string, mixed>
     */
    public function reenrollment(Student $student): array
    {
        $campaigns = ReenrollmentCampaign::query()
            ->openForRegistration()
            ->with(['fromAcademicYear', 'toAcademicYear'])
            ->get();

        return [
            'student_id' => $student->id,
            'open_campaigns' => ReenrollmentCampaignResource::collection($campaigns)->resolve(),
            'eligible' => $campaigns->isNotEmpty(),
        ];
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof CarbonInterface ? $value->format('Y-m-d') : substr((string) $value, 0, 10);
    }
}
