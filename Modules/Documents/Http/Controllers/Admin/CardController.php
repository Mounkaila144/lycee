<?php

namespace Modules\Documents\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Documents\Entities\StudentCard;
use Modules\Documents\Services\CardService;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\AcademicYear;

/**
 * Controller for Epic 4: Cards (Stories 19-20)
 */
class CardController extends Controller
{
    public function __construct(
        private CardService $cardService
    ) {}

    /**
     * Story 19: Generate student ID card
     */
    public function generateStudentCard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:tenant.students,id',
            'academic_year_id' => 'required|exists:tenant.academic_years,id',
            'access_permissions' => 'nullable|array',
        ]);

        $student = Student::findOrFail($validated['student_id']);
        $academicYear = AcademicYear::findOrFail($validated['academic_year_id']);

        $card = $this->cardService->generateStudentCard(
            $student,
            $academicYear,
            $validated['access_permissions'] ?? null
        );

        return response()->json([
            'message' => 'Student card generated successfully',
            'card' => $card->load(['student', 'academicYear', 'document']),
        ], 201);
    }

    /**
     * Story 20: Generate access badge
     */
    public function generateAccessBadge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:tenant.students,id',
            'academic_year_id' => 'required|exists:tenant.academic_years,id',
            'access_permissions' => 'required|array|min:1',
        ]);

        $student = Student::findOrFail($validated['student_id']);
        $academicYear = AcademicYear::findOrFail($validated['academic_year_id']);

        $badge = $this->cardService->generateAccessBadge(
            $student,
            $academicYear,
            $validated['access_permissions']
        );

        return response()->json([
            'message' => 'Access badge generated successfully',
            'badge' => $badge->load(['student', 'academicYear', 'document']),
        ], 201);
    }

    /**
     * Batch generate student cards
     */
    public function batchGenerateCards(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_ids' => 'required|array|min:1|max:500',
            'student_ids.*' => 'exists:tenant.students,id',
            'academic_year_id' => 'required|exists:tenant.academic_years,id',
            'access_permissions' => 'nullable|array',
        ]);

        $academicYear = AcademicYear::findOrFail($validated['academic_year_id']);

        $results = $this->cardService->batchGenerateStudentCards(
            $validated['student_ids'],
            $academicYear,
            $validated['access_permissions'] ?? null
        );

        $successCount = collect($results)->where('status', 'success')->count();
        $failedCount = collect($results)->where('status', 'failed')->count();

        return response()->json([
            'message' => "Batch generation completed: {$successCount} succeeded, {$failedCount} failed",
            'results' => $results,
            'summary' => [
                'total' => count($results),
                'success' => $successCount,
                'failed' => $failedCount,
            ],
        ]);
    }

    /**
     * List student cards
     */
    public function index(Request $request): JsonResponse
    {
        $query = StudentCard::with(['student', 'academicYear'])
            ->orderBy('issue_date', 'desc');

        if ($request->has('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->has('card_type')) {
            $query->where('card_type', $request->card_type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('academic_year_id')) {
            $query->where('academic_year_id', $request->academic_year_id);
        }

        if ($request->has('is_printed')) {
            $query->where('is_printed', $request->boolean('is_printed'));
        }

        $cards = $query->paginate($request->per_page ?? 50);

        return response()->json($cards);
    }

    /**
     * Replace lost or stolen card
     */
    public function replaceCard(Request $request, int $cardId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|in:lost,stolen,damaged',
        ]);

        $oldCard = StudentCard::findOrFail($cardId);

        $newCard = $this->cardService->replaceCard($oldCard, $validated['reason']);

        return response()->json([
            'message' => 'Card replaced successfully',
            'old_card' => $oldCard->fresh(),
            'new_card' => $newCard->load(['student', 'academicYear', 'document']),
        ]);
    }

    /**
     * Print card
     */
    public function printCard(int $cardId): JsonResponse
    {
        $card = StudentCard::findOrFail($cardId);

        $this->cardService->printCard($card, auth()->id());

        return response()->json([
            'message' => 'Card marked as printed',
            'card' => $card->fresh(),
        ]);
    }

    /**
     * Batch print cards
     */
    public function batchPrintCards(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'card_ids' => 'required|array|min:1|max:500',
            'card_ids.*' => 'exists:tenant.student_cards,id',
        ]);

        $results = $this->cardService->batchPrintCards($validated['card_ids'], auth()->id());

        $successCount = collect($results)->where('status', 'success')->count();
        $failedCount = collect($results)->where('status', 'failed')->count();

        return response()->json([
            'message' => "Batch print completed: {$successCount} succeeded, {$failedCount} failed",
            'results' => $results,
            'summary' => [
                'total' => count($results),
                'success' => $successCount,
                'failed' => $failedCount,
            ],
        ]);
    }

    /**
     * Update card access permissions
     */
    public function updateAccessPermissions(Request $request, int $cardId): JsonResponse
    {
        $validated = $request->validate([
            'access_permissions' => 'required|array|min:1',
        ]);

        $card = StudentCard::findOrFail($cardId);

        $this->cardService->updateAccessPermissions($card, $validated['access_permissions']);

        return response()->json([
            'message' => 'Access permissions updated',
            'card' => $card->fresh(),
        ]);
    }

    /**
     * Suspend card
     */
    public function suspendCard(int $cardId): JsonResponse
    {
        $card = StudentCard::findOrFail($cardId);
        $card->suspend();

        return response()->json([
            'message' => 'Card suspended',
            'card' => $card->fresh(),
        ]);
    }

    /**
     * Activate card
     */
    public function activateCard(int $cardId): JsonResponse
    {
        $card = StudentCard::findOrFail($cardId);
        $card->activate();

        return response()->json([
            'message' => 'Card activated',
            'card' => $card->fresh(),
        ]);
    }
}
