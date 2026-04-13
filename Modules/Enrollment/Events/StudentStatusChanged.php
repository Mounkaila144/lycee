<?php

namespace Modules\Enrollment\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentStatusHistory;

class StudentStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The student whose status changed.
     */
    public Student $student;

    /**
     * The previous status.
     */
    public string $oldStatus;

    /**
     * The new status.
     */
    public string $newStatus;

    /**
     * The status history record.
     */
    public StudentStatusHistory $history;

    /**
     * Create a new event instance.
     */
    public function __construct(
        Student $student,
        string $oldStatus,
        string $newStatus,
        StudentStatusHistory $history
    ) {
        $this->student = $student;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->history = $history;
    }

    /**
     * Check if this is a suspension.
     */
    public function isSuspension(): bool
    {
        return $this->newStatus === 'Suspendu';
    }

    /**
     * Check if this is an exclusion.
     */
    public function isExclusion(): bool
    {
        return $this->newStatus === 'Exclu';
    }

    /**
     * Check if this is a graduation.
     */
    public function isGraduation(): bool
    {
        return $this->newStatus === 'Diplômé';
    }

    /**
     * Check if this is a reactivation.
     */
    public function isReactivation(): bool
    {
        return $this->oldStatus === 'Suspendu' && $this->newStatus === 'Actif';
    }

    /**
     * Check if this is an abandonment.
     */
    public function isAbandonment(): bool
    {
        return $this->newStatus === 'Abandon';
    }

    /**
     * Check if this is a transfer.
     */
    public function isTransfer(): bool
    {
        return $this->newStatus === 'Transféré';
    }

    /**
     * Get a human-readable description of the transition.
     */
    public function getTransitionDescription(): string
    {
        return match ($this->newStatus) {
            'Suspendu' => "L'étudiant {$this->student->full_name} a été suspendu",
            'Exclu' => "L'étudiant {$this->student->full_name} a été exclu",
            'Diplômé' => "L'étudiant {$this->student->full_name} a obtenu son diplôme",
            'Abandon' => "L'étudiant {$this->student->full_name} a abandonné sa formation",
            'Transféré' => "L'étudiant {$this->student->full_name} a été transféré",
            'Actif' => "L'étudiant {$this->student->full_name} a été réactivé",
            default => "Le statut de l'étudiant {$this->student->full_name} a changé de {$this->oldStatus} à {$this->newStatus}",
        };
    }
}
