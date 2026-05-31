<?php

namespace Tests\Unit\TeacherPayroll;

use Modules\TeacherPayroll\Services\TeacherPayrollCalculator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests purs des formules de calcul de la paie professeur (sans base de données).
 * Les exemples reprennent ceux du cahier des charges.
 */
class TeacherPayrollCalculatorTest extends TestCase
{
    #[Test]
    public function mode_mensuel_calcule_le_net_correctement(): void
    {
        $r = TeacherPayrollCalculator::calculer([
            'mode_paiement' => 'MENSUEL',
            'salaire_base' => 120000,
            'total_primes' => 10000,
            'total_retenues' => 5000,
            'total_avances' => 20000,
        ]);

        $this->assertSame(130000.0, $r['montant_brut']);
        $this->assertSame(105000.0, $r['net_a_payer']);
        $this->assertSame(0.0, $r['solde_reporte']);
    }

    #[Test]
    public function mode_horaire_paie_les_heures_validees(): void
    {
        $r = TeacherPayrollCalculator::calculer([
            'mode_paiement' => 'HORAIRE',
            'taux_horaire' => 2000,
            'nombre_heures_validees' => 32,
        ]);

        $this->assertSame(64000.0, $r['montant_heures']);
        $this->assertSame(64000.0, $r['net_a_payer']);
    }

    #[Test]
    public function mode_hybride_ajoute_les_heures_supplementaires(): void
    {
        $r = TeacherPayrollCalculator::calculer([
            'mode_paiement' => 'HYBRIDE',
            'salaire_base' => 100000,
            'nombre_heures_supplementaires' => 10,
            'taux_heure_supplementaire' => 2500,
            'total_primes' => 5000,
        ]);

        $this->assertSame(25000.0, $r['montant_heures_supplementaires']);
        $this->assertSame(130000.0, $r['net_a_payer']);
    }

    #[Test]
    public function mode_forfait_utilise_le_montant_convenu(): void
    {
        $r = TeacherPayrollCalculator::calculer([
            'mode_paiement' => 'FORFAIT',
            'montant_forfait' => 80000,
            'total_retenues' => 10000,
        ]);

        $this->assertSame(80000.0, $r['montant_brut']);
        $this->assertSame(70000.0, $r['net_a_payer']);
    }

    #[Test]
    public function le_net_ne_peut_pas_etre_negatif_le_solde_est_reporte(): void
    {
        $r = TeacherPayrollCalculator::calculer([
            'mode_paiement' => 'MENSUEL',
            'salaire_base' => 10000,
            'total_retenues' => 5000,
            'total_avances' => 20000,
        ]);

        $this->assertSame(0.0, $r['net_a_payer']);
        $this->assertSame(15000.0, $r['solde_reporte']);
    }

    #[Test]
    public function un_mode_de_paiement_invalide_leve_une_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TeacherPayrollCalculator::calculer(['mode_paiement' => 'INCONNU']);
    }
}
