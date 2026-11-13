<?php

namespace Modules\NotesEvaluations\Http\Controllers\Lycee;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Entities\ReportCardComment;
use Modules\NotesEvaluations\Http\Requests\Lycee\LyceeResultsQueryRequest;
use Modules\NotesEvaluations\Http\Requests\Lycee\SaveReportCardCommentRequest;
use Modules\NotesEvaluations\Services\Lycee\LyceeGradeAccessService;
use Modules\NotesEvaluations\Services\Lycee\LyceeReportService;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Semester;

/**
 * Notes lycée — bulletins PDF (élève, classe) et appréciations.
 */
class ReportCardController extends Controller
{
    public function __construct(
        private LyceeReportService $reports,
        private LyceeGradeAccessService $access
    ) {}

    /**
     * GET /report-cards/students/{student}/pdf
     * Admin|Manager|PP : bulletin complet ; Élève/Parent : publié uniquement, 403 sans évaluation publiée.
     */
    public function studentPdf(LyceeResultsQueryRequest $request, int $student): Response
    {
        $user = $request->user();
        $student = Student::query()->findOrFail($student);
        $classe = $this->reports->studentClasse($student, $request->semesterId());

        $isStaff = $this->access->isSupervisor($user)
            || ($classe !== null && $this->access->canViewFullClass($user, $classe));
        $isFamily = ! $isStaff && $this->access->isFamilyOf($user, $student);

        if (! $isStaff && ! $isFamily) {
            abort(403, "Vous n'avez pas accès au bulletin de cet élève.");
        }

        $semester = $this->reports->resolveSemester($classe, $request->semesterId());

        if ($classe === null || $semester === null) {
            abort(422, "Aucune classe ou aucun semestre n'est associé à cet élève.");
        }

        if ($isFamily && ! $this->reports->hasPublishedEvaluations($classe, $semester)) {
            abort(403, "Le bulletin de ce semestre n'est pas encore disponible.");
        }

        $classe->loadMissing(['headTeacher', 'academicYear']);
        $publishedOnly = ! $isStaff || ! $request->boolean('include_unpublished');
        $report = $this->reports->studentReport($student, $classe, $semester, $publishedOnly);

        return $this->renderPdf([$report], $classe, $semester, $publishedOnly, 'bulletin_'.Str::slug($student->full_name).'_'.$semester->name.'.pdf');
    }

    /**
     * GET /report-cards/classes/{classe}/pdf (Admin|Manager|PP) — une page par élève.
     */
    public function classPdf(LyceeResultsQueryRequest $request, int $classe): Response
    {
        $user = $request->user();
        $classe = Classe::query()->with(['headTeacher', 'academicYear'])->findOrFail($classe);

        if (! $this->access->canViewFullClass($user, $classe)) {
            abort(403, 'Seuls l\'administration et le professeur principal peuvent éditer les bulletins de la classe.');
        }

        $semester = $this->reports->resolveSemester($classe, $request->semesterId());

        if ($semester === null) {
            abort(422, "Aucun semestre n'est défini pour l'année scolaire de cette classe.");
        }

        $publishedOnly = ! $request->boolean('include_unpublished');
        $reports = $this->reports->classReports($classe, $semester, $publishedOnly);

        if ($reports === []) {
            abort(422, 'Aucun élève actif dans cette classe.');
        }

        return $this->renderPdf($reports, $classe, $semester, $publishedOnly, 'bulletins_'.Str::slug($classe->name).'_'.$semester->name.'.pdf');
    }

    /**
     * PUT /report-cards/comments — appréciation matière (prof affecté) ou générale (PP, Admin/Manager).
     * Un commentaire vide supprime l'appréciation.
     */
    public function saveComment(SaveReportCardCommentRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $student = Student::query()->findOrFail($validated['student_id']);
        $semester = Semester::query()->findOrFail($validated['semester_id']);
        $subjectId = isset($validated['subject_id']) ? (int) $validated['subject_id'] : null;
        $classe = $this->reports->studentClasse($student, $semester->id);

        if ($classe === null) {
            abort(422, "L'élève n'est inscrit dans aucune classe pour ce semestre.");
        }

        if (! $this->access->canComment($user, $classe, $subjectId)) {
            abort(403, $subjectId === null
                ? "Seul le professeur principal ou l'administration peut saisir l'appréciation générale."
                : "Vous n'êtes pas affecté à cette matière dans la classe de l'élève.");
        }

        $existing = ReportCardComment::query()
            ->where('student_id', $student->id)
            ->where('semester_id', $semester->id)
            ->when($subjectId === null, fn ($query) => $query->whereNull('subject_id'), fn ($query) => $query->where('subject_id', $subjectId))
            ->first();

        $text = trim((string) ($validated['comment'] ?? ''));

        if ($text === '') {
            $existing?->delete();

            return response()->json([
                'data' => null,
                'message' => 'Appréciation supprimée.',
            ]);
        }

        $comment = $existing ?? new ReportCardComment([
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'subject_id' => $subjectId,
        ]);
        $comment->fill(['comment' => $text, 'author_id' => $user->getAuthIdentifier()])->save();

        return response()->json([
            'data' => [
                'id' => $comment->id,
                'student_id' => $comment->student_id,
                'semester_id' => $comment->semester_id,
                'subject_id' => $comment->subject_id,
                'comment' => $comment->comment,
                'author_id' => $comment->author_id,
                'updated_at' => $comment->updated_at?->toIso8601String(),
            ],
            'message' => 'Appréciation enregistrée.',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $reports
     */
    private function renderPdf(array $reports, Classe $classe, Semester $semester, bool $publishedOnly, string $fileName): Response
    {
        $pdf = Pdf::loadView('notesevaluations::lycee.report-card', [
            'reports' => $reports,
            'classe' => $classe,
            'semester' => $semester,
            'academicYear' => $classe->academicYear,
            'establishment' => $this->reports->establishment(),
            'isProvisional' => ! $publishedOnly,
            'generatedAt' => now(),
        ]);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->download($fileName);
    }
}
