<?php

namespace Modules\Enrollment\Services;

use Illuminate\Support\Facades\Schema;
use Modules\Enrollment\Entities\ClassEnrollment;
use Modules\Enrollment\Entities\Reenrollment;
use Modules\Enrollment\Entities\ReenrollmentCampaign;
use Modules\Enrollment\Entities\ReenrollmentIntention;
use Modules\Enrollment\Entities\Student;
use Modules\UsersGuard\Entities\TenantUser;
use Modules\UsersGuard\Entities\User;

/**
 * Campagnes de réinscription (lycée) : éligibilité, intentions des élèves et statistiques.
 *
 * La validation effective (inscription dans une classe de l'année suivante) est faite
 * par ClassPromotionService (passages de classe).
 */
class ReenrollmentService
{
    /**
     * Éligibilité d'un élève à une campagne : élève actif, inscrit dans une classe de l'année source.
     *
     * @return array{is_active: bool, has_previous_enrollment: bool, previous_enrollment: ClassEnrollment|null, no_disciplinary_exclusion: bool, is_eligible: bool}
     */
    public function checkEligibility(Student $student, ReenrollmentCampaign $campaign): array
    {
        $previousEnrollment = ClassEnrollment::query()
            ->with('classe:id,name,level_id')
            ->where('student_id', $student->id)
            ->where('academic_year_id', $campaign->from_academic_year_id)
            ->whereIn('status', ['Actif', 'Terminé'])
            ->first();

        $checks = [
            'is_active' => $student->status === 'Actif',
            'has_previous_enrollment' => $previousEnrollment !== null,
            'no_disciplinary_exclusion' => $student->status !== 'Exclu',
        ];

        return $checks + [
            'previous_enrollment' => $previousEnrollment,
            'is_eligible' => ! in_array(false, $checks, true),
        ];
    }

    /**
     * Élèves concernés par une campagne (inscrits dans une classe de l'année source).
     *
     * @return array{eligible: list<array<string, mixed>>, not_eligible: list<array<string, mixed>>, total_eligible: int, total_not_eligible: int}
     */
    public function getEligibleStudents(ReenrollmentCampaign $campaign): array
    {
        $students = Student::query()
            ->whereHas('classEnrollments', fn ($query) => $query
                ->where('academic_year_id', $campaign->from_academic_year_id)
                ->whereIn('status', ['Actif', 'Terminé']))
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get();

        $eligible = [];
        $notEligible = [];

        foreach ($students as $student) {
            $eligibility = $this->checkEligibility($student, $campaign);
            $row = ['student' => $student, 'eligibility' => $eligibility];

            if ($eligibility['is_eligible']) {
                $eligible[] = $row;
            } else {
                $notEligible[] = $row;
            }
        }

        return [
            'eligible' => $eligible,
            'not_eligible' => $notEligible,
            'total_eligible' => count($eligible),
            'total_not_eligible' => count($notEligible),
        ];
    }

    /**
     * La table des intentions existe-t-elle sur le tenant courant (migration jouée) ?
     */
    public function intentionsAvailable(): bool
    {
        return Schema::connection('tenant')->hasTable('reenrollment_intentions');
    }

    /**
     * Enregistre (ou met à jour) l'intention de réinscription d'un élève pour une campagne.
     */
    public function recordIntention(Student $student, ReenrollmentCampaign $campaign, bool $wantsToReenroll, ?string $comment): ReenrollmentIntention
    {
        return ReenrollmentIntention::query()->updateOrCreate(
            ['campaign_id' => $campaign->id, 'student_id' => $student->id],
            ['wants_to_reenroll' => $wantsToReenroll, 'comment' => $comment, 'responded_at' => now()],
        );
    }

    /**
     * Rejeter une demande de réinscription (historique).
     */
    public function rejectReenrollment(Reenrollment $reenrollment, User|TenantUser $validator, string $reason): Reenrollment
    {
        if (! $reenrollment->canBeRejected()) {
            throw new \Exception('Reenrollment cannot be rejected in current status');
        }

        $reenrollment->update([
            'status' => Reenrollment::STATUS_REJECTED,
            'validated_by' => $validator->id,
            'validated_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $reenrollment->fresh();
    }

    /**
     * Statistiques d'une campagne (demandes historiques + intentions des élèves).
     *
     * @return array<string, mixed>
     */
    public function getCampaignStatistics(ReenrollmentCampaign $campaign): array
    {
        $reenrollments = Reenrollment::on('tenant')->where('campaign_id', $campaign->id);

        $stats = [
            'total' => $reenrollments->count(),
            'by_status' => [
                'draft' => (clone $reenrollments)->where('status', 'Draft')->count(),
                'submitted' => (clone $reenrollments)->where('status', 'Submitted')->count(),
                'validated' => (clone $reenrollments)->where('status', 'Validated')->count(),
                'rejected' => (clone $reenrollments)->where('status', 'Rejected')->count(),
            ],
            'by_eligibility' => [
                'eligible' => (clone $reenrollments)->where('eligibility_status', 'Eligible')->count(),
                'not_eligible' => (clone $reenrollments)->where('eligibility_status', 'Not_Eligible')->count(),
                'pending' => (clone $reenrollments)->where('eligibility_status', 'Pending')->count(),
            ],
            'special_cases' => [
                'redoing' => (clone $reenrollments)->where('is_redoing', true)->count(),
                'reorientation' => (clone $reenrollments)->where('is_reorientation', true)->count(),
            ],
            'by_target_level' => Reenrollment::on('tenant')->where('campaign_id', $campaign->id)
                ->selectRaw('target_level, COUNT(*) as count')
                ->groupBy('target_level')
                ->pluck('count', 'target_level')
                ->toArray(),
            'intentions' => $this->intentionStatistics($campaign),
        ];

        $stats['validation_rate'] = $stats['total'] > 0
            ? round(($stats['by_status']['validated'] / $stats['total']) * 100, 2)
            : 0;

        return $stats;
    }

    /**
     * @return array{yes: int, no: int}
     */
    private function intentionStatistics(ReenrollmentCampaign $campaign): array
    {
        if (! $this->intentionsAvailable()) {
            return ['yes' => 0, 'no' => 0];
        }

        $counts = ReenrollmentIntention::query()
            ->where('campaign_id', $campaign->id)
            ->selectRaw('wants_to_reenroll, COUNT(*) as total')
            ->groupBy('wants_to_reenroll')
            ->pluck('total', 'wants_to_reenroll');

        return [
            'yes' => (int) ($counts[1] ?? 0),
            'no' => (int) ($counts[0] ?? 0),
        ];
    }
}
