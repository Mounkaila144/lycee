<?php

namespace Modules\Enrollment\Observers;

use Illuminate\Support\Facades\Auth;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentAuditLog;

class StudentObserver
{
    /**
     * Handle the Student "created" event.
     */
    public function created(Student $student): void
    {
        $this->logAudit($student, 'created');
    }

    /**
     * Handle the Student "updated" event.
     */
    public function updated(Student $student): void
    {
        $changes = $student->getChanges();
        $original = $student->getOriginal();

        // Exclude timestamps and irrelevant fields
        $excludeFields = ['updated_at', 'created_at'];

        foreach ($changes as $field => $newValue) {
            if (in_array($field, $excludeFields)) {
                continue;
            }

            $oldValue = $original[$field] ?? null;

            // Only log if value actually changed
            if ($oldValue !== $newValue) {
                $this->logAudit($student, 'updated', $field, $oldValue, $newValue);
            }
        }
    }

    /**
     * Handle the Student "deleted" event.
     */
    public function deleted(Student $student): void
    {
        $this->logAudit($student, 'deleted');
    }

    /**
     * Log audit event
     */
    private function logAudit(
        Student $student,
        string $event,
        ?string $fieldName = null,
        mixed $oldValue = null,
        mixed $newValue = null
    ): void {
        $request = request();

        // Try multiple ways to get authenticated user ID
        $userId = Auth::id() ?? $request?->user()?->id ?? null;

        StudentAuditLog::on('tenant')->create([
            'student_id' => $student->id,
            'user_id' => $userId,
            'event' => $event,
            'field_name' => $fieldName,
            'old_value' => $this->formatValue($oldValue),
            'new_value' => $this->formatValue($newValue),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    /**
     * Format value for storage
     */
    private function formatValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }
}
