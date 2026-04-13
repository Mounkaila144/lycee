<?php

namespace Modules\Enrollment\Http\Controllers\Frontend;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentCard;
use Modules\Enrollment\Http\Resources\StudentCardResource;
use Modules\StructureAcademique\Entities\AcademicYear;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MyCardController extends Controller
{
    /**
     * Get the authenticated student's current card
     */
    public function show(): JsonResponse
    {
        $student = $this->getAuthenticatedStudent();

        if (! $student) {
            return response()->json([
                'message' => 'Profil étudiant non trouvé',
                'data' => null,
            ], 404);
        }

        // Get the most recent active card
        $card = StudentCard::on('tenant')
            ->with(['student', 'academicYear'])
            ->where('student_id', $student->id)
            ->where('status', StudentCard::STATUS_ACTIVE)
            ->where('is_duplicate', false)
            ->orderBy('issued_at', 'desc')
            ->first();

        if (! $card) {
            return response()->json([
                'message' => 'Aucune carte étudiant disponible. Votre inscription doit être validée.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'data' => new StudentCardResource($card),
            'meta' => [
                'student' => [
                    'id' => $student->id,
                    'matricule' => $student->matricule,
                    'name' => $student->firstname.' '.$student->lastname,
                ],
                'is_valid' => $card->isValid(),
                'is_expired' => $card->isExpired(),
                'days_until_expiry' => $card->getDaysUntilExpiry(),
            ],
        ]);
    }

    /**
     * Get all cards history for the authenticated student
     */
    public function history(): JsonResponse
    {
        $student = $this->getAuthenticatedStudent();

        if (! $student) {
            return response()->json([
                'message' => 'Profil étudiant non trouvé',
                'data' => [],
            ], 404);
        }

        $cards = StudentCard::on('tenant')
            ->with(['academicYear'])
            ->where('student_id', $student->id)
            ->orderBy('issued_at', 'desc')
            ->get();

        return response()->json([
            'data' => StudentCardResource::collection($cards),
            'meta' => [
                'total' => $cards->count(),
                'active_count' => $cards->where('status', StudentCard::STATUS_ACTIVE)->count(),
                'duplicates_count' => $cards->where('is_duplicate', true)->count(),
            ],
        ]);
    }

    /**
     * Download the student card PDF
     */
    public function download(): StreamedResponse|JsonResponse
    {
        $student = $this->getAuthenticatedStudent();

        if (! $student) {
            return response()->json([
                'message' => 'Profil étudiant non trouvé',
            ], 404);
        }

        // Get the current active card
        $card = StudentCard::on('tenant')
            ->where('student_id', $student->id)
            ->where('status', StudentCard::STATUS_ACTIVE)
            ->where('is_duplicate', false)
            ->whereNotNull('pdf_path')
            ->orderBy('issued_at', 'desc')
            ->first();

        if (! $card) {
            return response()->json([
                'message' => 'Aucune carte étudiant disponible au téléchargement.',
            ], 404);
        }

        $disk = Storage::disk('tenant');

        if (! $disk->exists($card->pdf_path)) {
            return response()->json([
                'message' => 'Fichier de la carte introuvable. Veuillez contacter la scolarité.',
            ], 404);
        }

        $fileName = "carte_etudiant_{$student->matricule}_{$card->card_number}.pdf";

        return $disk->download($card->pdf_path, $fileName, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Get card for a specific academic year
     */
    public function showByYear(int $academicYearId): JsonResponse
    {
        $student = $this->getAuthenticatedStudent();

        if (! $student) {
            return response()->json([
                'message' => 'Profil étudiant non trouvé',
                'data' => null,
            ], 404);
        }

        $academicYear = AcademicYear::on('tenant')->find($academicYearId);

        if (! $academicYear) {
            return response()->json([
                'message' => 'Année académique non trouvée',
                'data' => null,
            ], 404);
        }

        $card = StudentCard::on('tenant')
            ->with(['student', 'academicYear'])
            ->where('student_id', $student->id)
            ->where('academic_year_id', $academicYearId)
            ->where('is_duplicate', false)
            ->first();

        if (! $card) {
            return response()->json([
                'message' => "Aucune carte étudiant pour l'année {$academicYear->name}",
                'data' => null,
            ], 404);
        }

        return response()->json([
            'data' => new StudentCardResource($card),
            'meta' => [
                'is_valid' => $card->isValid(),
                'is_expired' => $card->isExpired(),
            ],
        ]);
    }

    /**
     * Get QR code data for the current card (for mobile display)
     */
    public function qrCode(): JsonResponse
    {
        $student = $this->getAuthenticatedStudent();

        if (! $student) {
            return response()->json([
                'message' => 'Profil étudiant non trouvé',
            ], 404);
        }

        $card = StudentCard::on('tenant')
            ->where('student_id', $student->id)
            ->where('status', StudentCard::STATUS_ACTIVE)
            ->where('is_duplicate', false)
            ->orderBy('issued_at', 'desc')
            ->first();

        if (! $card) {
            return response()->json([
                'message' => 'Aucune carte étudiant active',
            ], 404);
        }

        return response()->json([
            'data' => [
                'qr_data' => $card->qr_code_data,
                'signature' => $card->qr_signature,
                'card_number' => $card->card_number,
                'valid_until' => $card->valid_until->toIso8601String(),
                'is_valid' => $card->isValid(),
            ],
        ]);
    }

    /**
     * Get the authenticated student from the user
     */
    private function getAuthenticatedStudent(): ?Student
    {
        $user = Auth::user();

        return Student::on('tenant')
            ->where('email', $user->email)
            ->first();
    }
}
