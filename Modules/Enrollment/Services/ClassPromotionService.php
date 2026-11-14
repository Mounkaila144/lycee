<?php

namespace Modules\Enrollment\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Enrollment\Entities\ClassEnrollment;
use Modules\Enrollment\Entities\ReenrollmentCampaign;
use Modules\Enrollment\Entities\ReenrollmentIntention;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Exceptions\ClassPromotionException;
use Modules\NotesEvaluations\Services\Lycee\LyceeGradeCalculator;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Level;
use Modules\UsersGuard\Entities\TenantUser;
use Modules\UsersGuard\Entities\User;

/**
 * Passages de classe de fin d'année (lycée) : passage en classe supérieure, redoublement ou départ.
 *
 * Chaque élève d'une classe source reçoit une décision ; pour un passage ou un redoublement,
 * une inscription (ClassEnrollment) est créée dans une classe de l'année cible et l'inscription
 * source est clôturée (status « Terminé », final_decision renseignée).
 */
class ClassPromotionService
{
    public const DECISION_PASSAGE = 'passage';

    public const DECISION_REDOUBLEMENT = 'redoublement';

    public const DECISION_DEPART = 'depart';

    public const DECISIONS = [
        self::DECISION_PASSAGE,
        self::DECISION_REDOUBLEMENT,
        self::DECISION_DEPART,
    ];

    /**
     * Motifs de départ (valeurs de class_enrollments.final_decision).
     */
    public const DEPART_REASONS = ['Abandon', 'Transfert', 'Exclusion', 'Diplôme'];

    /**
     * Statut de l'élève (students.status) correspondant à chaque motif de départ.
     */
    public const DEPART_STUDENT_STATUSES = [
        'Abandon' => 'Abandon',
        'Transfert' => 'Transféré',
        'Exclusion' => 'Exclu',
        'Diplôme' => 'Diplômé',
    ];

    private const ASSIGNMENT_TYPES = [
        self::DECISION_PASSAGE => 'Passage',
        self::DECISION_REDOUBLEMENT => 'Redoublement',
    ];

    public function __construct(
        private LyceeGradeCalculator $calculator,
        private StudentStatusService $statusService,
    ) {}

    /**
     * Niveau immédiatement supérieur (ordre de la table des niveaux), null pour le dernier niveau.
     */
    public function nextLevel(Level $level): ?Level
    {
        return Level::query()
            ->where('order_index', '>', $level->order_index)
            ->orderBy('order_index')
            ->orderBy('id')
            ->first();
    }

    /**
     * Année scolaire qui suit l'année donnée (par date de début).
     */
    public function nextAcademicYear(AcademicYear $academicYear): ?AcademicYear
    {
        if ($academicYear->start_date === null) {
            return null;
        }

        return AcademicYear::query()
            ->where('start_date', '>', $academicYear->start_date)
            ->orderBy('start_date')
            ->orderBy('id')
            ->first();
    }

    /**
     * Prépare les passages d'une classe : élèves, moyenne annuelle, décision et classe cible proposées.
     *
     * @return array<string, mixed>
     */
    public function prepare(Classe $classe, ?AcademicYear $targetYear): array
    {
        $classe->loadMissing(['level', 'academicYear']);
        $targetYear ??= $classe->academicYear ? $this->nextAcademicYear($classe->academicYear) : null;
        $nextLevel = $classe->level ? $this->nextLevel($classe->level) : null;

        $enrollments = ClassEnrollment::query()
            ->with('student')
            ->where('classe_id', $classe->id)
            ->where('academic_year_id', $classe->academic_year_id)
            ->whereIn('status', ['Actif', 'Terminé'])
            ->get()
            ->filter(fn (ClassEnrollment $enrollment): bool => $enrollment->student !== null)
            ->sortBy(fn (ClassEnrollment $enrollment): string => mb_strtolower($enrollment->student->lastname.' '.$enrollment->student->firstname))
            ->values();

        $studentIds = $enrollments->pluck('student_id')->map(fn ($id): int => (int) $id)->all();
        $gradesAvailable = $this->calculator->isAvailable();
        $annual = $gradesAvailable
            ? $this->calculator->classAnnualResults($classe, true, $studentIds)
            : ['averages' => [], 'ranks' => []];

        $passageClasses = $targetYear && $nextLevel ? $this->targetClasses($targetYear, $nextLevel) : collect();
        $repeatClasses = $targetYear && $classe->level_id ? $this->targetClasses($targetYear, $classe->level) : collect();
        $intentions = $this->intentionsFor($classe->academic_year_id, $targetYear?->id, $studentIds);
        $targetEnrollments = $targetYear
            ? ClassEnrollment::query()
                ->with('classe:id,name')
                ->where('academic_year_id', $targetYear->id)
                ->whereIn('student_id', $studentIds)
                ->get()
                ->keyBy('student_id')
            : collect();

        $students = $enrollments->map(function (ClassEnrollment $enrollment) use ($annual, $intentions, $targetEnrollments, $nextLevel, $passageClasses, $repeatClasses, $classe): array {
            $average = $annual['averages'][$enrollment->student_id] ?? null;
            $intention = $intentions[$enrollment->student_id] ?? null;
            $decision = $this->proposedDecision($average, $intention, $nextLevel !== null);
            $targetEnrollment = $targetEnrollments->get($enrollment->student_id);

            return [
                'student' => [
                    'id' => $enrollment->student->id,
                    'matricule' => $enrollment->student->matricule,
                    'full_name' => $enrollment->student->full_name,
                ],
                'class_enrollment_id' => $enrollment->id,
                'enrollment_status' => $enrollment->status,
                'final_decision' => $enrollment->final_decision,
                'annual_average' => $average,
                'annual_rank' => $annual['ranks'][$enrollment->student_id] ?? null,
                'intention' => $intention,
                'proposed_decision' => $decision,
                'proposed_target_classe_id' => match ($decision) {
                    self::DECISION_PASSAGE => $this->suggestTargetClasse($classe, $passageClasses),
                    self::DECISION_REDOUBLEMENT => $this->suggestTargetClasse($classe, $repeatClasses),
                    default => null,
                },
                'target_enrollment' => $targetEnrollment ? [
                    'id' => $targetEnrollment->id,
                    'classe_id' => $targetEnrollment->classe_id,
                    'classe_name' => $targetEnrollment->classe?->name,
                    'assignment_type' => $targetEnrollment->assignment_type,
                ] : null,
            ];
        });

        return [
            'classe' => [
                'id' => $classe->id,
                'name' => $classe->name,
                'level' => $this->levelSummary($classe->level),
                'academic_year' => $this->yearSummary($classe->academicYear),
            ],
            'target_academic_year' => $this->yearSummary($targetYear),
            'next_level' => $this->levelSummary($nextLevel),
            'grades_available' => $gradesAvailable,
            'target_classes' => [
                self::DECISION_PASSAGE => $passageClasses->map(fn (Classe $target): array => $this->classeSummary($target))->values()->all(),
                self::DECISION_REDOUBLEMENT => $repeatClasses->map(fn (Classe $target): array => $this->classeSummary($target))->values()->all(),
            ],
            'students' => $students->all(),
        ];
    }

    /**
     * Valide en masse les décisions d'une classe. Chaque élève est traité indépendamment.
     *
     * @param  list<array{student_id: int, decision: string, target_classe_id?: int|null, depart_reason?: string|null}>  $items
     * @return array{processed: list<array<string, mixed>>, errors: list<array{student_id: int, message: string}>}
     *
     * @throws ClassPromotionException
     */
    public function validateBatch(Classe $source, AcademicYear $targetYear, array $items, User|TenantUser $validator): array
    {
        if ((int) $targetYear->id === (int) $source->academic_year_id) {
            throw new ClassPromotionException("L'année cible doit être différente de l'année de la classe source.");
        }

        $processed = [];
        $errors = [];

        foreach ($items as $item) {
            try {
                $processed[] = $this->validateReenrollment(
                    $source,
                    (int) $item['student_id'],
                    $item['decision'],
                    isset($item['target_classe_id']) ? (int) $item['target_classe_id'] : null,
                    $targetYear,
                    $validator,
                    $item['depart_reason'] ?? null,
                );
            } catch (ClassPromotionException $exception) {
                $errors[] = ['student_id' => (int) $item['student_id'], 'message' => $exception->getMessage()];
            }
        }

        return ['processed' => $processed, 'errors' => $errors];
    }

    /**
     * Valide la décision de fin d'année d'un élève de la classe source.
     *
     * @return array{student_id: int, decision: string, class_enrollment_id: int|null, target_classe_id: int|null}
     *
     * @throws ClassPromotionException
     */
    public function validateReenrollment(
        Classe $source,
        int $studentId,
        string $decision,
        ?int $targetClasseId,
        AcademicYear $targetYear,
        User|TenantUser $validator,
        ?string $departReason = null,
    ): array {
        if (! in_array($decision, self::DECISIONS, true)) {
            throw new ClassPromotionException("Décision inconnue : {$decision}.");
        }

        $sourceEnrollment = ClassEnrollment::query()
            ->where('classe_id', $source->id)
            ->where('academic_year_id', $source->academic_year_id)
            ->where('student_id', $studentId)
            ->whereIn('status', ['Actif', 'Terminé'])
            ->first();

        if (! $sourceEnrollment) {
            throw new ClassPromotionException("L'élève n'est pas inscrit dans la classe {$source->name}.");
        }

        $this->ensureNotAlreadyEnrolled($studentId, $targetYear);

        if ($decision === self::DECISION_DEPART) {
            $reason = in_array($departReason, self::DEPART_REASONS, true) ? $departReason : 'Abandon';

            DB::connection('tenant')->transaction(function () use ($sourceEnrollment, $reason, $source, $targetYear): void {
                $sourceEnrollment->update([
                    'status' => 'Terminé',
                    'final_decision' => $reason,
                    'final_decision_at' => now()->toDateString(),
                ]);

                $this->markStudentAsLeft($sourceEnrollment->student_id, $reason, $source, $targetYear);
            });

            return [
                'student_id' => $studentId,
                'decision' => $decision,
                'class_enrollment_id' => null,
                'target_classe_id' => null,
            ];
        }

        if ($targetClasseId === null) {
            throw new ClassPromotionException('La classe cible est obligatoire pour un passage ou un redoublement.');
        }

        return DB::connection('tenant')->transaction(function () use ($source, $sourceEnrollment, $studentId, $decision, $targetClasseId, $targetYear, $validator): array {
            $target = Classe::query()->with('level')->lockForUpdate()->find($targetClasseId);

            if (! $target) {
                throw new ClassPromotionException('La classe cible est introuvable.');
            }

            $this->ensureTargetClasseIsValid($source, $target, $decision, $targetYear);
            $this->ensureCapacity($target);

            $newEnrollment = $this->createTargetEnrollment($sourceEnrollment, $target, $decision, $validator);

            $sourceEnrollment->markCompleted(self::ASSIGNMENT_TYPES[$decision]);

            return [
                'student_id' => $studentId,
                'decision' => $decision,
                'class_enrollment_id' => $newEnrollment->id,
                'target_classe_id' => $target->id,
            ];
        });
    }

    /**
     * Départ : passe l'élève au statut de sortie correspondant au motif (journalisé dans
     * student_status_histories) et désactive son compte (et celui des parents sans autre enfant scolarisé).
     */
    private function markStudentAsLeft(int $studentId, string $reason, Classe $source, AcademicYear $targetYear): void
    {
        $student = Student::query()->find($studentId);

        if (! $student) {
            return;
        }

        $exitStatus = self::DEPART_STUDENT_STATUSES[$reason];

        if ($this->statusService->isTransitionAllowed((string) $student->status, $exitStatus)) {
            $this->statusService->changeStatus(
                $student,
                $exitStatus,
                "Passage de classe : départ ({$reason}) à l'issue de la classe {$source->name}, avant l'année {$targetYear->name}."
            );

            return;
        }

        $this->statusService->deactivateAccountsAfterDeparture($student);
    }

    /**
     * @throws ClassPromotionException
     */
    private function ensureNotAlreadyEnrolled(int $studentId, AcademicYear $targetYear): void
    {
        $existing = ClassEnrollment::query()
            ->with('classe:id,name')
            ->where('student_id', $studentId)
            ->where('academic_year_id', $targetYear->id)
            ->first();

        if ($existing) {
            $classeName = $existing->classe?->name ?? '#'.$existing->classe_id;

            throw new ClassPromotionException("L'élève est déjà inscrit en {$classeName} pour l'année {$targetYear->name}.");
        }
    }

    /**
     * @throws ClassPromotionException
     */
    private function ensureTargetClasseIsValid(Classe $source, Classe $target, string $decision, AcademicYear $targetYear): void
    {
        if ((int) $target->academic_year_id !== (int) $targetYear->id) {
            throw new ClassPromotionException("La classe {$target->name} n'appartient pas à l'année cible {$targetYear->name}.");
        }

        $source->loadMissing('level');

        if ($decision === self::DECISION_REDOUBLEMENT) {
            if ((int) $target->level_id !== (int) $source->level_id) {
                throw new ClassPromotionException("Un redoublement doit se faire dans une classe du même niveau que {$source->name}.");
            }

            return;
        }

        $nextLevel = $source->level ? $this->nextLevel($source->level) : null;

        if (! $nextLevel) {
            throw new ClassPromotionException("Aucun niveau supérieur après {$source->name} : choisissez « départ ».");
        }

        if ((int) $target->level_id !== (int) $nextLevel->id) {
            throw new ClassPromotionException("Un passage depuis {$source->name} doit se faire dans une classe de niveau {$nextLevel->name}.");
        }
    }

    /**
     * @throws ClassPromotionException
     */
    private function ensureCapacity(Classe $target): void
    {
        if (! $target->max_capacity) {
            return;
        }

        $activeCount = ClassEnrollment::query()
            ->where('classe_id', $target->id)
            ->where('status', 'Actif')
            ->count();

        if ($activeCount >= $target->max_capacity) {
            throw new ClassPromotionException("La classe {$target->name} est complète ({$activeCount}/{$target->max_capacity}).");
        }
    }

    private function createTargetEnrollment(ClassEnrollment $sourceEnrollment, Classe $target, string $decision, User|TenantUser $validator): ClassEnrollment
    {
        $attributes = [
            'classe_id' => $target->id,
            'previous_class_enrollment_id' => $sourceEnrollment->id,
            'assigned_by' => $validator->id,
            'enrolled_at' => now()->toDateString(),
            'assignment_type' => self::ASSIGNMENT_TYPES[$decision],
            'status' => 'Actif',
            'final_decision' => null,
            'final_decision_at' => null,
        ];

        /** Une inscription annulée (soft delete) occupe encore l'index unique élève/année : on la réutilise. */
        $trashed = ClassEnrollment::onlyTrashed()
            ->where('student_id', $sourceEnrollment->student_id)
            ->where('academic_year_id', $target->academic_year_id)
            ->first();

        if ($trashed) {
            $trashed->restore();
            $trashed->update($attributes);

            return $trashed;
        }

        return ClassEnrollment::query()->create($attributes + [
            'student_id' => $sourceEnrollment->student_id,
            'academic_year_id' => $target->academic_year_id,
        ]);
    }

    private function proposedDecision(?float $average, ?bool $intention, bool $hasNextLevel): ?string
    {
        if ($intention === false) {
            return self::DECISION_DEPART;
        }

        if ($average === null) {
            return null;
        }

        if ($average >= LyceeGradeCalculator::PASS_MARK) {
            return $hasNextLevel ? self::DECISION_PASSAGE : self::DECISION_DEPART;
        }

        return self::DECISION_REDOUBLEMENT;
    }

    /**
     * Classe cible suggérée : même série et même section si possible, sinon la seule classe disponible.
     *
     * @param  Collection<int, Classe>  $candidates
     */
    private function suggestTargetClasse(Classe $source, Collection $candidates): ?int
    {
        if ($candidates->count() === 1) {
            return $candidates->first()->id;
        }

        $sameSeries = $candidates->filter(fn (Classe $candidate): bool => (int) $candidate->series_id === (int) $source->series_id);
        $sameSection = $sameSeries->filter(fn (Classe $candidate): bool => $source->section !== null && $candidate->section === $source->section);

        if ($sameSection->count() === 1) {
            return $sameSection->first()->id;
        }

        return $sameSeries->count() === 1 ? $sameSeries->first()->id : null;
    }

    /**
     * @return Collection<int, Classe>
     */
    private function targetClasses(AcademicYear $targetYear, ?Level $level): Collection
    {
        if (! $level) {
            return collect();
        }

        return Classe::query()
            ->where('academic_year_id', $targetYear->id)
            ->where('level_id', $level->id)
            ->withCount(['enrollments as enrolled_count' => fn ($query) => $query->where('status', 'Actif')])
            ->orderBy('name')
            ->get();
    }

    /**
     * Intentions exprimées par les élèves dans la campagne la plus récente de l'année source.
     *
     * @param  list<int>  $studentIds
     * @return array<int, bool>
     */
    private function intentionsFor(int $fromAcademicYearId, ?int $toAcademicYearId, array $studentIds): array
    {
        if ($studentIds === [] || ! Schema::connection('tenant')->hasTable('reenrollment_intentions')) {
            return [];
        }

        $campaign = ReenrollmentCampaign::query()
            ->where('from_academic_year_id', $fromAcademicYearId)
            ->when($toAcademicYearId, fn ($query) => $query->where('to_academic_year_id', $toAcademicYearId))
            ->latest('start_date')
            ->latest('id')
            ->first();

        if (! $campaign) {
            return [];
        }

        return ReenrollmentIntention::query()
            ->where('campaign_id', $campaign->id)
            ->whereIn('student_id', $studentIds)
            ->pluck('wants_to_reenroll', 'student_id')
            ->map(fn ($wants): bool => (bool) $wants)
            ->all();
    }

    /**
     * @return array{id: int, name: string, level_id: int|null, series_id: int|null, max_capacity: int|null, enrolled_count: int}
     */
    private function classeSummary(Classe $classe): array
    {
        return [
            'id' => $classe->id,
            'name' => $classe->name,
            'level_id' => $classe->level_id,
            'series_id' => $classe->series_id,
            'max_capacity' => $classe->max_capacity,
            'enrolled_count' => (int) ($classe->enrolled_count ?? 0),
        ];
    }

    /**
     * @return array{id: int, code: string, name: string}|null
     */
    private function levelSummary(?Level $level): ?array
    {
        return $level ? ['id' => $level->id, 'code' => $level->code, 'name' => $level->name] : null;
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function yearSummary(?AcademicYear $year): ?array
    {
        return $year ? ['id' => $year->id, 'name' => $year->name] : null;
    }
}
