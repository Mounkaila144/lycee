<?php

namespace Modules\TeacherPayroll\Services;

use InvalidArgumentException;

/**
 * Calcul pur de la paie d'un professeur (sans accès base de données).
 *
 * Toutes les valeurs monétaires sont en unités de la devise du contrat.
 * Le net à payer ne peut jamais être négatif : tout dépassement est reporté
 * dans `solde_reporte`.
 */
class TeacherPayrollCalculator
{
    /**
     * @param array{
     *     mode_paiement: string,
     *     salaire_base?: float,
     *     taux_horaire?: float,
     *     taux_heure_supplementaire?: float,
     *     montant_forfait?: float,
     *     nombre_heures_validees?: float,
     *     nombre_heures_supplementaires?: float,
     *     total_primes?: float,
     *     total_retenues?: float,
     *     total_avances?: float,
     * } $input
     * @return array{
     *     mode_paiement: string,
     *     salaire_base: float,
     *     taux_horaire: float,
     *     taux_heure_supplementaire: float,
     *     nombre_heures_validees: float,
     *     nombre_heures_supplementaires: float,
     *     montant_heures: float,
     *     montant_heures_supplementaires: float,
     *     montant_forfait: float,
     *     total_primes: float,
     *     total_retenues: float,
     *     total_avances: float,
     *     montant_brut: float,
     *     net_a_payer: float,
     *     solde_reporte: float,
     * }
     */
    public static function calculer(array $input): array
    {
        $mode = $input['mode_paiement'] ?? null;

        if (! in_array($mode, ['MENSUEL', 'HORAIRE', 'HYBRIDE', 'FORFAIT'], true)) {
            throw new InvalidArgumentException("Mode de paiement invalide: {$mode}");
        }

        $salaireBase = (float) ($input['salaire_base'] ?? 0);
        $tauxHoraire = (float) ($input['taux_horaire'] ?? 0);
        $tauxHs = (float) ($input['taux_heure_supplementaire'] ?? 0);
        $forfait = (float) ($input['montant_forfait'] ?? 0);
        $heuresValidees = (float) ($input['nombre_heures_validees'] ?? 0);
        $heuresSupp = (float) ($input['nombre_heures_supplementaires'] ?? 0);
        $totalPrimes = (float) ($input['total_primes'] ?? 0);
        $totalRetenues = (float) ($input['total_retenues'] ?? 0);
        $totalAvances = (float) ($input['total_avances'] ?? 0);

        $montantHeures = 0.0;
        $montantHs = 0.0;

        switch ($mode) {
            case 'MENSUEL':
                $montantBrut = $salaireBase + $totalPrimes;
                break;

            case 'HORAIRE':
                $montantHeures = $heuresValidees * $tauxHoraire;
                $montantBrut = $montantHeures + $totalPrimes;
                break;

            case 'HYBRIDE':
                $montantHs = $heuresSupp * $tauxHs;
                $montantBrut = $salaireBase + $montantHs + $totalPrimes;
                break;

            case 'FORFAIT':
            default:
                $montantBrut = $forfait + $totalPrimes;
                break;
        }

        $netCalcule = $montantBrut - $totalRetenues - $totalAvances;

        // Le net ne peut pas être négatif : le solde négatif est reporté.
        $netAPayer = max(0.0, $netCalcule);
        $soldeReporte = $netCalcule < 0 ? abs($netCalcule) : 0.0;

        return [
            'mode_paiement' => $mode,
            'salaire_base' => round($salaireBase, 2),
            'taux_horaire' => round($tauxHoraire, 2),
            'taux_heure_supplementaire' => round($tauxHs, 2),
            'nombre_heures_validees' => round($heuresValidees, 2),
            'nombre_heures_supplementaires' => round($heuresSupp, 2),
            'montant_heures' => round($montantHeures, 2),
            'montant_heures_supplementaires' => round($montantHs, 2),
            'montant_forfait' => round($mode === 'FORFAIT' ? $forfait : 0, 2),
            'total_primes' => round($totalPrimes, 2),
            'total_retenues' => round($totalRetenues, 2),
            'total_avances' => round($totalAvances, 2),
            'montant_brut' => round($montantBrut, 2),
            'net_a_payer' => round($netAPayer, 2),
            'solde_reporte' => round($soldeReporte, 2),
        ];
    }
}
