<?php

namespace Modules\Documents\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Modules\Documents\Entities\Document;
use Modules\Enrollment\Entities\Student;
use Symfony\Component\HttpFoundation\Response;

/**
 * Consultation and download of issued official documents, shared by
 * staff (Administrator, Manager), students (own documents) and parents
 * (their children's documents). Ownership is enforced by DocumentPolicy.
 */
class DocumentController extends Controller
{
    /**
     * List the issued documents of a student.
     */
    public function studentDocuments(Request $request, Student $student): JsonResponse
    {
        $this->authorizeFor($request, 'viewStudentDocuments', [Document::class, $student]);

        $documents = Document::query()
            ->forStudent($student->id)
            ->issued()
            ->with('academicYear:id,name')
            ->latest('issue_date')
            ->latest('id')
            ->get();

        return response()->json([
            'data' => $documents->map(fn (Document $document) => self::summary($document))->values(),
            'meta' => ['student_id' => $student->id],
        ]);
    }

    /**
     * Stream the PDF of a document.
     */
    public function download(Request $request, Document $document): Response
    {
        $this->authorizeFor($request, 'download', $document);

        $disk = Storage::disk('tenant');

        if (! $document->pdf_path || ! $disk->exists($document->pdf_path)) {
            return response()->json([
                'message' => 'Le fichier PDF de ce document est introuvable.',
            ], 404);
        }

        return $disk->download(
            $document->pdf_path,
            $document->document_number.'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function summary(Document $document): array
    {
        return [
            'id' => $document->id,
            'student_id' => $document->student_id,
            'document_type' => $document->document_type,
            'document_number' => $document->document_number,
            'issue_date' => $document->issue_date?->toDateString(),
            'status' => $document->status,
            'academic_year' => $document->academicYear?->name,
            'verification_code' => $document->verification_code,
            'download_url' => "/admin/documents/files/{$document->id}/download",
        ];
    }

    private function authorizeFor(Request $request, string $ability, mixed $subject): void
    {
        Gate::forUser($request->user())->authorize($ability, $subject);
    }
}
