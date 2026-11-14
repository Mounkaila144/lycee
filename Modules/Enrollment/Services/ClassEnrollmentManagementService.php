<?php

namespace Modules\Enrollment\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Enrollment\Entities\ClassEnrollment;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentAuditLog;
use Modules\StructureAcademique\Entities\Classe;
use Modules\UsersGuard\Entities\TenantUser;
use Modules\UsersGuard\Entities\User;

/**
 * Inscription d'un élève dans une classe et changement de classe en cours d'année.
 *
 * Règles : l'année de l'inscription est celle de la classe ; un élève a au plus une
 * inscription par année scolaire (index unique élève/année) ; la capacité de la classe
 * (max_capacity, élèves « Actif ») est respectée.
 */
class ClassEnrollmentManagementService
{
    public const INTERNAL_TRANSFER_LABEL = 'Transfert interne';

    /**
     * Inscrit l'élève dans la classe (année = année de la classe).
     *
     * @throws ValidationException
     */
    public function enroll(Student $student, int $classeId, User|TenantUser $assignedBy, ?string $assignmentType = null, ?string $enrolledAt = null, ?string $notes = null): ClassEnrollment
    {
        return DB::connection('tenant')->transaction(function () use ($student, $classeId, $assignedBy, $assignmentType, $enrolledAt, $notes): ClassEnrollment {
            $classe = Classe::query()->with('academicYear')->lockForUpdate()->findOrFail($classeId);

            $existing = ClassEnrollment::withTrashed()
                ->with('classe:id,name')
                ->where('student_id', $student->id)
                ->where('academic_year_id', $classe->academic_year_id)
                ->lockForUpdate()
                ->first();

            if ($existing && ! $existing->trashed() && $existing->status === 'Actif') {
                $currentName = $existing->classe?->name ?? '#'.$existing->classe_id;

                throw ValidationException::withMessages([
                    'classe_id' => ["L'élève est déjà inscrit en {$currentName} pour l'année {$classe->academicYear?->name}. Utilisez « Changer de classe »."],
                ]);
            }

            $this->ensureCapacity($classe);

            $attributes = [
                'classe_id' => $classe->id,
                'previous_class_enrollment_id' => null,
                'assigned_by' => $assignedBy->id,
                'enrolled_at' => $enrolledAt ?? now()->toDateString(),
                'assignment_type' => $assignmentType ?? 'Nouvelle inscription',
                'status' => 'Actif',
                'final_decision' => null,
                'final_decision_at' => null,
                'notes' => $notes,
            ];

            /** Une inscription annulée ou terminée de la même année occupe l'index unique élève/année : on la réutilise. */
            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }

                $existing->update($attributes);
                $enrollment = $existing;
            } else {
                $enrollment = ClassEnrollment::query()->create($attributes + [
                    'student_id' => $student->id,
                    'academic_year_id' => $classe->academic_year_id,
                ]);
            }

            $this->audit($student, $assignedBy, 'class_enrolled', null, $classe->name);

            return $enrollment->load(['classe:id,name,max_capacity', 'academicYear:id,name,is_active']);
        });
    }

    /**
     * Change l'élève de classe dans la même année scolaire (transfert interne).
     *
     * L'index unique élève/année impose une seule ligne par année : l'inscription active est
     * déplacée vers la nouvelle classe et l'ancienne classe est tracée (notes + journal d'audit).
     *
     * @throws ValidationException
     */
    public function changeClass(Student $student, int $targetClasseId, User|TenantUser $assignedBy, ?string $reason = null): ClassEnrollment
    {
        return DB::connection('tenant')->transaction(function () use ($student, $targetClasseId, $assignedBy, $reason): ClassEnrollment {
            $target = Classe::query()->with('academicYear')->lockForUpdate()->findOrFail($targetClasseId);

            $current = ClassEnrollment::query()
                ->with('classe:id,name,level_id')
                ->where('student_id', $student->id)
                ->where('academic_year_id', $target->academic_year_id)
                ->where('status', 'Actif')
                ->lockForUpdate()
                ->first();

            if (! $current) {
                throw ValidationException::withMessages([
                    'classe_id' => ["L'élève n'a pas d'inscription active pour l'année {$target->academicYear?->name}. Utilisez « Inscrire en classe »."],
                ]);
            }

            if ((int) $current->classe_id === (int) $target->id) {
                throw ValidationException::withMessages([
                    'classe_id' => ["L'élève est déjà inscrit en {$target->name}."],
                ]);
            }

            $this->ensureCapacity($target);

            $previousName = $current->classe?->name ?? '#'.$current->classe_id;
            $trace = sprintf(
                '%s le %s : %s → %s%s',
                self::INTERNAL_TRANSFER_LABEL,
                now()->format('d/m/Y'),
                $previousName,
                $target->name,
                $reason ? " ({$reason})" : ''
            );

            $current->update([
                'classe_id' => $target->id,
                'assigned_by' => $assignedBy->id,
                'notes' => trim(($current->notes ? $current->notes."\n" : '').$trace),
            ]);

            $this->audit($student, $assignedBy, 'class_changed', $previousName, $target->name);

            return $current->load(['classe:id,name,max_capacity', 'academicYear:id,name,is_active']);
        });
    }

    /**
     * Annule une inscription active (erreur de saisie, départ avant la rentrée…).
     *
     * @throws ValidationException
     */
    public function cancel(ClassEnrollment $enrollment, User|TenantUser $cancelledBy, ?string $reason = null): ClassEnrollment
    {
        if ($enrollment->status !== 'Actif') {
            throw ValidationException::withMessages([
                'status' => ['Seule une inscription active peut être annulée.'],
            ]);
        }

        $enrollment->loadMissing('classe:id,name', 'student');

        $enrollment->update([
            'status' => 'Annulé',
            'notes' => trim(($enrollment->notes ? $enrollment->notes."\n" : '').'Annulée le '.now()->format('d/m/Y').($reason ? " ({$reason})" : '')),
        ]);

        if ($enrollment->student) {
            $this->audit($enrollment->student, $cancelledBy, 'class_enrollment_cancelled', $enrollment->classe?->name, null);
        }

        return $enrollment;
    }

    /**
     * @throws ValidationException
     */
    private function ensureCapacity(Classe $classe): void
    {
        if (! $classe->max_capacity) {
            return;
        }

        $activeCount = ClassEnrollment::query()
            ->where('classe_id', $classe->id)
            ->where('status', 'Actif')
            ->count();

        if ($activeCount >= $classe->max_capacity) {
            throw ValidationException::withMessages([
                'classe_id' => ["La classe {$classe->name} est complète ({$activeCount}/{$classe->max_capacity})."],
            ]);
        }
    }

    private function audit(Student $student, User|TenantUser $user, string $event, ?string $oldValue, ?string $newValue): void
    {
        StudentAuditLog::query()->create([
            'student_id' => $student->id,
            'user_id' => $user->id,
            'event' => $event,
            'field_name' => 'classe',
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
