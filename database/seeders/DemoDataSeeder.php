<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Modules\Enrollment\Entities\Student;
use Modules\Finance\Entities\ClassFee;
use Modules\Finance\Entities\Expense;
use Modules\Finance\Entities\ExpenseCategory;
use Modules\Finance\Entities\FeeType;
use Modules\Finance\Entities\Payment;
use Modules\Finance\Services\BillingService;
use Modules\NotesEvaluations\Entities\Evaluation;
use Modules\NotesEvaluations\Entities\Grade;
use Modules\StructureAcademique\Database\Seeders\CycleAndLevelSeeder;
use Modules\StructureAcademique\Database\Seeders\SeriesSeeder;
use Modules\StructureAcademique\Database\Seeders\SubjectCoefficientSeeder;
use Modules\StructureAcademique\Database\Seeders\SubjectSeeder;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Level;
use Modules\StructureAcademique\Entities\Semester;
use Modules\StructureAcademique\Entities\Subject;
use Modules\TeacherPayroll\Entities\ContratProfesseur;
use Modules\TeacherPayroll\Entities\Professeur;
use Modules\UsersGuard\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\UsersGuard\Entities\TenantUser;

/**
 * Remplit un tenant avec un jeu de données de démonstration secondaire complet :
 * structure (cycles/niveaux/matières/coefficients), classes, professeurs,
 * élèves, emploi du temps, évaluations + notes, présences, facturation +
 * paiements, dépenses. Conçu pour être ré-exécutable sans créer de doublons.
 *
 * Lancement : php artisan tenants:seed "Database\Seeders\DemoDataSeeder" --tenants=company1
 */
class DemoDataSeeder extends Seeder
{
    private const LEVELS = ['6E', '5E', '4E', '3E'];

    private const SECTIONS = ['A', 'B'];

    private const STUDENTS_PER_CLASS = 12;

    private const GRADE_SUBJECTS = ['MATH', 'FRAN', 'ANG', 'SVT', 'HG'];

    public function run(): void
    {
        // ⚠️ Le tenant courant DOIT être restauré après tout sous-seeder qui
        // change de contexte. RolesAndPermissionsSeeder boucle sur TOUS les
        // tenants (tenancy()->initialize) et laisse le contexte sur le dernier.
        $current = function_exists('tenant') ? tenant() : null;

        $this->command?->info('▶ Données de référence (rôles, cycles, niveaux, matières, coefficients)…');

        if (\Spatie\Permission\Models\Role::where('name', 'Professeur')->where('guard_name', 'tenant')->doesntExist()) {
            $this->call(RolesAndPermissionsSeeder::class);
            $this->restoreTenant($current);
        }

        $this->call([
            CycleAndLevelSeeder::class,
            SeriesSeeder::class,
            SubjectSeeder::class,
        ]);
        $this->restoreTenant($current);

        $year = $this->academicYear();
        [$s1] = $this->semesters($year);

        // Coefficients matière/classe (dépend des niveaux + matières).
        try {
            $this->call(SubjectCoefficientSeeder::class);
            $this->restoreTenant($current);
        } catch (\Throwable $e) {
            $this->command?->warn('  coefficients ignorés: '.$e->getMessage());
        }

        $this->command?->info('▶ Classes…');
        $classes = $this->classes($year);

        $this->command?->info('▶ Professeurs + contrats…');
        $teachers = $this->teachers($year);

        $userId = $teachers[0]->id ?? TenantUser::query()->value('id');

        $this->command?->info('▶ Élèves…');
        $this->students($classes);

        $this->command?->info('▶ Emploi du temps…');
        $this->timetable($classes, $teachers, $s1);

        $this->command?->info('▶ Évaluations + notes…');
        $this->evaluationsAndGrades($classes, $s1, $userId);

        // Présences non générées : attendance_records.student_id pointe vers
        // `users`, or les élèves de démo n'ont pas de compte utilisateur.

        $this->command?->info('▶ Facturation + paiements…');
        $this->billing($classes, $year);

        $this->command?->info('▶ Dépenses…');
        $this->expenses();

        $this->command?->info('✅ Données de démonstration générées.');
    }

    /**
     * Restaure le contexte tenant après un sous-seeder qui aurait pu en changer.
     */
    private function restoreTenant($current): void
    {
        if ($current && function_exists('tenancy')) {
            tenancy()->initialize($current);
        }
    }

    private function academicYear(): AcademicYear
    {
        $year = AcademicYear::on('tenant')->where('is_active', true)->first();

        if (! $year) {
            $year = AcademicYear::on('tenant')->firstOrCreate(
                ['name' => '2026-2027'],
                ['start_date' => '2026-09-15', 'end_date' => '2027-06-30', 'is_active' => true]
            );
        }

        return $year;
    }

    /**
     * @return array<int, Semester>
     */
    private function semesters(AcademicYear $year): array
    {
        $s1 = Semester::on('tenant')->firstOrCreate(
            ['academic_year_id' => $year->id, 'name' => '1er Semestre'],
            ['start_date' => $year->start_date, 'end_date' => Carbon::parse($year->start_date)->addMonths(4), 'is_active' => true]
        );
        $s2 = Semester::on('tenant')->firstOrCreate(
            ['academic_year_id' => $year->id, 'name' => '2e Semestre'],
            ['start_date' => Carbon::parse($year->start_date)->addMonths(4)->addDay(), 'end_date' => $year->end_date, 'is_active' => false]
        );

        return [$s1, $s2];
    }

    /**
     * @return array<int, Classe>
     */
    private function classes(AcademicYear $year): array
    {
        $classes = [];
        foreach (self::LEVELS as $code) {
            $level = Level::on('tenant')->where('code', $code)->first();
            if (! $level) {
                continue;
            }
            foreach (self::SECTIONS as $section) {
                $classes[] = Classe::on('tenant')->firstOrCreate(
                    ['academic_year_id' => $year->id, 'level_id' => $level->id, 'series_id' => null, 'section' => $section],
                    ['max_capacity' => 60]
                );
            }
        }

        return $classes;
    }

    /**
     * @return array<int, TenantUser>
     */
    private function teachers(AcademicYear $year): array
    {
        $defs = [
            ['Ibrahim', 'Souley', 'MENSUEL'],
            ['Aïcha', 'Hamidou', 'MENSUEL'],
            ['Moussa', 'Abdou', 'HORAIRE'],
            ['Fatima', 'Issoufou', 'HYBRIDE'],
            ['Oumarou', 'Garba', 'HORAIRE'],
            ['Zeinabou', 'Maïga', 'FORFAIT'],
        ];

        // Le rôle vient d'être créé dans ce même process : on récupère le modèle
        // et on l'assigne par INSTANCE pour contourner le cache Spatie (findByName).
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $profRole = \Spatie\Permission\Models\Role::where('name', 'Professeur')->where('guard_name', 'tenant')->first();

        $teachers = [];
        foreach ($defs as $i => [$prenom, $nom, $mode]) {
            $username = 'prof_demo_'.($i + 1);
            $user = TenantUser::where('username', $username)->first();
            if (! $user) {
                $user = TenantUser::factory()->create([
                    'username' => $username,
                    'firstname' => $prenom,
                    'lastname' => $nom,
                    'email' => $username.'@demo.lycee',
                    'application' => 'admin',
                    'is_active' => true,
                ]);
            }
            if ($profRole && ! $user->roles->contains('id', $profRole->id)) {
                $user->assignRole($profRole);
            }

            $prof = Professeur::on('tenant')->firstOrCreate(
                ['user_id' => $user->id],
                ['nom' => $nom, 'prenom' => $prenom, 'email' => $user->email, 'statut_professeur' => 'PERMANENT', 'actif' => true]
            );

            if (! $prof->contrats()->where('statut', 'ACTIF')->exists()) {
                ContratProfesseur::on('tenant')->create([
                    'professeur_id' => $prof->id,
                    'annee_scolaire_id' => $year->id,
                    'date_debut' => $year->start_date,
                    'mode_paiement' => $mode,
                    'salaire_base' => in_array($mode, ['MENSUEL', 'HYBRIDE'], true) ? 150000 : 0,
                    'taux_horaire' => $mode === 'HORAIRE' ? 2500 : 0,
                    'taux_heure_supplementaire' => $mode === 'HYBRIDE' ? 3000 : 0,
                    'montant_forfait' => $mode === 'FORFAIT' ? 200000 : 0,
                    'volume_horaire_mensuel_prevu' => $mode === 'HYBRIDE' ? 20 : null,
                    'statut' => 'ACTIF',
                    'devise' => 'FCFA',
                ]);
            }

            $teachers[] = $user;
        }

        return $teachers;
    }

    /**
     * @param  array<int, Classe>  $classes
     */
    private function students(array $classes): void
    {
        foreach ($classes as $classe) {
            $existing = Student::on('tenant')->where('class_id', $classe->id)->count();
            $toCreate = max(0, self::STUDENTS_PER_CLASS - $existing);

            for ($i = 0; $i < $toCreate; $i++) {
                $seq = str_pad((string) (Student::on('tenant')->count() + 1), 4, '0', STR_PAD_LEFT);
                Student::factory()->create([
                    'class_id' => $classe->id,
                    'matricule' => 'MAT-'.$classe->id.'-'.$seq,
                    'email' => 'eleve'.$seq.'@demo.lycee',
                    'status' => 'Actif',
                ]);
            }
        }
    }

    /**
     * @param  array<int, Classe>  $classes
     * @param  array<int, TenantUser>  $teachers
     */
    private function timetable(array $classes, array $teachers, Semester $s1): void
    {
        if (! \Illuminate\Support\Facades\Schema::connection('tenant')->hasTable('timetable_slots')) {
            return;
        }

        $subjects = Subject::on('tenant')->whereIn('code', self::GRADE_SUBJECTS)->get()->values();
        if ($subjects->isEmpty() || empty($teachers)) {
            return;
        }

        foreach ($classes as $ci => $classe) {
            if (\Modules\Timetable\Entities\TimetableSlot::on('tenant')->where('class_id', $classe->id)->exists()) {
                continue;
            }
            foreach ($subjects as $si => $subject) {
                $teacher = $teachers[($ci + $si) % count($teachers)];
                $start = 8 + $si;
                try {
                    \Modules\Timetable\Entities\TimetableSlot::on('tenant')->create([
                        'class_id' => $classe->id,
                        'subject_id' => $subject->id,
                        'teacher_id' => $teacher->id,
                        'semester_id' => $s1->id,
                        'room_name' => 'Salle '.(($ci % 6) + 1),
                        'day_of_week' => ($si % 5) + 1,
                        'start_time' => sprintf('%02d:00', $start),
                        'end_time' => sprintf('%02d:00', $start + 1),
                        'type' => 'CM',
                        'is_recurring' => true,
                    ]);
                } catch (\Throwable $e) {
                    $this->command?->warn('  créneau ignoré: '.$e->getMessage());

                    return;
                }
            }
        }
    }

    /**
     * @param  array<int, Classe>  $classes
     */
    private function evaluationsAndGrades(array $classes, Semester $s1, ?int $userId): void
    {
        $subjects = Subject::on('tenant')->whereIn('code', self::GRADE_SUBJECTS)->get();

        foreach ($classes as $classe) {
            $students = Student::on('tenant')->where('class_id', $classe->id)->get();
            if ($students->isEmpty()) {
                continue;
            }

            foreach ($subjects as $subject) {
                foreach ([['Devoir', 'Devoir 1', 1], ['Composition', 'Composition', 2]] as [$type, $name, $coef]) {
                    $eval = Evaluation::on('tenant')->firstOrCreate(
                        ['class_id' => $classe->id, 'subject_id' => $subject->id, 'semester_id' => $s1->id, 'name' => $name],
                        ['type' => $type, 'evaluation_date' => Carbon::parse($s1->start_date)->addWeeks($coef * 3), 'max_score' => 20, 'coefficient' => $coef, 'status' => 'published', 'created_by' => $userId]
                    );

                    foreach ($students as $student) {
                        Grade::on('tenant')->updateOrCreate(
                            ['evaluation_id' => $eval->id, 'student_id' => $student->id],
                            ['score' => rand(60, 190) / 10, 'is_absent' => false, 'status' => 'published', 'entered_by' => $userId, 'entered_at' => now()]
                        );
                    }
                }
            }
        }
    }

    /**
     * @param  array<int, Classe>  $classes
     */
    private function billing(array $classes, AcademicYear $year): void
    {
        $inscription = FeeType::on('tenant')->firstOrCreate(
            ['code' => 'INSC'],
            ['name' => 'Frais d\'inscription', 'default_amount' => 15000, 'category' => 'registration', 'is_mandatory' => true, 'academic_year_id' => $year->id]
        );
        $scolarite = FeeType::on('tenant')->firstOrCreate(
            ['code' => 'SCOL'],
            ['name' => 'Frais de scolarité', 'default_amount' => 120000, 'category' => 'tuition', 'is_mandatory' => true, 'academic_year_id' => $year->id]
        );

        $billing = app(BillingService::class);
        $counter = (int) Payment::on('tenant')->count();

        foreach ($classes as $classe) {
            ClassFee::on('tenant')->firstOrCreate(
                ['class_id' => $classe->id, 'fee_type_id' => $inscription->id],
                ['label' => 'Inscription', 'amount' => 15000]
            );
            ClassFee::on('tenant')->firstOrCreate(
                ['class_id' => $classe->id, 'fee_type_id' => $scolarite->id],
                ['label' => 'Scolarité annuelle', 'amount' => 120000]
            );

            foreach (Student::on('tenant')->where('class_id', $classe->id)->get() as $student) {
                $invoice = $billing->generateClassInvoice($student, $classe);
                if (! $invoice) {
                    continue;
                }

                // Si déjà des paiements, on ne re-paie pas (idempotent).
                if ($invoice->payments()->exists()) {
                    continue;
                }

                $total = (float) $invoice->total_amount;
                $roll = rand(1, 10);
                $paid = $roll <= 5 ? $total : ($roll <= 8 ? round($total * 0.5) : 0.0);
                if ($paid <= 0) {
                    continue;
                }

                $counter++;
                Payment::on('tenant')->create([
                    'invoice_id' => $invoice->id,
                    'student_id' => $student->id,
                    'payment_date' => Carbon::now()->subDays(rand(5, 120))->toDateString(),
                    'amount' => $paid,
                    'payment_method' => ['cash', 'mobile_money', 'bank_transfer'][rand(0, 2)],
                    'receipt_number' => 'REC-'.str_pad((string) $counter, 5, '0', STR_PAD_LEFT),
                ]);

                $invoice->update([
                    'paid_amount' => $paid,
                    'status' => $paid >= $total ? 'paid' : 'partial',
                ]);
            }
        }
    }

    private function expenses(): void
    {
        if (! \Illuminate\Support\Facades\Schema::connection('tenant')->hasTable('expenses')) {
            return;
        }
        if (Expense::on('tenant')->count() >= 10) {
            return;
        }

        $cats = ExpenseCategory::on('tenant')->get()->keyBy('code');
        $defs = [
            ['ELECTRICITY', 'Facture NIGELEC', 75000],
            ['WATER', 'Facture SEEN', 30000],
            ['RENT', 'Loyer mensuel', 250000],
            ['SUPPLIES', 'Fournitures de bureau', 45000],
            ['MAINTENANCE', 'Réparation climatiseurs', 60000],
            ['TRANSPORT', 'Carburant bus scolaire', 90000],
            ['COMMUNICATION', 'Internet + téléphone', 40000],
            ['INSURANCE', 'Assurance établissement', 120000],
        ];

        foreach ([1, 0] as $monthsAgo) {
            foreach ($defs as [$code, $label, $amount]) {
                $cat = $cats->get($code);
                if (! $cat) {
                    continue;
                }
                $date = Carbon::now()->subMonths($monthsAgo)->startOfMonth()->addDays(rand(2, 25));
                Expense::on('tenant')->create([
                    'expense_category_id' => $cat->id,
                    'label' => $label,
                    'amount' => $amount,
                    'expense_date' => $date->toDateString(),
                    'paid_date' => $date->toDateString(),
                    'status' => 'paid',
                    'payment_method' => 'cash',
                    'is_recurring' => in_array($code, ['ELECTRICITY', 'WATER', 'RENT', 'COMMUNICATION'], true),
                ]);
            }
        }
    }
}
