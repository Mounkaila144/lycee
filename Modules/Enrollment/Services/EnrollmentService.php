<?php

namespace Modules\Enrollment\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentEnrollment;
use Modules\Enrollment\Entities\StudentModuleEnrollment;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\Semester;

class EnrollmentService
{
    /**
     * Create a student enrollment (pedagogical inscription)
     *
     * @return array{enrollment: StudentEnrollment, module_enrollments: Collection}
     */
    public function createEnrollment(
        Student $student,
        Programme $programme,
        Semester $semester,
        string $level,
        array $moduleIds = [],
        ?int $groupId = null,
        bool $autoEnrollObligatory = true
    ): array {
        return DB::connection('tenant')->transaction(function () use (
            $student, $programme, $semester, $level, $moduleIds, $groupId, $autoEnrollObligatory
        ) {
            // Check if enrollment already exists
            $existingEnrollment = StudentEnrollment::on('tenant')
                ->where('student_id', $student->id)
                ->where('programme_id', $programme->id)
                ->where('semester_id', $semester->id)
                ->first();

            if ($existingEnrollment) {
                throw new \Exception('L\'étudiant est déjà inscrit à ce programme pour ce semestre');
            }

            // Create enrollment
            $enrollment = StudentEnrollment::on('tenant')->create([
                'student_id' => $student->id,
                'programme_id' => $programme->id,
                'academic_year_id' => $semester->academic_year_id,
                'semester_id' => $semester->id,
                'level' => $level,
                'group_id' => $groupId,
                'enrollment_date' => now(),
                'status' => 'Actif',
                'enrolled_by' => Auth::id(),
            ]);

            // Collect all modules to enroll
            $modulesToEnroll = collect();

            // Auto-enroll to obligatory modules
            if ($autoEnrollObligatory) {
                $obligatoryModules = $this->getAvailableModules($programme->id, $level, $semester->id)
                    ->where('type', 'Obligatoire');

                foreach ($obligatoryModules as $module) {
                    $modulesToEnroll->put($module->id, [
                        'module' => $module,
                        'is_optional' => false,
                    ]);
                }
            }

            // Add selected optional modules
            if (! empty($moduleIds)) {
                $selectedModules = Module::on('tenant')->whereIn('id', $moduleIds)->get();
                foreach ($selectedModules as $module) {
                    if (! $modulesToEnroll->has($module->id)) {
                        $modulesToEnroll->put($module->id, [
                            'module' => $module,
                            'is_optional' => $module->type === 'Optionnel',
                        ]);
                    }
                }
            }

            // Create module enrollments
            $moduleEnrollments = collect();
            foreach ($modulesToEnroll as $moduleData) {
                $moduleEnrollment = StudentModuleEnrollment::on('tenant')->create([
                    'student_id' => $student->id,
                    'student_enrollment_id' => $enrollment->id,
                    'module_id' => $moduleData['module']->id,
                    'semester_id' => $semester->id,
                    'enrollment_date' => now(),
                    'status' => 'Inscrit',
                    'is_optional' => $moduleData['is_optional'],
                    'enrolled_by' => Auth::id(),
                ]);
                $moduleEnrollments->push($moduleEnrollment);
            }

            return [
                'enrollment' => $enrollment->fresh(['programme', 'semester', 'academicYear']),
                'module_enrollments' => $moduleEnrollments,
            ];
        });
    }

    /**
     * Get available modules for a programme/level/semester
     */
    public function getAvailableModules(int $programmeId, string $level, int $semesterId): Collection
    {
        // Get the semester to determine the module semester code
        $semester = Semester::on('tenant')->find($semesterId);
        if (! $semester) {
            return collect();
        }

        // Calculate the module semester code based on level and semester name
        // L1 + S1 = S1, L1 + S2 = S2
        // L2 + S1 = S3, L2 + S2 = S4, etc.
        $moduleSemester = $this->calculateModuleSemester($level, $semester->name);

        return Module::on('tenant')
            ->whereHas('programmes', function ($query) use ($programmeId) {
                $query->where('programmes.id', $programmeId);
            })
            ->where('level', $level)
            ->where('semester', $moduleSemester)
            ->orderBy('type') // Obligatoire first
            ->orderBy('code')
            ->get();
    }

    /**
     * Calculate the module semester code based on level and semester name
     */
    private function calculateModuleSemester(string $level, string $semesterName): string
    {
        $semesterOffset = match ($level) {
            'L1' => 0,
            'L2' => 2,
            'L3' => 4,
            'M1' => 6,
            'M2' => 8,
            default => 0,
        };

        $semesterNumber = (int) str_replace('S', '', $semesterName);

        return 'S'.($semesterOffset + $semesterNumber);
    }

    /**
     * Get available modules for enrollment with enrollment status for a student
     */
    public function getModulesForEnrollment(
        int $programmeId,
        string $level,
        int $semesterId,
        ?int $studentId = null
    ): Collection {
        $modules = $this->getAvailableModules($programmeId, $level, $semesterId);

        if ($studentId) {
            // Get student's current module enrollments for this semester
            $enrolledModuleIds = StudentModuleEnrollment::on('tenant')
                ->where('student_id', $studentId)
                ->where('semester_id', $semesterId)
                ->pluck('module_id')
                ->toArray();

            return $modules->map(function ($module) use ($enrolledModuleIds) {
                $module->is_enrolled = in_array($module->id, $enrolledModuleIds);

                return $module;
            });
        }

        return $modules->map(function ($module) {
            $module->is_enrolled = false;

            return $module;
        });
    }

    /**
     * Add modules to an existing enrollment
     */
    public function addModulesToEnrollment(
        StudentEnrollment $enrollment,
        array $moduleIds
    ): Collection {
        $addedEnrollments = collect();

        foreach ($moduleIds as $moduleId) {
            // Check if already enrolled
            $existing = StudentModuleEnrollment::on('tenant')
                ->where('student_id', $enrollment->student_id)
                ->where('module_id', $moduleId)
                ->where('semester_id', $enrollment->semester_id)
                ->first();

            if ($existing) {
                continue; // Skip if already enrolled
            }

            $module = Module::on('tenant')->find($moduleId);
            if (! $module) {
                continue;
            }

            $moduleEnrollment = StudentModuleEnrollment::on('tenant')->create([
                'student_id' => $enrollment->student_id,
                'student_enrollment_id' => $enrollment->id,
                'module_id' => $moduleId,
                'semester_id' => $enrollment->semester_id,
                'enrollment_date' => now(),
                'status' => 'Inscrit',
                'is_optional' => $module->type === 'Optionnel',
                'enrolled_by' => Auth::id(),
            ]);

            $addedEnrollments->push($moduleEnrollment);
        }

        return $addedEnrollments;
    }

    /**
     * Remove modules from an enrollment
     */
    public function removeModulesFromEnrollment(
        StudentEnrollment $enrollment,
        array $moduleIds
    ): array {
        $removed = [];
        $errors = [];

        foreach ($moduleIds as $moduleId) {
            $moduleEnrollment = StudentModuleEnrollment::on('tenant')
                ->where('student_enrollment_id', $enrollment->id)
                ->where('module_id', $moduleId)
                ->first();

            if (! $moduleEnrollment) {
                $errors[] = "Module ID {$moduleId} non trouvé dans l'inscription";

                continue;
            }

            // Check if module is obligatory
            if (! $moduleEnrollment->is_optional) {
                $errors[] = "Le module {$moduleEnrollment->module->code} est obligatoire et ne peut pas être retiré";

                continue;
            }

            // Check if grades have been entered
            if ($moduleEnrollment->hasGrades()) {
                $errors[] = "Le module {$moduleEnrollment->module->code} a des notes saisies et ne peut pas être retiré";

                continue;
            }

            $moduleEnrollment->delete();
            $removed[] = $moduleId;
        }

        return [
            'removed' => $removed,
            'errors' => $errors,
        ];
    }

    /**
     * Get student's enrollments
     */
    public function getStudentEnrollments(int $studentId): Collection
    {
        return StudentEnrollment::on('tenant')
            ->with(['programme', 'semester', 'academicYear', 'moduleEnrollments.module'])
            ->where('student_id', $studentId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get student's module enrollments for a specific semester
     */
    public function getStudentModuleEnrollments(int $studentId, int $semesterId): Collection
    {
        return StudentModuleEnrollment::on('tenant')
            ->with(['module', 'semester'])
            ->where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Update enrollment status
     */
    public function updateEnrollmentStatus(StudentEnrollment $enrollment, string $status): StudentEnrollment
    {
        if (! in_array($status, StudentEnrollment::VALID_STATUSES)) {
            throw new \InvalidArgumentException("Statut invalide: {$status}");
        }

        $enrollment->update(['status' => $status]);

        return $enrollment->fresh();
    }

    /**
     * Update module enrollment status
     */
    public function updateModuleEnrollmentStatus(
        StudentModuleEnrollment $moduleEnrollment,
        string $status
    ): StudentModuleEnrollment {
        if (! in_array($status, StudentModuleEnrollment::VALID_STATUSES)) {
            throw new \InvalidArgumentException("Statut invalide: {$status}");
        }

        $moduleEnrollment->update(['status' => $status]);

        return $moduleEnrollment->fresh();
    }

    /**
     * Calculate total credits for a student's enrollment
     */
    public function calculateTotalCredits(StudentEnrollment $enrollment): int
    {
        return $enrollment->moduleEnrollments()
            ->with('module')
            ->get()
            ->sum(fn ($me) => $me->module->credits_ects ?? 0);
    }

    /**
     * Get enrollment statistics for a programme/semester
     */
    public function getEnrollmentStatistics(int $programmeId, int $semesterId): array
    {
        $enrollments = StudentEnrollment::on('tenant')
            ->where('programme_id', $programmeId)
            ->where('semester_id', $semesterId)
            ->get();

        return [
            'total_enrollments' => $enrollments->count(),
            'by_level' => $enrollments->groupBy('level')->map->count(),
            'by_status' => $enrollments->groupBy('status')->map->count(),
            'active' => $enrollments->where('status', 'Actif')->count(),
        ];
    }

    /**
     * Get students enrolled in a specific module
     */
    public function getStudentsInModule(int $moduleId, int $semesterId): Collection
    {
        return StudentModuleEnrollment::on('tenant')
            ->with(['student', 'studentEnrollment'])
            ->where('module_id', $moduleId)
            ->where('semester_id', $semesterId)
            ->whereIn('status', ['Inscrit', 'Validé', 'Non validé'])
            ->get()
            ->map(fn ($me) => $me->student);
    }

    /**
     * Check if student can enroll to a module (prerequisites check)
     */
    public function canEnrollToModule(Student $student, Module $module): array
    {
        // Check if already enrolled
        $alreadyEnrolled = StudentModuleEnrollment::on('tenant')
            ->where('student_id', $student->id)
            ->where('module_id', $module->id)
            ->whereIn('status', ['Inscrit'])
            ->exists();

        // Check if already validated
        $alreadyValidated = StudentModuleEnrollment::on('tenant')
            ->where('student_id', $student->id)
            ->where('module_id', $module->id)
            ->where('status', 'Validé')
            ->exists();

        $prerequisites = $module->prerequisites;
        $missingPrerequisites = [];

        foreach ($prerequisites as $prerequisite) {
            $validated = StudentModuleEnrollment::on('tenant')
                ->where('student_id', $student->id)
                ->where('module_id', $prerequisite->id)
                ->where('status', 'Validé')
                ->exists();

            if (! $validated) {
                $missingPrerequisites[] = [
                    'id' => $prerequisite->id,
                    'code' => $prerequisite->code,
                    'name' => $prerequisite->name,
                ];
            }
        }

        $canEnroll = empty($missingPrerequisites) && ! $alreadyEnrolled && ! $alreadyValidated;

        return [
            'can_enroll' => $canEnroll,
            'missing_prerequisites' => $missingPrerequisites,
            'already_enrolled' => $alreadyEnrolled,
            'already_validated' => $alreadyValidated,
        ];
    }
}
