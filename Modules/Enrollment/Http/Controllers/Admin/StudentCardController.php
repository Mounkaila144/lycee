<?php

namespace Modules\Enrollment\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentCard;
use Modules\Enrollment\Http\Requests\BatchGenerateCardsRequest;
use Modules\Enrollment\Http\Requests\GenerateCardRequest;
use Modules\Enrollment\Http\Requests\UpdateCardStatusRequest;
use Modules\Enrollment\Http\Requests\VerifyCardRequest;
use Modules\Enrollment\Http\Resources\CardVerificationResource;
use Modules\Enrollment\Http\Resources\StudentCardResource;
use Modules\Enrollment\Services\StudentCardGeneratorService;
use Modules\StructureAcademique\Entities\AcademicYear;

class StudentCardController extends Controller
{
    public function __construct(
        private StudentCardGeneratorService $cardService
    ) {}

    /**
     * List student cards
     */
    public function index(Request $request): JsonResponse
    {
        $cards = StudentCard::query()
            ->when($request->academic_year_id, fn ($q, $yearId) => $q->where('academic_year_id', $yearId)
            )
            ->when($request->status, fn ($q, $status) => $q->where('status', $status)
            )
            ->when($request->print_status, fn ($q, $printStatus) => $q->where('print_status', $printStatus)
            )
            ->when($request->is_duplicate !== null, fn ($q) => $q->where('is_duplicate', $request->boolean('is_duplicate'))
            )
            ->when($request->search, fn ($q, $search) => $q->where(function ($query) use ($search) {
                $query->where('card_number', 'like', "%{$search}%")
                    ->orWhereHas('student', fn ($sq) => $sq->where('matricule', 'like', "%{$search}%")
                        ->orWhere('firstname', 'like', "%{$search}%")
                        ->orWhere('lastname', 'like', "%{$search}%")
                    );
            })
            )
            ->with(['student', 'academicYear'])
            ->latest()
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => StudentCardResource::collection($cards),
            'meta' => [
                'current_page' => $cards->currentPage(),
                'last_page' => $cards->lastPage(),
                'per_page' => $cards->perPage(),
                'total' => $cards->total(),
            ],
        ]);
    }

    /**
     * Generate card for a student
     */
    public function generate(GenerateCardRequest $request, int $studentId): JsonResponse
    {
        $student = Student::findOrFail($studentId);
        $year = AcademicYear::findOrFail($request->validated('academic_year_id'));

        try {
            $card = $this->cardService->generate($student, $year);

            return response()->json([
                'message' => 'Carte étudiant générée avec succès.',
                'data' => new StudentCardResource($card->load(['student', 'academicYear'])),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la génération de la carte.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Batch generate cards
     */
    public function batchGenerate(BatchGenerateCardsRequest $request): JsonResponse
    {
        $year = AcademicYear::findOrFail($request->validated('academic_year_id'));

        $results = $this->cardService->batchGenerate(
            $request->validated('student_ids'),
            $year
        );

        $generatedCount = count($results['generated']);
        $skippedCount = count($results['skipped']);
        $failedCount = count($results['failed']);

        return response()->json([
            'message' => "{$generatedCount} carte(s) générée(s), {$skippedCount} ignorée(s), {$failedCount} échec(s).",
            'data' => $results,
        ]);
    }

    /**
     * Show card details
     */
    public function show(int $id): JsonResponse
    {
        $card = StudentCard::with(['student', 'academicYear', 'originalCard'])
            ->findOrFail($id);

        return response()->json([
            'data' => new StudentCardResource($card),
        ]);
    }

    /**
     * Generate duplicate card
     */
    public function duplicate(int $id): JsonResponse
    {
        $card = StudentCard::findOrFail($id);

        try {
            $duplicate = $this->cardService->generateDuplicate(
                $card->student,
                $card->academicYear
            );

            return response()->json([
                'message' => 'Duplicata de carte généré avec succès.',
                'data' => new StudentCardResource($duplicate->load(['student', 'academicYear'])),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la génération du duplicata.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update card status
     */
    public function updateStatus(UpdateCardStatusRequest $request, int $id): JsonResponse
    {
        $card = StudentCard::findOrFail($id);

        try {
            $updated = $this->cardService->updateStatus($card, $request->validated('status'));

            return response()->json([
                'message' => 'Statut de la carte mis à jour.',
                'data' => new StudentCardResource($updated),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour du statut.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update print status
     */
    public function updatePrintStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'print_status' => ['required', 'in:'.implode(',', StudentCard::PRINT_STATUSES)],
        ]);

        $card = StudentCard::findOrFail($id);

        try {
            $updated = $this->cardService->updatePrintStatus($card, $request->print_status);

            return response()->json([
                'message' => 'Statut d\'impression mis à jour.',
                'data' => new StudentCardResource($updated),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour du statut d\'impression.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Verify card via QR code
     */
    public function verify(VerifyCardRequest $request): JsonResponse
    {
        try {
            $result = $this->cardService->verifyCard(
                $request->validated('qr_data'),
                $request->validated('signature')
            );

            return response()->json([
                'message' => 'Carte vérifiée avec succès.',
                'data' => new CardVerificationResource($result),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'valid' => false,
                'message' => 'Carte invalide.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Download card PDF
     */
    public function download(int $id)
    {
        $card = StudentCard::with('student')->findOrFail($id);

        if (! $card->pdf_path) {
            return response()->json([
                'message' => 'Le PDF de la carte n\'est pas encore généré.',
            ], 404);
        }

        $disk = Storage::disk('tenant');

        if (! $disk->exists($card->pdf_path)) {
            return response()->json([
                'message' => 'Le fichier PDF n\'existe pas.',
            ], 404);
        }

        $fileName = "carte_{$card->student->matricule}.pdf";

        return response()->download($disk->path($card->pdf_path), $fileName);
    }

    /**
     * Get card statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $request->validate([
            'academic_year_id' => ['required', 'exists:tenant.academic_years,id'],
        ]);

        $stats = $this->cardService->getStatistics($request->academic_year_id);

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Delete a student card
     */
    public function destroy(int $id): JsonResponse
    {
        $card = StudentCard::findOrFail($id);

        // Delete PDF file if exists
        if ($card->pdf_path) {
            Storage::disk('tenant')->delete($card->pdf_path);
        }

        $card->delete();

        return response()->json([
            'message' => 'Carte supprimée avec succès.',
        ]);
    }
}
