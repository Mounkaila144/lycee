<?php

namespace Modules\Enrollment\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentClassEnrollment;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Level;

/**
 * Secondary-school re-enrollment: promote a whole class to the next level for
 * a new academic year, without re-typing student data. Keeps a per-year
 * enrollment history (student_class_enrollments) while updating each student's
 * current class (students.class_id).
 */
class ClassReenrollmentController extends Controller
{
    /**
     * Preview the re-enrollment of a source class into a target academic year:
     * the students and the proposed next-level target class.
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_id' => 'required|integer|exists:tenant.classes,id',
            'target_academic_year_id' => 'required|integer|exists:tenant.academic_years,id',
        ]);

        $source = Classe::on('tenant')->with(['level', 'series'])->findOrFail($validated['class_id']);
        $targetYearId = (int) $validated['target_academic_year_id'];

        $nextLevel = $this->nextLevel($source->level);
        $targetClass = $nextLevel ? $this->findTargetClass($targetYearId, $nextLevel->id, $source->series_id, $source->section) : null;

        $students = Student::on('tenant')
            ->where('class_id', $source->id)
            ->orderBy('lastname')->orderBy('firstname')
            ->get(['id', 'matricule', 'firstname', 'lastname', 'status']);

        $alreadyDone = StudentClassEnrollment::on('tenant')
            ->where('academic_year_id', $targetYearId)
            ->whereIn('student_id', $students->pluck('id'))
            ->pluck('student_id')->all();

        return response()->json([
            'data' => [
                'source_class' => ['id' => $source->id, 'name' => $source->name, 'level' => $source->level?->name],
                'next_level' => $nextLevel ? ['id' => $nextLevel->id, 'name' => $nextLevel->name] : null,
                'is_terminal' => $nextLevel === null,
                'suggested_target_class' => $targetClass ? ['id' => $targetClass->id, 'name' => $targetClass->name] : null,
                'students' => $students->map(fn ($s) => [
                    'student_id' => $s->id,
                    'matricule' => $s->matricule,
                    'name' => trim($s->lastname.' '.$s->firstname),
                    'status' => $s->status,
                    'already_reenrolled' => in_array($s->id, $alreadyDone, true),
                ])->values(),
            ],
        ]);
    }

    /**
     * Apply the re-enrollment decisions: create the target classes if needed,
     * write the enrollment history and move each student to its new class.
     */
    public function process(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_class_id' => 'required|integer|exists:tenant.classes,id',
            'target_academic_year_id' => 'required|integer|exists:tenant.academic_years,id',
            'decisions' => 'required|array|min:1',
            'decisions.*.student_id' => 'required|integer|exists:tenant.students,id',
            'decisions.*.action' => 'required|in:promote,repeat,exclude,graduate',
        ]);

        $source = Classe::on('tenant')->with('level')->findOrFail($validated['source_class_id']);
        $targetYearId = (int) $validated['target_academic_year_id'];
        $nextLevel = $this->nextLevel($source->level);
        $userId = auth('tenant')->id();

        $result = ['promoted' => 0, 'repeated' => 0, 'graduated' => 0, 'excluded' => 0];

        DB::connection('tenant')->transaction(function () use ($validated, $source, $targetYearId, $nextLevel, $userId, &$result) {
            foreach ($validated['decisions'] as $decision) {
                $action = $decision['action'];

                if ($action === 'exclude') {
                    $result['excluded']++;

                    continue;
                }

                if ($action === 'graduate' || ($action === 'promote' && $nextLevel === null)) {
                    Student::on('tenant')->where('id', $decision['student_id'])->update(['status' => 'Diplômé']);
                    $result['graduated']++;

                    continue;
                }

                $levelId = $action === 'repeat' ? $source->level_id : $nextLevel->id;
                $type = $action === 'repeat' ? 'repeat' : 'promotion';

                $targetClass = $this->findTargetClass($targetYearId, $levelId, $source->series_id, $source->section)
                    ?? Classe::on('tenant')->create([
                        'academic_year_id' => $targetYearId,
                        'level_id' => $levelId,
                        'series_id' => $source->series_id,
                        'section' => $source->section,
                        'max_capacity' => $source->max_capacity,
                    ]);

                StudentClassEnrollment::on('tenant')->updateOrCreate(
                    ['student_id' => $decision['student_id'], 'academic_year_id' => $targetYearId],
                    [
                        'class_id' => $targetClass->id,
                        'enrollment_type' => $type,
                        'enrolled_at' => now(),
                        'enrolled_by' => $userId,
                    ],
                );

                Student::on('tenant')->where('id', $decision['student_id'])
                    ->update(['class_id' => $targetClass->id, 'status' => 'Actif']);

                $student = Student::on('tenant')->find($decision['student_id']);
                app(\Modules\Finance\Services\BillingService::class)->generateClassInvoice($student, $targetClass);

                $result[$type === 'repeat' ? 'repeated' : 'promoted']++;
            }
        });

        return response()->json([
            'message' => 'Réinscription effectuée.',
            'data' => $result,
        ]);
    }

    /**
     * Re-enroll a SINGLE student (from the students list "Réinscrire" action):
     * promote to next level or repeat the same level, for a target year.
     */
    public function processStudent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|integer|exists:tenant.students,id',
            'target_academic_year_id' => 'required|integer|exists:tenant.academic_years,id',
            'action' => 'required|in:promote,repeat',
        ]);

        $student = Student::on('tenant')->with('classe.level')->findOrFail($validated['student_id']);
        $source = $student->classe;
        abort_if($source === null, 422, "L'élève n'est rattaché à aucune classe.");

        $targetYearId = (int) $validated['target_academic_year_id'];
        $nextLevel = $this->nextLevel($source->level);

        if ($validated['action'] === 'promote' && $nextLevel === null) {
            return response()->json(['message' => 'Niveau terminal : utilisez la réinscription par classe pour diplômer.'], 422);
        }

        $levelId = $validated['action'] === 'repeat' ? $source->level_id : $nextLevel->id;
        $type = $validated['action'] === 'repeat' ? 'repeat' : 'promotion';

        $targetClass = $this->findTargetClass($targetYearId, $levelId, $source->series_id, $source->section)
            ?? Classe::on('tenant')->create([
                'academic_year_id' => $targetYearId,
                'level_id' => $levelId,
                'series_id' => $source->series_id,
                'section' => $source->section,
                'max_capacity' => $source->max_capacity,
            ]);

        StudentClassEnrollment::on('tenant')->updateOrCreate(
            ['student_id' => $student->id, 'academic_year_id' => $targetYearId],
            ['class_id' => $targetClass->id, 'enrollment_type' => $type, 'enrolled_at' => now(), 'enrolled_by' => auth('tenant')->id()],
        );

        $student->update(['class_id' => $targetClass->id, 'status' => 'Actif']);

        $invoice = app(\Modules\Finance\Services\BillingService::class)
            ->generateClassInvoice($student->fresh(), $targetClass);

        return response()->json([
            'message' => 'Réinscription effectuée.',
            'data' => [
                'class_id' => $targetClass->id,
                'class_name' => $targetClass->name,
                'enrollment_type' => $type,
                'invoice_id' => $invoice?->id,
            ],
        ]);
    }

    /**
     * Per-year roster from the enrollment history.
     */
    public function roster(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'academic_year_id' => 'required|integer|exists:tenant.academic_years,id',
        ]);

        $rows = StudentClassEnrollment::on('tenant')
            ->with(['student:id,matricule,firstname,lastname', 'classe:id,name'])
            ->where('academic_year_id', $validated['academic_year_id'])
            ->get()
            ->map(fn (StudentClassEnrollment $e) => [
                'id' => $e->id,
                'student' => $e->student ? [
                    'id' => $e->student->id,
                    'matricule' => $e->student->matricule,
                    'name' => trim($e->student->lastname.' '.$e->student->firstname),
                ] : null,
                'class' => $e->classe?->name,
                'enrollment_type' => $e->enrollment_type,
                'enrolled_at' => optional($e->enrolled_at)->format('Y-m-d'),
            ]);

        return response()->json(['data' => $rows]);
    }

    /**
     * Initialize the history for an academic year from the students' current
     * class (students.class_id) — one-time bootstrap for existing data.
     */
    public function backfill(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'academic_year_id' => 'required|integer|exists:tenant.academic_years,id',
        ]);

        $yearId = (int) $validated['academic_year_id'];
        $userId = auth('tenant')->id();
        $created = 0;

        Student::on('tenant')->whereNotNull('class_id')->with('classe')->chunk(200, function ($students) use ($yearId, $userId, &$created) {
            foreach ($students as $student) {
                if (! $student->classe || (int) $student->classe->academic_year_id !== $yearId) {
                    continue;
                }

                $enrollment = StudentClassEnrollment::on('tenant')->firstOrCreate(
                    ['student_id' => $student->id, 'academic_year_id' => $yearId],
                    ['class_id' => $student->class_id, 'enrollment_type' => 'initial', 'enrolled_at' => now(), 'enrolled_by' => $userId],
                );

                if ($enrollment->wasRecentlyCreated) {
                    $created++;
                }
            }
        });

        return response()->json(['message' => "Historique initialisé ({$created} inscription(s)).", 'data' => ['created' => $created]]);
    }

    /**
     * The level immediately above the given one (by order_index), or null if
     * it is the last level (Terminale).
     */
    private function nextLevel(?Level $level): ?Level
    {
        if (! $level) {
            return null;
        }

        return Level::on('tenant')->where('order_index', '>', $level->order_index)
            ->orderBy('order_index')->first();
    }

    private function findTargetClass(int $yearId, int $levelId, ?int $seriesId, ?string $section): ?Classe
    {
        return Classe::on('tenant')
            ->where('academic_year_id', $yearId)
            ->where('level_id', $levelId)
            ->where('series_id', $seriesId)
            ->where('section', $section)
            ->first();
    }
}
