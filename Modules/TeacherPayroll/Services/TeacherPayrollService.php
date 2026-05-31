<?php

namespace Modules\TeacherPayroll\Services;

use Modules\TeacherPayroll\Entities\AbsenceProfesseur;
use Modules\TeacherPayroll\Entities\AvanceProfesseur;
use Modules\TeacherPayroll\Entities\ContratProfesseur;
use Modules\TeacherPayroll\Entities\HeureCoursProfesseur;
use Modules\TeacherPayroll\Entities\PaieProfesseur;
use Modules\TeacherPayroll\Entities\PeriodePaie;
use Modules\TeacherPayroll\Entities\PrimeProfesseur;
use Modules\TeacherPayroll\Entities\Professeur;
use Modules\TeacherPayroll\Entities\RetenueProfesseur;

class TeacherPayrollService
{
    /**
     * Génère (ou recalcule) la paie de tous les professeurs ayant un contrat
     * actif pour la période donnée.
     *
     * @param  array<int>|null  $professeurIds  Restreindre à certains professeurs.
     * @return array{generated: int, skipped: int, paies: array<int>}
     */
    public function genererPaiesPourPeriode(PeriodePaie $periode, ?array $professeurIds = null, bool $forcer = false): array
    {
        $query = Professeur::on('tenant')->actif()->whereHas('contrats', function ($q) {
            $q->where('statut', 'ACTIF');
        });

        if ($professeurIds) {
            $query->whereIn('id', $professeurIds);
        }

        $generated = 0;
        $skipped = 0;
        $ids = [];

        foreach ($query->get() as $professeur) {
            $paie = $this->genererPaie($professeur, $periode, $forcer);

            if ($paie) {
                $generated++;
                $ids[] = $paie->id;
            } else {
                $skipped++;
            }
        }

        return ['generated' => $generated, 'skipped' => $skipped, 'paies' => $ids];
    }

    /**
     * Génère ou recalcule la paie d'un professeur pour une période.
     * Retourne null si aucune paie ne doit être générée (pas de contrat,
     * période de vacances non payée pour un contrat fixe, paie verrouillée).
     */
    public function genererPaie(Professeur $professeur, PeriodePaie $periode, bool $forcer = false): ?PaieProfesseur
    {
        $contrat = $professeur->getContratActifPour($periode->annee_scolaire_id);

        if (! $contrat) {
            return null;
        }

        // Paie déjà verrouillée : ne pas écraser sans action explicite.
        $existante = PaieProfesseur::on('tenant')
            ->where('professeur_id', $professeur->id)
            ->where('periode_paie_id', $periode->id)
            ->first();

        if ($existante && ! $existante->isModifiable() && ! $forcer) {
            return $existante;
        }

        // Règle vacances : un contrat fixe non payé pendant les vacances ne
        // génère pas de paie automatiquement (sauf mode horaire = heures réelles).
        if ($periode->est_vacances && ! $contrat->paiement_pendant_vacances && $contrat->mode_paiement !== 'HORAIRE' && ! $forcer) {
            return null;
        }

        $donnees = $this->collecterDonnees($professeur, $contrat, $periode);

        $calcul = TeacherPayrollCalculator::calculer($donnees['input']);

        $payload = array_merge($calcul, [
            'professeur_id' => $professeur->id,
            'contrat_professeur_id' => $contrat->id,
            'periode_paie_id' => $periode->id,
            'montant_paye' => $existante->montant_paye ?? 0,
            'statut' => 'CALCULEE',
            'date_calcul' => now(),
            'mode_reglement' => $existante->mode_reglement ?? null,
            'reference_paiement' => $existante->reference_paiement ?? null,
            'details_primes' => $donnees['details_primes'],
            'details_retenues' => $donnees['details_retenues'],
            'calcule_par' => auth('tenant')->id(),
        ]);

        $paie = PaieProfesseur::on('tenant')->updateOrCreate(
            ['professeur_id' => $professeur->id, 'periode_paie_id' => $periode->id],
            $payload
        );

        PayrollAuditLogger::log('CALCUL_PAIE', 'paie_professeur', $paie->id, null, [
            'net_a_payer' => $paie->net_a_payer,
            'mode' => $paie->mode_paiement,
        ]);

        return $paie;
    }

    /**
     * Rassemble heures, primes, retenues et avances pour le calcul.
     *
     * @return array{input: array<string, float|string>, details_primes: array, details_retenues: array}
     */
    private function collecterDonnees(Professeur $professeur, ContratProfesseur $contrat, PeriodePaie $periode): array
    {
        $heuresValidees = $this->heuresValideesPourProfesseur($professeur->id, $periode);

        // Heures supplémentaires (mode hybride) : au-delà du volume mensuel prévu.
        $heuresSupp = 0.0;
        if ($contrat->mode_paiement === 'HYBRIDE') {
            $volumePrevu = $contrat->volume_horaire_mensuel_prevu;
            $heuresSupp = $volumePrevu !== null
                ? max(0.0, $heuresValidees - (float) $volumePrevu)
                : $heuresValidees;
        }

        // Primes de la période.
        $primes = PrimeProfesseur::on('tenant')
            ->where('professeur_id', $professeur->id)
            ->where('periode_paie_id', $periode->id)
            ->get();
        $totalPrimes = (float) $primes->sum('montant');
        $detailsPrimes = $primes->map(fn ($p) => [
            'libelle' => $p->libelle,
            'type' => $p->type_prime,
            'montant' => (float) $p->montant,
        ])->all();

        // Retenues de la période + retenues issues des absences impactant la paie.
        $retenues = RetenueProfesseur::on('tenant')
            ->where('professeur_id', $professeur->id)
            ->where('periode_paie_id', $periode->id)
            ->get();
        $detailsRetenues = $retenues->map(fn ($r) => [
            'libelle' => $r->libelle,
            'type' => $r->type_retenue,
            'montant' => (float) $r->montant,
        ])->all();
        $totalRetenues = (float) $retenues->sum('montant');

        $absences = AbsenceProfesseur::on('tenant')
            ->where('professeur_id', $professeur->id)
            ->where('impact_sur_paie', true)
            ->whereBetween('date_absence', [$periode->date_debut, $periode->date_fin])
            ->get();
        foreach ($absences as $absence) {
            $montant = (float) $absence->montant_retenue;
            if ($montant <= 0) {
                continue;
            }
            $totalRetenues += $montant;
            $detailsRetenues[] = [
                'libelle' => 'Absence du '.$absence->date_absence?->format('d/m/Y'),
                'type' => 'ABSENCE',
                'montant' => $montant,
            ];
        }

        // Brut provisoire pour plafonner les avances (le net ne peut être négatif).
        $brutProvisoire = $this->brutProvisoire($contrat, $heuresValidees, $heuresSupp, $totalPrimes);
        $disponiblePourAvances = max(0.0, $brutProvisoire - $totalRetenues);

        $avances = AvanceProfesseur::on('tenant')
            ->where('professeur_id', $professeur->id)
            ->where('statut', 'APPROUVEE')
            ->where('periode_deduction_id', $periode->id)
            ->get();
        $totalResteAvances = (float) $avances->sum(fn ($a) => $a->reste_a_deduire);
        $totalAvances = min($totalResteAvances, $disponiblePourAvances);

        return [
            'input' => [
                'mode_paiement' => $contrat->mode_paiement,
                'salaire_base' => (float) $contrat->salaire_base,
                'taux_horaire' => (float) $contrat->taux_horaire,
                'taux_heure_supplementaire' => (float) $contrat->taux_heure_supplementaire,
                'montant_forfait' => (float) $contrat->montant_forfait,
                'nombre_heures_validees' => $heuresValidees,
                'nombre_heures_supplementaires' => $heuresSupp,
                'total_primes' => $totalPrimes,
                'total_retenues' => $totalRetenues,
                'total_avances' => $totalAvances,
            ],
            'details_primes' => $detailsPrimes,
            'details_retenues' => $detailsRetenues,
        ];
    }

    /**
     * Total des heures VALIDE imputables au professeur sur la période.
     * Inclut les heures effectuées en remplacement (remplace_par = professeur).
     * Exclut les heures du professeur titulaire qui ont été remplacées.
     */
    public function heuresValideesPourProfesseur(int $professeurId, PeriodePaie $periode): float
    {
        return (float) HeureCoursProfesseur::on('tenant')
            ->where('statut', 'VALIDE')
            ->whereBetween('date_cours', [$periode->date_debut, $periode->date_fin])
            ->where(function ($q) use ($professeurId) {
                $q->where(function ($sub) use ($professeurId) {
                    $sub->whereNull('remplace_par_professeur_id')
                        ->where('professeur_id', $professeurId);
                })->orWhere('remplace_par_professeur_id', $professeurId);
            })
            ->sum('nombre_heures');
    }

    private function brutProvisoire(ContratProfesseur $contrat, float $heuresValidees, float $heuresSupp, float $totalPrimes): float
    {
        return match ($contrat->mode_paiement) {
            'MENSUEL' => (float) $contrat->salaire_base + $totalPrimes,
            'HORAIRE' => $heuresValidees * (float) $contrat->taux_horaire + $totalPrimes,
            'HYBRIDE' => (float) $contrat->salaire_base + $heuresSupp * (float) $contrat->taux_heure_supplementaire + $totalPrimes,
            'FORFAIT' => (float) $contrat->montant_forfait + $totalPrimes,
            default => 0.0,
        };
    }

    /**
     * Valide une paie : verrouille le calcul et marque les avances déduites.
     */
    public function validerPaie(PaieProfesseur $paie): PaieProfesseur
    {
        if ($paie->statut !== 'CALCULEE') {
            return $paie;
        }

        // Marque les avances assignées à la période comme déduites, à hauteur
        // du montant réellement appliqué (déduction partielle possible).
        $this->appliquerAvances($paie);

        $paie->update([
            'statut' => 'VALIDEE',
            'date_validation' => now(),
            'valide_par' => auth('tenant')->id(),
        ]);

        PayrollAuditLogger::log('VALIDATION_PAIE', 'paie_professeur', $paie->id, null, [
            'net_a_payer' => $paie->net_a_payer,
        ]);

        return $paie->refresh();
    }

    private function appliquerAvances(PaieProfesseur $paie): void
    {
        $restant = (float) $paie->total_avances;

        if ($restant <= 0) {
            return;
        }

        $avances = AvanceProfesseur::on('tenant')
            ->where('professeur_id', $paie->professeur_id)
            ->where('statut', 'APPROUVEE')
            ->where('periode_deduction_id', $paie->periode_paie_id)
            ->orderBy('date_avance')
            ->get();

        foreach ($avances as $avance) {
            if ($restant <= 0) {
                break;
            }

            $aDeduire = min($avance->reste_a_deduire, $restant);
            $nouveauDeduit = (float) $avance->montant_deduit + $aDeduire;
            $restant -= $aDeduire;

            $avance->update([
                'montant_deduit' => $nouveauDeduit,
                'statut' => $nouveauDeduit >= (float) $avance->montant ? 'DEDUITE' : 'APPROUVEE',
            ]);
        }
    }

    /**
     * Enregistre un règlement (total ou partiel) sur une paie validée.
     */
    public function enregistrerPaiement(PaieProfesseur $paie, float $montant, array $attributs = []): PaieProfesseur
    {
        $paie->paiements()->create([
            'montant' => $montant,
            'date_paiement' => $attributs['date_paiement'] ?? now()->toDateString(),
            'mode_reglement' => $attributs['mode_reglement'] ?? 'ESPECES',
            'reference' => $attributs['reference'] ?? null,
            'note' => $attributs['note'] ?? null,
            'enregistre_par' => auth('tenant')->id(),
        ]);

        $totalPaye = (float) $paie->paiements()->sum('montant');
        $net = (float) $paie->net_a_payer;

        $statut = $totalPaye >= $net && $net > 0
            ? 'PAYEE'
            : ($totalPaye > 0 ? 'PARTIELLEMENT_PAYEE' : $paie->statut);

        $paie->update([
            'montant_paye' => $totalPaye,
            'statut' => $statut,
            'date_paiement' => $statut === 'PAYEE' ? now() : $paie->date_paiement,
            'mode_reglement' => $attributs['mode_reglement'] ?? $paie->mode_reglement,
            'reference_paiement' => $attributs['reference'] ?? $paie->reference_paiement,
        ]);

        PayrollAuditLogger::log('PAIEMENT_EFFECTUE', 'paie_professeur', $paie->id, null, [
            'montant' => $montant,
            'total_paye' => $totalPaye,
            'statut' => $statut,
        ]);

        return $paie->refresh();
    }

    /**
     * Valide en lot toutes les paies calculées d'une période.
     */
    public function validerPeriode(PeriodePaie $periode): int
    {
        $paies = PaieProfesseur::on('tenant')
            ->where('periode_paie_id', $periode->id)
            ->where('statut', 'CALCULEE')
            ->get();

        foreach ($paies as $paie) {
            $this->validerPaie($paie);
        }

        $periode->update(['statut' => 'VALIDEE']);

        return $paies->count();
    }
}
