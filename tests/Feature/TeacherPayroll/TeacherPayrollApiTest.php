<?php

namespace Tests\Feature\TeacherPayroll;

use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Subject;
use Modules\TeacherPayroll\Entities\ContratProfesseur;
use Modules\TeacherPayroll\Entities\HeureCoursProfesseur;
use Modules\TeacherPayroll\Entities\PaieProfesseur;
use Modules\TeacherPayroll\Entities\PeriodePaie;
use Modules\TeacherPayroll\Entities\Professeur;
use Modules\TeacherPayroll\Services\TeacherPayrollService;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class TeacherPayrollApiTest extends TestCase
{
    use InteractsWithTenancy;

    private TenantUser $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        $this->user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($this->user, 'Administrator');
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function authGetJson(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->getJson($uri);
    }

    private function authPostJson(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->postJson($uri, $data);
    }

    private function authGet(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->get($uri);
    }

    private function creerProfesseur(array $attrs = []): Professeur
    {
        return Professeur::on('tenant')->create(array_merge([
            'nom' => 'Diallo',
            'prenom' => 'Amadou',
            'statut_professeur' => 'CONTRACTUEL',
            'actif' => true,
        ], $attrs));
    }

    private function creerContrat(Professeur $prof, array $attrs = []): ContratProfesseur
    {
        return ContratProfesseur::on('tenant')->create(array_merge([
            'professeur_id' => $prof->id,
            'date_debut' => '2026-01-01',
            'mode_paiement' => 'MENSUEL',
            'salaire_base' => 120000,
            'statut' => 'ACTIF',
            'devise' => 'FCFA',
        ], $attrs));
    }

    private function creerPeriode(array $attrs = []): PeriodePaie
    {
        return PeriodePaie::on('tenant')->create(array_merge([
            'mois' => 1,
            'annee' => 2026,
            'date_debut' => '2026-01-01',
            'date_fin' => '2026-01-31',
            'statut' => 'OUVERTE',
            'est_vacances' => false,
        ], $attrs));
    }

    private function service(): TeacherPayrollService
    {
        return app(TeacherPayrollService::class);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->getJson('/api/admin/teacher-payroll/professeurs')->assertStatus(401);
    }

    #[Test]
    public function it_creates_a_professeur_via_api(): void
    {
        $response = $this->authPostJson('/api/admin/teacher-payroll/professeurs', [
            'nom' => 'Ousmane',
            'prenom' => 'Hassane',
            'statut_professeur' => 'VACATAIRE',
        ]);

        $response->assertStatus(201)->assertJsonPath('data.nom', 'Ousmane');
        $this->assertSame(1, Professeur::on('tenant')->count());
    }

    #[Test]
    public function it_rejects_an_invalid_statut(): void
    {
        $this->authPostJson('/api/admin/teacher-payroll/professeurs', [
            'nom' => 'X',
            'prenom' => 'Y',
            'statut_professeur' => 'INCONNU',
        ])->assertStatus(422);
    }

    #[Test]
    public function it_calculates_a_monthly_payroll_through_the_api(): void
    {
        $prof = $this->creerProfesseur();
        $this->creerContrat($prof, ['salaire_base' => 120000]);
        $periode = $this->creerPeriode();

        $this->authPostJson("/api/admin/teacher-payroll/periodes/{$periode->id}/calculer")
            ->assertOk()
            ->assertJsonPath('data.generated', 1);

        $paie = PaieProfesseur::on('tenant')->where('professeur_id', $prof->id)->first();
        $this->assertNotNull($paie);
        $this->assertSame('120000.00', $paie->net_a_payer);
        $this->assertSame('CALCULEE', $paie->statut);
    }

    #[Test]
    public function horaire_pays_only_validated_hours(): void
    {
        $prof = $this->creerProfesseur(['statut_professeur' => 'VACATAIRE']);
        $this->creerContrat($prof, ['mode_paiement' => 'HORAIRE', 'salaire_base' => 0, 'taux_horaire' => 2000]);
        $periode = $this->creerPeriode();

        // 20h validées, 12h prévues (non payées).
        HeureCoursProfesseur::on('tenant')->create([
            'professeur_id' => $prof->id, 'date_cours' => '2026-01-10',
            'nombre_heures' => 20, 'statut' => 'VALIDE',
        ]);
        HeureCoursProfesseur::on('tenant')->create([
            'professeur_id' => $prof->id, 'date_cours' => '2026-01-12',
            'nombre_heures' => 12, 'statut' => 'PREVU',
        ]);

        $paie = $this->service()->genererPaie($prof, $periode);

        $this->assertSame('20.00', $paie->nombre_heures_validees);
        $this->assertSame('40000.00', $paie->net_a_payer); // 20 * 2000
    }

    #[Test]
    public function replacement_hours_are_paid_to_the_substitute_not_the_titular(): void
    {
        $titulaire = $this->creerProfesseur(['nom' => 'Titulaire']);
        $remplacant = $this->creerProfesseur(['nom' => 'Remplacant']);
        $this->creerContrat($titulaire, ['mode_paiement' => 'HORAIRE', 'salaire_base' => 0, 'taux_horaire' => 2000]);
        $this->creerContrat($remplacant, ['mode_paiement' => 'HORAIRE', 'salaire_base' => 0, 'taux_horaire' => 2000]);
        $periode = $this->creerPeriode();

        // Heure validée, effectuée par le remplaçant.
        HeureCoursProfesseur::on('tenant')->create([
            'professeur_id' => $titulaire->id,
            'remplace_par_professeur_id' => $remplacant->id,
            'date_cours' => '2026-01-15',
            'nombre_heures' => 4, 'statut' => 'VALIDE',
        ]);

        $paieTitulaire = $this->service()->genererPaie($titulaire, $periode);
        $paieRemplacant = $this->service()->genererPaie($remplacant, $periode);

        $this->assertSame('0.00', $paieTitulaire->montant_heures);
        $this->assertSame('8000.00', $paieRemplacant->net_a_payer); // 4 * 2000
    }

    #[Test]
    public function changing_the_rate_after_calculation_does_not_alter_existing_paie(): void
    {
        $prof = $this->creerProfesseur();
        $contrat = $this->creerContrat($prof, ['mode_paiement' => 'HORAIRE', 'salaire_base' => 0, 'taux_horaire' => 2000]);
        $periode = $this->creerPeriode();

        HeureCoursProfesseur::on('tenant')->create([
            'professeur_id' => $prof->id, 'date_cours' => '2026-01-10',
            'nombre_heures' => 10, 'statut' => 'VALIDE',
        ]);

        $paie = $this->service()->genererPaie($prof, $periode);
        $this->service()->validerPaie($paie);

        // Le taux change après validation.
        $contrat->update(['taux_horaire' => 5000]);

        $paie->refresh();
        $this->assertSame('2000.00', $paie->taux_horaire); // figé
        $this->assertSame('20000.00', $paie->net_a_payer); // 10 * 2000, inchangé
    }

    #[Test]
    public function a_monthly_teacher_is_skipped_during_unpaid_vacation(): void
    {
        $prof = $this->creerProfesseur();
        $this->creerContrat($prof, ['mode_paiement' => 'MENSUEL', 'paiement_pendant_vacances' => false]);
        $periode = $this->creerPeriode(['est_vacances' => true]);

        $paie = $this->service()->genererPaie($prof, $periode);

        $this->assertNull($paie);
    }

    #[Test]
    public function a_monthly_teacher_paid_during_vacation_is_generated(): void
    {
        $prof = $this->creerProfesseur();
        $this->creerContrat($prof, ['mode_paiement' => 'MENSUEL', 'salaire_base' => 90000, 'paiement_pendant_vacances' => true]);
        $periode = $this->creerPeriode(['est_vacances' => true]);

        $paie = $this->service()->genererPaie($prof, $periode);

        $this->assertNotNull($paie);
        $this->assertSame('90000.00', $paie->net_a_payer);
    }

    #[Test]
    public function partial_then_full_payment_updates_the_status(): void
    {
        $prof = $this->creerProfesseur();
        $this->creerContrat($prof, ['salaire_base' => 100000]);
        $periode = $this->creerPeriode();

        $paie = $this->service()->genererPaie($prof, $periode);
        $this->service()->validerPaie($paie);

        // Paiement partiel.
        $this->authPostJson("/api/admin/teacher-payroll/paies/{$paie->id}/paiement", [
            'montant' => 40000, 'mode_reglement' => 'ESPECES',
        ])->assertOk();
        $paie->refresh();
        $this->assertSame('PARTIELLEMENT_PAYEE', $paie->statut);
        $this->assertSame('40000.00', $paie->montant_paye);

        // Solde.
        $this->authPostJson("/api/admin/teacher-payroll/paies/{$paie->id}/paiement", [
            'montant' => 60000, 'mode_reglement' => 'MOBILE_MONEY',
        ])->assertOk();
        $paie->refresh();
        $this->assertSame('PAYEE', $paie->statut);
        $this->assertSame('100000.00', $paie->montant_paye);
    }

    #[Test]
    public function it_lists_unlinked_professeur_users_and_imports_one(): void
    {
        $teacher = TenantUser::factory()->create(['application' => 'admin', 'firstname' => 'Awa', 'lastname' => 'Sow']);
        $this->assignRole($teacher, 'Professeur');

        // Candidat listé
        $this->authGetJson('/api/admin/teacher-payroll/professeurs/candidats-utilisateurs')
            ->assertOk()
            ->assertJsonFragment(['id' => $teacher->id]);

        // Import → fiche liée + infos reprises du compte
        $this->authPostJson('/api/admin/teacher-payroll/professeurs/importer', [
            'user_id' => $teacher->id, 'statut_professeur' => 'PERMANENT',
        ])->assertStatus(201)
            ->assertJsonPath('data.user_id', $teacher->id)
            ->assertJsonPath('data.nom', 'Sow')
            ->assertJsonPath('data.prenom', 'Awa');

        // Plus listé comme candidat
        $this->authGetJson('/api/admin/teacher-payroll/professeurs/candidats-utilisateurs')
            ->assertOk()
            ->assertJsonMissing(['id' => $teacher->id]);
    }

    #[Test]
    public function it_refuses_to_import_the_same_user_twice(): void
    {
        $teacher = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($teacher, 'Professeur');

        $this->authPostJson('/api/admin/teacher-payroll/professeurs/importer', ['user_id' => $teacher->id])->assertStatus(201);
        $this->authPostJson('/api/admin/teacher-payroll/professeurs/importer', ['user_id' => $teacher->id])->assertStatus(422);
    }

    #[Test]
    public function it_generates_a_payslip_pdf(): void
    {
        $prof = $this->creerProfesseur();
        $this->creerContrat($prof);
        $periode = $this->creerPeriode();
        $paie = $this->service()->genererPaie($prof, $periode);

        $response = $this->authGet("/api/admin/teacher-payroll/paies/{$paie->id}/fiche-pdf");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }
}
