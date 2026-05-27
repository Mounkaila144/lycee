<?php

namespace Modules\Enrollment\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Services\BulletinService;

/**
 * Read-only aggregations for a single student, shared by the student portal
 * (own data) and the parent portal (child data). All queries run on the tenant
 * connection and are scoped to the given student.
 */
class StudentDataService
{
    public function __construct(private readonly BulletinService $bulletins) {}

    /**
     * Per-subject averages, general average and rank for the student's class.
     *
     * @return array<string, mixed>
     */
    public function grades(Student $student): array
    {
        if (! $student->class_id) {
            return ['subjects' => [], 'student' => null, 'class_average' => null];
        }

        $bulletin = $this->bulletins->buildForClass($student->class_id, null);
        $row = collect($bulletin['students'])->firstWhere('student_id', $student->id);

        return [
            'subjects' => $bulletin['subjects'],
            'student' => $row,
            'class_average' => $bulletin['stats']['class_average'] ?? null,
        ];
    }

    /**
     * Attendance records with a status summary.
     *
     * @return array<string, mixed>
     */
    public function attendance(Student $student): array
    {
        if (! Schema::connection('tenant')->hasTable('attendance_records')) {
            return ['records' => [], 'summary' => ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0]];
        }

        $records = DB::connection('tenant')
            ->table('attendance_records as ar')
            ->join('attendance_sessions as s', 'ar.attendance_session_id', '=', 's.id')
            ->leftJoin('subjects as sub', 's.subject_id', '=', 'sub.id')
            ->where('ar.student_id', $student->id)
            ->whereNull('ar.deleted_at')
            ->orderByDesc('s.session_date')
            ->get([
                'ar.id', 'ar.status', 'ar.delay_minutes', 'ar.notes',
                's.session_date', 's.start_time', 'sub.name as subject_name',
            ]);

        $summary = [
            'present' => $records->where('status', 'present')->count(),
            'absent' => $records->where('status', 'absent')->count(),
            'late' => $records->where('status', 'late')->count(),
            'excused' => $records->where('status', 'excused')->count(),
            'total' => $records->count(),
        ];

        return ['records' => $records->all(), 'summary' => $summary];
    }

    /**
     * Weekly timetable slots for the student's class.
     *
     * @return array<int, array<string, mixed>>
     */
    public function timetable(Student $student): array
    {
        if (! $student->class_id || ! Schema::connection('tenant')->hasTable('timetable_slots')) {
            return [];
        }

        return DB::connection('tenant')
            ->table('timetable_slots as t')
            ->leftJoin('subjects as sub', 't.subject_id', '=', 'sub.id')
            ->leftJoin('users as u', 't.teacher_id', '=', 'u.id')
            ->where('t.class_id', $student->class_id)
            ->whereNull('t.deleted_at')
            ->orderByRaw("FIELD(t.day_of_week,'Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi')")
            ->orderBy('t.start_time')
            ->get([
                't.id', 't.day_of_week', 't.start_time', 't.end_time', 't.room_name',
                'sub.name as subject_name',
                DB::raw("TRIM(CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.lastname,''))) as teacher_name"),
            ])
            ->map(fn ($s) => (array) $s)
            ->all();
    }

    /**
     * Invoices with the outstanding balance per invoice.
     *
     * @return array<string, mixed>
     */
    public function invoices(Student $student): array
    {
        if (! Schema::connection('tenant')->hasTable('invoices')) {
            return ['invoices' => [], 'summary' => ['total_due' => 0, 'total_paid' => 0, 'balance' => 0]];
        }

        $invoices = DB::connection('tenant')
            ->table('invoices')
            ->where('student_id', $student->id)
            ->whereNull('deleted_at')
            ->orderByDesc('invoice_date')
            ->get(['id', 'invoice_number', 'invoice_date', 'due_date', 'total_amount', 'paid_amount', 'status'])
            ->map(function ($inv) {
                $inv->balance = round((float) $inv->total_amount - (float) $inv->paid_amount, 2);

                return (array) $inv;
            });

        return [
            'invoices' => $invoices->all(),
            'summary' => [
                'total_due' => round($invoices->sum('total_amount'), 2),
                'total_paid' => round($invoices->sum('paid_amount'), 2),
                'balance' => round($invoices->sum('balance'), 2),
            ],
        ];
    }

    /**
     * Issued documents (certificates, cards, …).
     *
     * @return array<int, array<string, mixed>>
     */
    public function documents(Student $student): array
    {
        if (! Schema::connection('tenant')->hasTable('documents')) {
            return [];
        }

        return DB::connection('tenant')
            ->table('documents')
            ->where('student_id', $student->id)
            ->whereNull('deleted_at')
            ->orderByDesc('issue_date')
            ->get(['id', 'document_type', 'document_number', 'issue_date', 'verification_code', 'status'])
            ->map(fn ($d) => (array) $d)
            ->all();
    }

    /**
     * Synthetic KPIs for the portal home.
     *
     * @return array<string, mixed>
     */
    public function dashboard(Student $student): array
    {
        $grades = $this->grades($student);
        $attendance = $this->attendance($student);
        $invoices = $this->invoices($student);

        return [
            'general_average' => $grades['student']['general_average'] ?? null,
            'rank' => $grades['student']['rank'] ?? null,
            'absences_count' => $attendance['summary']['absent'] ?? 0,
            'late_count' => $attendance['summary']['late'] ?? 0,
            'pending_balance' => $invoices['summary']['balance'] ?? 0,
            'documents_count' => count($this->documents($student)),
        ];
    }
}
