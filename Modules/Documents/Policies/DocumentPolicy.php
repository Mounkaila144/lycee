<?php

namespace Modules\Documents\Policies;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Modules\Documents\Entities\Document;
use Modules\Enrollment\Entities\Student;

/**
 * Access rules for issued official documents (certificates, transcripts...).
 *
 * - Administrator / Manager : every document.
 * - Élève : only its own issued documents.
 * - Parent : only issued documents of its own children (permission `view children documents`).
 */
class DocumentPolicy
{
    public const STAFF_ROLES = ['Administrator', 'Manager'];

    public function download(Authenticatable $user, Document $document): bool
    {
        if ($this->isStaff($user)) {
            return true;
        }

        if (! $document->isIssued()) {
            return false;
        }

        $student = $document->student;

        return $student !== null && $this->canAccessStudent($user, $student);
    }

    public function viewStudentDocuments(Authenticatable $user, Student $student): bool
    {
        return $this->isStaff($user) || $this->canAccessStudent($user, $student);
    }

    private function canAccessStudent(Authenticatable $user, Student $student): bool
    {
        if ($user->hasRole('Élève') && (int) $student->user_id === (int) $user->getKey()) {
            return true;
        }

        if ($user->hasRole('Parent') && $user->checkPermissionTo('view children documents')) {
            return $user->parent?->students()
                ->where('students.id', $student->id)
                ->exists() === true;
        }

        return false;
    }

    private function isStaff(Authenticatable $user): bool
    {
        return method_exists($user, 'hasAnyRole') && $user->hasAnyRole(self::STAFF_ROLES);
    }
}
