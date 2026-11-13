<?php

namespace Modules\NotesEvaluations\Http\Controllers\Lycee;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Http\Requests\Lycee\LyceeResultsQueryRequest;
use Modules\NotesEvaluations\Services\Lycee\LyceeGradeAccessService;
use Modules\NotesEvaluations\Services\Lycee\LyceeReportService;
use Modules\StructureAcademique\Entities\Classe;

/**
 * Notes lycée — résultats d'une classe et relevé d'un élève.
 */
class LyceeResultController extends Controller
{
    public function __construct(
        private LyceeReportService $reports,
        private LyceeGradeAccessService $access
    ) {}

    /**
     * GET /results/classes/{classe} (Admin|Manager ; Professeur PP ou affecté — ses matières sauf PP).
     */
    public function classResults(LyceeResultsQueryRequest $request, int $classe): JsonResponse
    {
        $user = $request->user();
        $classe = Classe::query()->with('headTeacher')->findOrFail($classe);

        if (! $this->access->canViewClassResults($user, $classe)) {
            abort(403, "Vous n'avez pas accès aux résultats de cette classe.");
        }

        $semester = $this->reports->resolveSemester($classe, $request->semesterId());

        if ($semester === null) {
            abort(422, "Aucun semestre n'est défini pour l'année scolaire de cette classe.");
        }

        $publishedOnly = ! $request->boolean('include_unpublished');

        return response()->json([
            'data' => $this->reports->classResults($classe, $semester, $publishedOnly, $this->access->visibleSubjectIds($user, $classe)),
            'meta' => [
                'published_only' => $publishedOnly,
                'semesters' => $this->reports->semestersFor($classe),
            ],
        ]);
    }

    /**
     * GET /results/students/{student} (Admin|Manager|prof concerné|Élève lui-même|Parent).
     * Élève/Parent : évaluations publiées uniquement.
     */
    public function studentResults(LyceeResultsQueryRequest $request, int $student): JsonResponse
    {
        $user = $request->user();
        $student = Student::query()->findOrFail($student);
        $classe = $this->reports->studentClasse($student, $request->semesterId());

        if (! $this->access->canViewStudentReport($user, $student, $classe)) {
            abort(403, "Vous n'avez pas accès aux notes de cet élève.");
        }

        $classe?->loadMissing('headTeacher');
        $semester = $this->reports->resolveSemester($classe, $request->semesterId());
        $isStaff = $this->access->isSupervisor($user) || ($classe !== null && $this->access->canViewClassResults($user, $classe));
        $publishedOnly = ! $isStaff || ! $request->boolean('include_unpublished');
        $visibleSubjectIds = $isStaff && $classe !== null ? $this->access->visibleSubjectIds($user, $classe) : null;

        return response()->json([
            'data' => $this->reports->studentReport($student, $classe, $semester, $publishedOnly, $visibleSubjectIds),
            'meta' => [
                'student_id' => $student->id,
                'published_only' => $publishedOnly,
                'semesters' => $this->reports->semestersFor($classe),
            ],
        ]);
    }
}
