<?php

namespace Modules\PortailParent\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Enrollment\Entities\Student;
use Modules\UsersGuard\Entities\TenantUser;

class ChildPolicy
{
    use HandlesAuthorization;

    public function view(TenantUser $user, Student $student): bool
    {
        if (! $user->hasRole('Parent')) {
            return false;
        }

        $parent = $user->parent;
        if (! $parent) {
            return false;
        }

        return $parent->students()
            ->where('students.id', $student->id)
            ->exists();
    }

    public function viewGrades(TenantUser $user, Student $student): bool
    {
        return $user->hasPermissionTo('view children grades')
            && $this->view($user, $student);
    }

    public function viewAttendance(TenantUser $user, Student $student): bool
    {
        return $user->hasPermissionTo('view children attendance')
            && $this->view($user, $student);
    }

    public function viewTimetable(TenantUser $user, Student $student): bool
    {
        return $user->hasPermissionTo('view children timetable')
            && $this->view($user, $student);
    }

    public function viewInvoices(TenantUser $user, Student $student): bool
    {
        return $user->hasPermissionTo('view children invoices')
            && $this->view($user, $student);
    }

    public function viewDocuments(TenantUser $user, Student $student): bool
    {
        return $user->hasPermissionTo('view children documents')
            && $this->view($user, $student);
    }

    public function payInvoices(TenantUser $user, Student $student): bool
    {
        if (! $user->hasPermissionTo('pay children invoices')) {
            return false;
        }

        return $user->parent
            ?->students()
            ->where('students.id', $student->id)
            ->wherePivot('is_financial_responsible', true)
            ->exists() === true;
    }
}
