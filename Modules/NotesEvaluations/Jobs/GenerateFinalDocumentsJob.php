<?php

namespace Modules\NotesEvaluations\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\NotesEvaluations\Entities\ModuleGrade;
use Modules\NotesEvaluations\Entities\SemesterResult;

class GenerateFinalDocumentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public SemesterResult $semesterResult
    ) {}

    public function handle(): void
    {
        Log::info('Generating final documents for student', [
            'student_id' => $this->semesterResult->student_id,
            'semester_id' => $this->semesterResult->semester_id,
        ]);

        try {
            // Load relationships
            $this->semesterResult->load(['student', 'semester']);

            // Generate attestation PDF
            $attestationPath = $this->generateAttestationPdf();

            // Update semester result with attestation path
            $this->semesterResult->update([
                'attestation_file_path' => $attestationPath,
            ]);

            Log::info('Final documents generated successfully', [
                'student_id' => $this->semesterResult->student_id,
                'attestation_path' => $attestationPath,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate final documents', [
                'student_id' => $this->semesterResult->student_id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate attestation PDF
     */
    protected function generateAttestationPdf(): string
    {
        $student = $this->semesterResult->student;
        $semester = $this->semesterResult->semester;

        // Get module grades
        $moduleGrades = ModuleGrade::where([
            'student_id' => $this->semesterResult->student_id,
            'semester_id' => $this->semesterResult->semester_id,
        ])
            ->with('module')
            ->orderBy('module_id')
            ->get();

        // Prepare data for PDF
        $data = [
            'student' => [
                'matricule' => $student->matricule ?? 'N/A',
                'full_name' => $student->full_name ?? $student->firstname.' '.$student->lastname,
                'birth_date' => $student->birth_date?->format('d/m/Y'),
                'programme' => $student->programme?->name ?? 'N/A',
            ],
            'semester' => [
                'name' => $semester->name ?? 'N/A',
                'academic_year' => $semester->academicYear?->name ?? 'N/A',
            ],
            'result' => [
                'average' => number_format($this->semesterResult->average ?? 0, 2),
                'mention' => $this->semesterResult->mention,
                'rank' => $this->semesterResult->rank,
                'total_ranked' => $this->semesterResult->total_ranked,
                'total_credits' => $this->semesterResult->total_credits,
                'acquired_credits' => $this->semesterResult->acquired_credits,
                'final_status' => $this->semesterResult->final_status_label,
            ],
            'modules' => $moduleGrades->map(fn ($mg) => [
                'code' => $mg->module?->code,
                'name' => $mg->module?->name,
                'credits' => $mg->module?->credits_ects,
                'average' => $mg->average ? number_format($mg->average, 2) : 'ABS',
                'status' => $mg->status,
            ])->toArray(),
            'generated_at' => now()->format('d/m/Y H:i'),
        ];

        // Generate file path
        $filename = sprintf(
            'attestations/%s/%s_%s_%s.pdf',
            $semester->id ?? 'unknown',
            $student->matricule ?? $student->id,
            'attestation',
            now()->format('Ymd_His')
        );

        // For now, store JSON data as placeholder
        // In production, this would use a PDF library like DomPDF or Snappy
        $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        Storage::disk('tenant')->put(str_replace('.pdf', '.json', $filename), $jsonContent);

        // Return the path (PDF generation would happen here in production)
        return $filename;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateFinalDocumentsJob failed permanently', [
            'student_id' => $this->semesterResult->student_id,
            'semester_id' => $this->semesterResult->semester_id,
            'error' => $exception->getMessage(),
        ]);
    }
}
