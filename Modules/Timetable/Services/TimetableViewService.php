<?php

namespace Modules\Timetable\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Modules\Enrollment\Entities\ClassEnrollment;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\Semester;
use Modules\Timetable\Entities\TimetableSlot;

/**
 * Consultation des emplois du temps (lycée) : par classe, par enseignant, par élève.
 */
class TimetableViewService
{
    private ?bool $supportsClasses = null;

    /**
     * Semestre à utiliser : celui demandé, sinon le semestre actif (de l'année donnée si fournie),
     * sinon le semestre couvrant la date du jour.
     */
    public function resolveSemesterId(?int $semesterId = null, ?int $academicYearId = null): ?int
    {
        if ($semesterId) {
            return $semesterId;
        }

        $baseQuery = Semester::query()
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId));

        return (clone $baseQuery)->active()->orderByDesc('start_date')->value('id')
            ?? (clone $baseQuery)->current()->orderByDesc('start_date')->value('id');
    }

    /**
     * Inscription active (classe) de l'élève, en privilégiant l'année scolaire active.
     */
    public function activeClassEnrollment(Student $student): ?ClassEnrollment
    {
        return ClassEnrollment::query()
            ->where('student_id', $student->id)
            ->where('status', 'Actif')
            ->join('academic_years', 'academic_years.id', '=', 'class_enrollments.academic_year_id')
            ->whereNull('academic_years.deleted_at')
            ->orderByDesc('academic_years.is_active')
            ->orderByDesc('academic_years.start_date')
            ->select('class_enrollments.*')
            ->with('classe')
            ->first();
    }

    /**
     * @return Collection<int, TimetableSlot>
     */
    public function slotsForClasse(int $classeId, ?int $semesterId): Collection
    {
        if (! $this->supportsClasses()) {
            return collect();
        }

        return $this->orderedQuery()
            ->with(['subject', 'module', 'teacher', 'room', 'classe'])
            ->byClasse($classeId)
            ->when($semesterId, fn ($query, $id) => $query->bySemester($id))
            ->get();
    }

    /**
     * @return Collection<int, TimetableSlot>
     */
    public function slotsForTeacher(int $teacherId, ?int $semesterId): Collection
    {
        return $this->orderedQuery()
            ->with(['subject', 'module', 'classe', 'group', 'room'])
            ->byTeacher($teacherId)
            ->when($semesterId, fn ($query, $id) => $query->bySemester($id))
            ->get();
    }

    /**
     * Emploi du temps d'un élève (via sa classe active).
     *
     * @return array{classe: array{id: int, name: string}|null, semester_id: int|null, slots: Collection<int, TimetableSlot>}
     */
    public function forStudent(Student $student, ?int $semesterId = null): array
    {
        $enrollment = $this->activeClassEnrollment($student);

        if (! $enrollment) {
            return ['classe' => null, 'semester_id' => $semesterId, 'slots' => collect()];
        }

        $resolvedSemesterId = $this->resolveSemesterId($semesterId, $enrollment->academic_year_id);

        return [
            'classe' => $enrollment->classe ? ['id' => $enrollment->classe->id, 'name' => $enrollment->classe->name] : null,
            'semester_id' => $resolvedSemesterId,
            'slots' => $this->slotsForClasse($enrollment->classe_id, $resolvedSemesterId),
        ];
    }

    /**
     * Format « liste plate » utilisé par les portails Élève et Parent.
     *
     * @param  Collection<int, TimetableSlot>  $slots
     * @return array<int, array{id: int, day: string, day_of_week: string, start_time: string, end_time: string, subject: string|null, subject_code: string|null, teacher: string|null, classroom: string|null, classe: string|null, type: string}>
     */
    public function toPortalList(Collection $slots): array
    {
        return $slots->map(fn (TimetableSlot $slot) => [
            'id' => $slot->id,
            'day' => $slot->day_of_week,
            'day_of_week' => $slot->day_of_week,
            'start_time' => substr((string) $slot->start_time, 0, 5),
            'end_time' => substr((string) $slot->end_time, 0, 5),
            'subject' => $slot->subject?->name ?? $slot->module?->name,
            'subject_code' => $slot->subject?->code ?? $slot->module?->code,
            'teacher' => $slot->teacher?->name,
            'classroom' => $slot->room?->name ?? $slot->classe?->classroom,
            'classe' => $slot->classe?->name,
            'type' => $slot->type,
        ])->values()->all();
    }

    /**
     * Garde-fou tant que la migration « classe_id / subject_id » n'est pas appliquée sur un tenant.
     */
    private function supportsClasses(): bool
    {
        return $this->supportsClasses ??= Schema::connection('tenant')->hasColumn('timetable_slots', 'classe_id');
    }

    private function orderedQuery(): Builder
    {
        return TimetableSlot::query()
            ->orderByRaw("FIELD(day_of_week, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi')")
            ->orderBy('start_time');
    }
}
