<?php

namespace Modules\NotesEvaluations\Services;

use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentModuleEnrollment;
use Modules\NotesEvaluations\Entities\Grade;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;
use Modules\UsersGuard\Entities\User;

class BatchGradeService
{
    /**
     * Validate batch grades data without saving
     *
     * @return array{valid: bool, valid_count: int, error_count: int, errors: array, warnings: array}
     */
    public function validateBatch(
        ModuleEvaluationConfig $evaluation,
        array $gradesData
    ): array {
        $results = [
            'valid' => true,
            'valid_count' => 0,
            'error_count' => 0,
            'errors' => [],
            'warnings' => [],
        ];

        // Get enrolled students
        $enrolledStudents = StudentModuleEnrollment::forModule($evaluation->module_id)
            ->when($evaluation->semester_id, fn ($q) => $q->bySemester($evaluation->semester_id))
            ->inscrit()
            ->pluck('student_id');

        foreach ($gradesData as $index => $data) {
            $lineNumber = $index + 1;
            $errors = [];
            $warnings = [];

            // Validate matricule
            $matricule = $data['matricule'] ?? null;
            if (empty($matricule)) {
                $errors[] = 'Matricule manquant';
            } else {
                $student = Student::where('matricule', $matricule)->first();

                if (! $student) {
                    $errors[] = "Matricule introuvable: {$matricule}";
                } elseif (! $enrolledStudents->contains($student->id)) {
                    $errors[] = "Étudiant non inscrit au module: {$matricule}";
                }
            }

            // Validate score
            $score = $data['score'] ?? null;
            $isAbsent = $data['is_absent'] ?? false;

            if (! $isAbsent && $score !== null) {
                if (! is_numeric($score)) {
                    $errors[] = "Note invalide: {$score}";
                } elseif ($score < 0 || $score > 20) {
                    $errors[] = "Note hors limites (0-20): {$score}";
                } elseif ($score < 5) {
                    $warnings[] = "Note très basse: {$score}";
                }
            }

            if (! empty($errors)) {
                $results['errors'][] = [
                    'line' => $lineNumber,
                    'matricule' => $matricule,
                    'errors' => $errors,
                ];
                $results['error_count']++;
                $results['valid'] = false;
            } else {
                $results['valid_count']++;
            }

            if (! empty($warnings)) {
                $results['warnings'][] = [
                    'line' => $lineNumber,
                    'matricule' => $matricule,
                    'warnings' => $warnings,
                ];
            }
        }

        return $results;
    }

    /**
     * Process batch grades
     *
     * @return array{created: int, updated: int, skipped: int, errors: array}
     */
    public function processBatch(
        ModuleEvaluationConfig $evaluation,
        array $gradesData,
        User $teacher,
        bool $overwriteExisting = false
    ): array {
        $results = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        DB::beginTransaction();
        try {
            foreach ($gradesData as $data) {
                try {
                    $result = $this->processGrade($evaluation, $data, $teacher, $overwriteExisting);

                    switch ($result) {
                        case 'created':
                            $results['created']++;
                            break;
                        case 'updated':
                            $results['updated']++;
                            break;
                        case 'skipped':
                            $results['skipped']++;
                            break;
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'matricule' => $data['matricule'] ?? 'N/A',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $results;
    }

    /**
     * Process a single grade
     */
    private function processGrade(
        ModuleEvaluationConfig $evaluation,
        array $data,
        User $teacher,
        bool $overwriteExisting
    ): string {
        $matricule = $data['matricule'] ?? null;
        $student = Student::where('matricule', $matricule)->first();

        if (! $student) {
            throw new \Exception("Étudiant introuvable: {$matricule}");
        }

        // Check enrollment
        $isEnrolled = StudentModuleEnrollment::forStudent($student->id)
            ->forModule($evaluation->module_id)
            ->inscrit()
            ->exists();

        if (! $isEnrolled) {
            throw new \Exception('Étudiant non inscrit au module');
        }

        $isAbsent = $data['is_absent'] ?? false;
        $score = $isAbsent ? null : ($data['score'] ?? null);
        $comment = $data['comment'] ?? null;

        // Check existing grade
        $existingGrade = Grade::where('student_id', $student->id)
            ->where('evaluation_id', $evaluation->id)
            ->first();

        if ($existingGrade) {
            if (! $overwriteExisting) {
                return 'skipped';
            }

            if ($existingGrade->isPublished()) {
                throw new \Exception('Note publiée - modification non autorisée');
            }

            $existingGrade->update([
                'score' => $score,
                'is_absent' => $isAbsent,
                'comment' => $comment ?? $existingGrade->comment,
                'entered_by' => $teacher->id,
                'entered_at' => now(),
            ]);

            return 'updated';
        }

        Grade::create([
            'student_id' => $student->id,
            'evaluation_id' => $evaluation->id,
            'score' => $score,
            'is_absent' => $isAbsent,
            'comment' => $comment,
            'entered_by' => $teacher->id,
            'entered_at' => now(),
            'status' => 'Draft',
        ]);

        return 'created';
    }

    /**
     * Parse clipboard data format
     *
     * @return array{format: string, data: array}
     */
    public function parseClipboardData(string $text): array
    {
        $lines = array_filter(explode("\n", trim($text)));
        $data = [];

        if (empty($lines)) {
            return ['format' => 'empty', 'data' => []];
        }

        $firstLine = $lines[0];
        $hasTabs = str_contains($firstLine, "\t");

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            if ($hasTabs) {
                $cells = explode("\t", $line);
                $data[] = [
                    'matricule' => $cells[0] ?? null,
                    'score' => $this->parseScore($cells[1] ?? null),
                    'is_absent' => $this->isAbsentValue($cells[1] ?? null),
                    'comment' => $cells[2] ?? null,
                ];
            } else {
                // Single column - just scores in order
                $data[] = [
                    'score' => $this->parseScore($line),
                    'is_absent' => $this->isAbsentValue($line),
                ];
            }
        }

        return [
            'format' => $hasTabs ? 'multi-column' : 'single-column',
            'data' => $data,
        ];
    }

    /**
     * Parse score value
     */
    private function parseScore(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = strtoupper(trim($value));

        if (in_array($value, ['ABS', 'ABSENT', 'A', 'EXC', 'E'])) {
            return null;
        }

        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    /**
     * Check if value represents absence
     */
    private function isAbsentValue(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return in_array(strtoupper(trim($value)), ['ABS', 'ABSENT', 'A', 'EXC', 'E']);
    }
}
