<?php

namespace Modules\Enrollment\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Enrollment\Entities\Equivalence;
use Modules\Enrollment\Entities\PedagogicalEnrollment;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentModuleEnrollment;
use Modules\Enrollment\Entities\Transfer;
use Modules\Enrollment\Entities\TransferDocument;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Semester;
use Modules\UsersGuard\Entities\TenantUser;

class TransferService
{
    public function __construct(
        private EquivalenceMatchingService $equivalenceService
    ) {}

    /**
     * Create a new transfer request
     */
    public function createTransferRequest(array $data, array $documents = []): Transfer
    {
        $transfer = DB::transaction(function () use ($data, $documents) {
            // Generate unique transfer number
            $transferNumber = Transfer::generateTransferNumber($data['academic_year_id']);

            $transfer = Transfer::create([
                'transfer_number' => $transferNumber,
                'firstname' => $data['firstname'],
                'lastname' => $data['lastname'],
                'birthdate' => $data['birthdate'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'origin_institution' => $data['origin_institution'],
                'origin_program' => $data['origin_program'],
                'origin_level' => $data['origin_level'],
                'target_program_id' => $data['target_program_id'],
                'target_level' => $data['target_level'],
                'academic_year_id' => $data['academic_year_id'],
                'transfer_reason' => $data['transfer_reason'],
                'total_ects_claimed' => $data['total_ects_claimed'] ?? 0,
                'status' => Transfer::STATUS_SUBMITTED,
            ]);

            // Upload documents
            foreach ($documents as $type => $document) {
                if ($document instanceof UploadedFile) {
                    $this->uploadDocument($transfer, $document, $type);
                }
            }

            return $transfer;
        });

        return $transfer;
    }

    /**
     * Upload document for transfer
     */
    public function uploadDocument(Transfer $transfer, UploadedFile $file, string $type): TransferDocument
    {
        $path = "transfers/{$transfer->academic_year_id}/{$transfer->transfer_number}";
        $fileName = $type.'_'.time().'.'.$file->getClientOriginalExtension();

        $storedPath = Storage::disk('tenant')->putFileAs($path, $file, $fileName);

        return TransferDocument::create([
            'transfer_id' => $transfer->id,
            'type' => $type,
            'original_name' => $file->getClientOriginalName(),
            'path' => $storedPath,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    /**
     * Start review process
     */
    public function startReview(Transfer $transfer, TenantUser $reviewer): Transfer
    {
        if (! $transfer->canBeReviewed()) {
            throw new \Exception('Transfer cannot be reviewed in current status');
        }

        $transfer->update([
            'status' => Transfer::STATUS_UNDER_REVIEW,
            'reviewed_by' => $reviewer->id,
        ]);

        return $transfer->fresh();
    }

    /**
     * Analyze and suggest equivalences
     */
    public function analyzeEquivalences(Transfer $transfer, array $originModules): array
    {
        if (! in_array($transfer->status, [Transfer::STATUS_SUBMITTED, Transfer::STATUS_UNDER_REVIEW])) {
            throw new \Exception('Transfer not in correct status for equivalence analysis');
        }

        // Update status
        if ($transfer->status === Transfer::STATUS_SUBMITTED) {
            $transfer->update(['status' => Transfer::STATUS_UNDER_REVIEW]);
        }

        // Get suggestions
        $suggestions = $this->equivalenceService->suggestEquivalences($transfer, $originModules);

        // Create equivalence records
        $equivalences = $this->equivalenceService->createEquivalencesFromSuggestions($transfer, $suggestions);

        // Update transfer status
        $transfer->update(['status' => Transfer::STATUS_EQUIVALENCES_PROPOSED]);

        return [
            'transfer' => $transfer->fresh(),
            'equivalences' => $equivalences,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * Validate transfer with equivalences
     */
    public function validateTransfer(Transfer $transfer, TenantUser $validator): Transfer
    {
        if (! $transfer->canBeValidated()) {
            throw new \Exception('Transfer cannot be validated in current status');
        }

        // Check if there are proposed equivalences that need validation
        $pendingEquivalences = Equivalence::where('transfer_id', $transfer->id)
            ->where('status', Equivalence::STATUS_PROPOSED)
            ->count();

        if ($pendingEquivalences > 0) {
            throw new \Exception('There are pending equivalences that need to be validated first');
        }

        $transfer->update([
            'status' => Transfer::STATUS_VALIDATED,
            'reviewed_by' => $validator->id,
            'reviewed_at' => now(),
        ]);

        return $transfer->fresh();
    }

    /**
     * Integrate student from transfer
     */
    public function integrateStudent(Transfer $transfer, TenantUser $processor): Student
    {
        if (! $transfer->canBeIntegrated()) {
            throw new \Exception('Transfer cannot be integrated in current status');
        }

        return DB::transaction(function () use ($transfer, $processor) {
            // 1. Create student if not existing
            $student = $transfer->student ?? $this->createStudentFromTransfer($transfer);

            $transfer->update([
                'student_id' => $student->id,
                'status' => Transfer::STATUS_INTEGRATED,
                'reviewed_by' => $processor->id,
                'reviewed_at' => now(),
            ]);

            // 2. Create grades for validated equivalences
            $validatedEquivalences = Equivalence::where('transfer_id', $transfer->id)
                ->where('status', Equivalence::STATUS_VALIDATED)
                ->whereNotNull('target_module_id')
                ->get();

            foreach ($validatedEquivalences as $equivalence) {
                if ($equivalence->equivalence_type === Equivalence::TYPE_NONE) {
                    continue;
                }

                // Create grade record with equivalence flag
                // This would integrate with Grades module when available
                // For now, we just track the validated equivalences
            }

            // 3. Create pedagogical enrollment
            // Find current semester for the academic year (based on dates)
            $semester = Semester::where('academic_year_id', $transfer->academic_year_id)
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->first() ?? Semester::where('academic_year_id', $transfer->academic_year_id)->first();

            $enrollment = PedagogicalEnrollment::create([
                'student_id' => $student->id,
                'programme_id' => $transfer->target_program_id,
                'level' => $transfer->target_level,
                'academic_year_id' => $transfer->academic_year_id,
                'semester_id' => $semester?->id,
                'enrollment_date' => now()->toDateString(),
                'status' => 'Actif',
            ]);

            // 4. Enroll in remaining modules (not covered by equivalences)
            $this->enrollRemainingModules($student, $transfer, $enrollment);

            // 5. Generate equivalence certificate
            $this->generateEquivalenceCertificate($transfer);

            return $student;
        });
    }

    /**
     * Reject transfer
     */
    public function rejectTransfer(Transfer $transfer, TenantUser $reviewer, string $reason): Transfer
    {
        if (! $transfer->canBeRejected()) {
            throw new \Exception('Transfer cannot be rejected in current status');
        }

        $transfer->update([
            'status' => Transfer::STATUS_REJECTED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $transfer->fresh();
    }

    /**
     * Get transfer statistics
     */
    public function getStatistics(?int $academicYearId = null): array
    {
        $query = Transfer::query();

        if ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        }

        $total = $query->count();

        return [
            'total' => $total,
            'by_status' => [
                'submitted' => (clone $query)->where('status', 'Submitted')->count(),
                'under_review' => (clone $query)->where('status', 'Under_Review')->count(),
                'equivalences_proposed' => (clone $query)->where('status', 'Equivalences_Proposed')->count(),
                'validated' => (clone $query)->where('status', 'Validated')->count(),
                'integrated' => (clone $query)->where('status', 'Integrated')->count(),
                'rejected' => (clone $query)->where('status', 'Rejected')->count(),
            ],
            'acceptance_rate' => $total > 0
                ? round(((clone $query)->whereIn('status', ['Validated', 'Integrated'])->count() / $total) * 100, 2)
                : 0,
            'average_ects_granted' => Transfer::where('status', 'Integrated')
                ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
                ->avg('total_ects_granted') ?? 0,
            'by_origin' => Transfer::query()
                ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
                ->selectRaw('origin_institution, COUNT(*) as count')
                ->groupBy('origin_institution')
                ->orderByDesc('count')
                ->limit(10)
                ->pluck('count', 'origin_institution')
                ->toArray(),
        ];
    }

    /**
     * Create student from transfer data or find existing by email
     */
    private function createStudentFromTransfer(Transfer $transfer): Student
    {
        // Check if student with same email already exists
        $existingStudent = Student::where('email', $transfer->email)->first();

        if ($existingStudent) {
            return $existingStudent;
        }

        $matricule = $this->generateMatricule($transfer);

        return Student::create([
            'matricule' => $matricule,
            'firstname' => $transfer->firstname,
            'lastname' => $transfer->lastname,
            'birthdate' => $transfer->birthdate,
            'email' => $transfer->email,
            'phone' => $transfer->phone,
            'mobile' => $transfer->phone,
            'status' => 'Actif',
        ]);
    }

    /**
     * Generate matricule for transfer student
     */
    private function generateMatricule(Transfer $transfer): string
    {
        $year = date('y');
        $programCode = substr($transfer->targetProgram->code ?? 'TR', 0, 3);
        $sequence = Student::whereYear('created_at', date('Y'))->count() + 1;

        return sprintf('%s%s%04d', $year, strtoupper($programCode), $sequence);
    }

    /**
     * Enroll student in remaining modules
     */
    private function enrollRemainingModules(Student $student, Transfer $transfer, PedagogicalEnrollment $enrollment): void
    {
        // Get modules covered by equivalences
        $equivalentModuleIds = Equivalence::where('transfer_id', $transfer->id)
            ->where('status', Equivalence::STATUS_VALIDATED)
            ->whereIn('equivalence_type', [Equivalence::TYPE_FULL, Equivalence::TYPE_EXEMPTION])
            ->pluck('target_module_id')
            ->filter();

        // Get all required modules for the program/level
        $allRequiredModules = Module::query()
            ->whereHas('programmes', function ($q) use ($transfer) {
                $q->where('programmes.id', $transfer->target_program_id);
            })
            ->where('level', $transfer->target_level)
            ->where('type', 'Obligatoire')
            ->pluck('id');

        // Enroll in modules not covered by equivalences
        $modulesToEnroll = $allRequiredModules->diff($equivalentModuleIds);

        foreach ($modulesToEnroll as $moduleId) {
            StudentModuleEnrollment::create([
                'student_id' => $student->id,
                'student_enrollment_id' => $enrollment->id,
                'module_id' => $moduleId,
                'semester_id' => $enrollment->semester_id,
                'enrollment_date' => now()->toDateString(),
                'status' => 'Inscrit',
            ]);
        }
    }

    /**
     * Generate equivalence certificate PDF
     */
    private function generateEquivalenceCertificate(Transfer $transfer): void
    {
        try {
            $equivalences = Equivalence::where('transfer_id', $transfer->id)
                ->where('status', Equivalence::STATUS_VALIDATED)
                ->with('targetModule')
                ->get();

            $pdf = Pdf::loadView('enrollment::transfers.equivalence_certificate', [
                'transfer' => $transfer->load(['student', 'targetProgram', 'academicYear']),
                'equivalences' => $equivalences,
            ]);

            $fileName = "attestation_equivalences_{$transfer->transfer_number}.pdf";
            $path = "transfers/{$transfer->academic_year_id}/{$fileName}";

            Storage::disk('tenant')->put($path, $pdf->output());

            $transfer->update(['equivalence_certificate_path' => $path]);
        } catch (\Exception $e) {
            logger()->error('Failed to generate equivalence certificate: '.$e->getMessage());
        }
    }
}
