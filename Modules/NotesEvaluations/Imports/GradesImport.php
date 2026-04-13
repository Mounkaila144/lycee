<?php

namespace Modules\NotesEvaluations\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentModuleEnrollment;
use Modules\NotesEvaluations\Entities\Grade;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;
use Modules\UsersGuard\Entities\TenantUser;

class GradesImport implements SkipsEmptyRows, ToCollection, WithBatchInserts, WithHeadingRow, WithValidation
{
    /** @var array<int, array{row: int, error: string}> */
    private array $errors = [];

    private int $imported = 0;

    private int $updated = 0;

    private int $skipped = 0;

    public function __construct(
        private ModuleEvaluationConfig $evaluation,
        private TenantUser $teacher,
        private string $importMode = 'add'
    ) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2 because of header row and 0-indexing

            try {
                $this->processRow($row->toArray(), $rowNumber);
            } catch (\Exception $e) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'error' => $e->getMessage(),
                    'data' => $row->toArray(),
                ];
            }
        }
    }

    private function processRow(array $row, int $rowNumber): void
    {
        $matricule = $row['matricule'] ?? null;
        $note = $row['note_0_20_ou_abs'] ?? $row['note'] ?? null;
        $comment = $row['commentaire'] ?? $row['comment'] ?? null;

        if (empty($matricule)) {
            throw new \Exception('Matricule manquant');
        }

        // Find student
        $student = Student::where('matricule', $matricule)->first();
        if (! $student) {
            throw new \Exception("Étudiant introuvable: {$matricule}");
        }

        // Verify student is enrolled in this module
        $isEnrolled = StudentModuleEnrollment::forStudent($student->id)
            ->forModule($this->evaluation->module_id)
            ->when($this->evaluation->semester_id, fn ($q) => $q->bySemester($this->evaluation->semester_id))
            ->inscrit()
            ->exists();

        if (! $isEnrolled) {
            throw new \Exception("Étudiant non inscrit au module: {$matricule}");
        }

        // Parse grade
        $isAbsent = false;
        $score = null;

        if ($note !== null && $note !== '') {
            $noteStr = strtoupper(trim((string) $note));

            if (in_array($noteStr, ['ABS', 'ABSENT', 'A'])) {
                $isAbsent = true;
            } elseif (in_array($noteStr, ['EXC', 'EXCLU', 'E'])) {
                $isAbsent = true;
            } else {
                $score = (float) str_replace(',', '.', $noteStr);

                if ($score < 0 || $score > 20) {
                    throw new \Exception("Note hors limites (0-20): {$score}");
                }

                $score = round($score, 2);
            }
        }

        // Check existing grade
        $existingGrade = Grade::where('student_id', $student->id)
            ->where('evaluation_id', $this->evaluation->id)
            ->first();

        if ($existingGrade) {
            if ($this->importMode === 'add') {
                $this->skipped++;

                return;
            }

            if ($existingGrade->isPublished() && $this->importMode !== 'overwrite') {
                throw new \Exception('Note déjà publiée, modification non autorisée');
            }

            // Update
            $existingGrade->update([
                'score' => $score,
                'is_absent' => $isAbsent,
                'comment' => $comment ?? $existingGrade->comment,
                'entered_by' => $this->teacher->id,
                'entered_at' => now(),
            ]);
            $this->updated++;
        } else {
            // Create
            Grade::create([
                'student_id' => $student->id,
                'evaluation_id' => $this->evaluation->id,
                'score' => $score,
                'is_absent' => $isAbsent,
                'comment' => $comment,
                'entered_by' => $this->teacher->id,
                'entered_at' => now(),
                'status' => 'Draft',
            ]);
            $this->imported++;
        }
    }

    public function rules(): array
    {
        return [
            'matricule' => ['required', 'string'],
            'note_0_20_ou_abs' => ['nullable'],
            'note' => ['nullable'],
        ];
    }

    public function batchSize(): int
    {
        return 100;
    }

    /**
     * @return array{imported: int, updated: int, skipped: int, errors: array, total_processed: int}
     */
    public function getReport(): array
    {
        return [
            'imported' => $this->imported,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'total_processed' => $this->imported + $this->updated + $this->skipped,
        ];
    }

    public function getImported(): int
    {
        return $this->imported;
    }

    public function getUpdated(): int
    {
        return $this->updated;
    }

    public function getSkipped(): int
    {
        return $this->skipped;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
