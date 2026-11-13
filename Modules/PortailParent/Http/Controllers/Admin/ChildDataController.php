<?php

namespace Modules\PortailParent\Http\Controllers\Admin;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Attendance\Entities\AttendanceRecord;
use Modules\Documents\Entities\Document;
use Modules\Enrollment\Entities\Student;
use Modules\Finance\Entities\Invoice;
use Modules\Finance\Entities\Payment;
use Modules\NotesEvaluations\Entities\Grade;
use Modules\Timetable\Services\TimetableViewService;

/**
 * Données rattachées à un enfant via le portail Parent.
 *
 *  - Story Parent 02 : GET /api/admin/parent/children/{studentId}/grades
 *  - Story Parent 03 : GET /api/admin/parent/children/{studentId}/attendance
 *  - Story Parent 04 : GET /api/admin/parent/children/{studentId}/timetable
 *  - Story Parent 05 : GET /api/admin/parent/children/{studentId}/invoices
 *  - Story Parent 09 : GET /api/admin/parent/children/{studentId}/documents
 *
 * Chaque endpoint vérifie l'ownership via ChildPolicy (registered in
 * PortailParentServiceProvider). L'élève est résolu dans le contrôleur
 * (et non par route model binding) car le binding implicite s'exécute avant
 * l'initialisation du tenant.
 */
class ChildDataController extends Controller
{
    private const ATTENDANCE_STATUS_LABELS = [
        'present' => 'Présent',
        'absent' => 'Absent',
        'late' => 'Retard',
        'excused' => 'Excusé',
    ];

    /**
     * Story Parent 02 — Notes publiées de l'enfant.
     */
    public function grades(Request $request, int $studentId): JsonResponse
    {
        $student = $this->resolveChild($request, 'viewGrades', $studentId);

        $grades = Grade::query()
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
                'score' => $grade->is_absent ? null : ($grade->score !== null ? (float) $grade->score : null),
                'max_score' => $grade->evaluation?->max_score !== null ? (float) $grade->evaluation->max_score : 20.0,
                'is_absent' => (bool) $grade->is_absent,
                'comment' => $grade->comment,
                'semester' => $grade->evaluation?->semester?->name,
                'evaluation_date' => $grade->evaluation?->planned_date?->format('Y-m-d')
                    ?? $grade->published_at?->format('Y-m-d'),
            ])
            ->values();

        return response()->json([
            'data' => $grades,
            'meta' => ['student_id' => $student->id],
        ]);
    }

    /**
     * Story Parent 03 — Présences/absences de l'enfant.
     *
     * Attention : attendance_records.student_id référence users.id (compte de l'élève),
     * pas students.id.
     */
    public function attendance(Request $request, int $studentId): JsonResponse
    {
        $student = $this->resolveChild($request, 'viewAttendance', $studentId);

        $records = collect();

        if ($student->user_id !== null) {
            $records = AttendanceRecord::query()
                ->where('student_id', $student->user_id)
                ->with(['session.subject'])
                ->latest('id')
                ->get();
        }

        $records = $records
            ->map(fn (AttendanceRecord $record): array => [
                'id' => $record->id,
                'date' => $record->session?->session_date?->format('Y-m-d')
                    ?? $record->created_at?->format('Y-m-d'),
                'subject' => $record->session?->subject?->name,
                'start_time' => $record->session?->start_time,
                'end_time' => $record->session?->end_time,
                'status' => self::ATTENDANCE_STATUS_LABELS[$record->status] ?? $record->status,
                'delay_minutes' => $record->delay_minutes,
                'notes' => $record->notes,
            ])
            ->values();

        return response()->json([
            'data' => $records,
            'meta' => [
                'student_id' => $student->id,
                'absences_count' => $records->where('status', 'Absent')->count(),
                'lates_count' => $records->where('status', 'Retard')->count(),
            ],
        ]);
    }

    /**
     * Story Parent 05 — Factures de l'enfant (avec paiements reçus).
     */
    public function invoices(Request $request, int $studentId): JsonResponse
    {
        $student = $this->resolveChild($request, 'viewInvoices', $studentId);
        $currency = (string) config('services.cinetpay.currency', 'XOF');

        $invoices = Invoice::query()
            ->where('student_id', $student->id)
            ->where('status', '!=', 'draft')
            ->with(['items', 'payments'])
            ->orderByDesc('invoice_date')
            ->get()
            ->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'description' => $invoice->items->pluck('description')->filter()->implode(', ') ?: $invoice->notes,
                'amount' => (float) $invoice->total_amount,
                'paid_amount' => (float) $invoice->paid_amount,
                'balance' => max(0, (float) $invoice->total_amount - (float) $invoice->paid_amount),
                'currency' => $currency,
                'status' => $invoice->status,
                'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
                'due_date' => $invoice->due_date?->format('Y-m-d'),
                'payments' => $invoice->payments->map(fn (Payment $payment): array => [
                    'id' => $payment->id,
                    'payment_date' => $payment->payment_date?->format('Y-m-d'),
                    'amount' => (float) $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'receipt_number' => $payment->receipt_number,
                ])->values(),
            ])
            ->values();

        return response()->json([
            'data' => $invoices,
            'meta' => [
                'student_id' => $student->id,
                'total_amount' => $invoices->sum('amount'),
                'total_paid' => $invoices->sum('paid_amount'),
                'total_balance' => $invoices->whereNotIn('status', ['cancelled'])->sum('balance'),
                'currency' => $currency,
            ],
        ]);
    }

    /**
     * Story Parent 04 — Emploi du temps de la classe active de l'enfant.
     */
    public function timetable(Request $request, int $studentId, TimetableViewService $timetableViewService): JsonResponse
    {
        $student = $this->resolveChild($request, 'viewTimetable', $studentId);

        $timetable = $timetableViewService->forStudent($student);

        return response()->json([
            'data' => $timetableViewService->toPortalList($timetable['slots']),
            'meta' => [
                'student_id' => $student->id,
                'classe' => $timetable['classe'],
                'semester_id' => $timetable['semester_id'],
            ],
        ]);
    }

    /**
     * Story Parent 09 — Documents officiels délivrés à l'enfant.
     */
    public function documents(Request $request, int $studentId): JsonResponse
    {
        $student = $this->resolveChild($request, 'viewDocuments', $studentId);

        $documents = Document::query()
            ->forStudent($student->id)
            ->issued()
            ->orderByDesc('issue_date')
            ->get()
            ->map(fn (Document $document): array => [
                'id' => $document->id,
                'type' => $document->document_type,
                'title' => $document->document_number
                    ? "{$document->document_type} — {$document->document_number}"
                    : $document->document_type,
                'document_number' => $document->document_number,
                'download_url' => $document->pdf_url,
                'generated_at' => $document->issue_date?->format('Y-m-d'),
            ])
            ->values();

        return response()->json([
            'data' => $documents,
            'meta' => ['student_id' => $student->id],
        ]);
    }

    /**
     * Story Parent 08 — Annonces générales de l'école (non liées à un enfant spécifique).
     *
     * Permission requise : `view announcements` (sans ChildPolicy car non-ownership).
     */
    public function announcements(Request $request): JsonResponse
    {
        if (! $request->user()->hasPermissionTo('view announcements')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        return response()->json([
            'data' => [],
            'meta' => [
                'note' => 'Scaffold — annonces générales à câbler avec un module Communication (V2).',
            ],
        ]);
    }

    /**
     * Résout l'élève puis vérifie l'ownership via Gate::forUser()
     * (TenantSanctumAuth ne pose pas Auth::user()).
     */
    private function resolveChild(Request $request, string $ability, int $studentId): Student
    {
        $student = Student::query()->findOrFail($studentId);

        if (! Gate::forUser($request->user())->allows($ability, $student)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        return $student;
    }
}
