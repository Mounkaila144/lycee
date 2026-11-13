<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Modules\Enrollment\Entities\ClassEnrollment;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentEnrollment;
use Modules\Finance\Entities\FeeType;
use Modules\Finance\Entities\Invoice;
use Modules\Finance\Entities\InvoiceItem;
use Modules\Finance\Entities\Payment;
use Modules\PortailParent\Entities\ParentModel;
use Modules\StructureAcademique\Database\Seeders\CycleAndLevelSeeder;
use Modules\StructureAcademique\Database\Seeders\SeriesSeeder;
use Modules\StructureAcademique\Database\Seeders\SubjectCoefficientSeeder;
use Modules\StructureAcademique\Database\Seeders\SubjectSeeder;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Level;
use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\Semester;
use Modules\StructureAcademique\Entities\Series;
use Modules\UsersGuard\Database\Seeders\UsersGuardDatabaseSeeder;
use Modules\UsersGuard\Entities\Tenant;
use Modules\UsersGuard\Entities\TenantUser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DemoSchoolSeeder extends Seeder
{
    private const PASSWORD = 'password';

    private const STUDENTS_PER_CLASS = 6;

    public function run(): void
    {
        $tenants = Tenant::query()->get();

        if ($tenants->isEmpty() && ! app()->runningUnitTests()) {
            $this->call(UsersGuardDatabaseSeeder::class);
            $tenants = Tenant::query()->get();
        }

        if ($tenants->isEmpty()) {
            $this->command?->warn('Aucun tenant trouve. Lancez d’abord le seeder UsersGuard ou creez un tenant.');

            return;
        }

        foreach ($tenants as $tenant) {
            if (app()->runningUnitTests()) {
                $this->seedTenant($tenant->id);

                continue;
            }

            tenancy()->initialize($tenant);

            try {
                $this->seedTenant($tenant->id);
            } finally {
                tenancy()->end();
            }
        }
    }

    private function seedTenant(string $tenantId): void
    {
        DB::connection('tenant')->transaction(function () use ($tenantId) {
            $this->seedRolesAndPermissions();
            $users = $this->seedUsers($tenantId);
            $this->seedAcademicReferences();
            $academicYear = $this->seedAcademicYears();
            $semester = $this->seedSemesters($academicYear);
            $classes = $this->seedClasses($academicYear, $users['teachers']);
            $programme = $this->seedProgramme($users['teachers'][0]);
            $feeTypes = $this->seedFeeTypes($academicYear);
            $this->seedStudents($tenantId, $classes, $programme, $academicYear, $semester, $users, $feeTypes);
        });

        $this->command?->info("Demo data seeded for tenant [{$tenantId}].");
    }

    private function seedRolesAndPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'view dashboard',
            'view users',
            'create users',
            'edit users',
            'delete users',
            'view roles',
            'create roles',
            'edit roles',
            'delete roles',
            'view settings',
            'edit settings',
            'view reports',
            'export reports',
            'view students',
            'manage grades',
            'view timetable',
            'view own grades',
            'view own timetable',
            'upload documents',
            'request attestations',
            'view own attendance',
            'view children',
            'view children grades',
            'view children attendance',
            'view children timetable',
            'view children invoices',
            'pay children invoices',
            'view children documents',
            'message teachers',
            'view announcements',
            'view invoices',
            'create invoices',
            'edit invoices',
            'delete invoices',
            'view payments',
            'create payments',
            'edit payments',
            'delete payments',
            'generate receipts',
            'manage payment plans',
            'manage late fees',
            'manage refunds',
            'manage bank reconciliation',
            'manage collection',
            'view financial reports',
            'export financial data',
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission, 'guard_name' => 'tenant'],
                ['display_name' => ucfirst($permission)]
            );
        }

        $allPermissions = Permission::query()->where('guard_name', 'tenant')->pluck('name')->all();

        $this->syncRole('Administrator', $allPermissions, 'Administrateur - Acces complet');
        $this->syncRole('Manager', ['view dashboard', 'view users', 'create users', 'edit users', 'view reports'], 'Manager - Suivi et reporting');
        $this->syncRole('User', ['view dashboard'], 'Utilisateur simple');
        $this->syncRole('Professeur', ['view dashboard', 'view students', 'manage grades', 'view timetable'], 'Enseignant - Classes et notes');
        $this->syncRole('Élève', ['view dashboard', 'view own grades', 'view own timetable', 'upload documents', 'request attestations', 'view own attendance'], 'Eleve - Portail');
        $this->syncRole('Parent', ['view dashboard', 'view children', 'view children grades', 'view children attendance', 'view children timetable', 'view children invoices', 'pay children invoices', 'view children documents', 'message teachers', 'view announcements'], 'Parent - Suivi des enfants');
        $this->syncRole('Caissier', ['view dashboard', 'view students', 'view invoices', 'create payments', 'generate receipts', 'view financial reports'], 'Caissier - Encaissements');
        $this->syncRole('Comptable', ['view dashboard', 'view students', 'view invoices', 'create invoices', 'edit invoices', 'view payments', 'create payments', 'generate receipts', 'manage payment plans', 'manage late fees', 'manage refunds', 'manage bank reconciliation', 'view financial reports', 'export financial data', 'manage collection'], 'Comptable - Facturation et recouvrement');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function syncRole(string $name, array $permissions, string $description): Role
    {
        $role = Role::updateOrCreate(
            ['name' => $name, 'guard_name' => 'tenant'],
            ['display_name' => $name, 'description' => $description]
        );

        $role->syncPermissions($permissions);

        return $role;
    }

    /**
     * @return array{admin: TenantUser, cashier: TenantUser, accountant: TenantUser, teachers: list<TenantUser>}
     */
    private function seedUsers(string $tenantId): array
    {
        $admin = $this->tenantUser('demo.admin', 'admin.demo@'.$tenantId.'.test', 'Amina', 'Directrice', 'admin', 'F', 'Administrator');
        $cashier = $this->tenantUser('demo.caissier', 'caissier.demo@'.$tenantId.'.test', 'Issa', 'Caissier', 'admin', 'M', 'Caissier');
        $accountant = $this->tenantUser('demo.comptable', 'comptable.demo@'.$tenantId.'.test', 'Mariam', 'Comptable', 'admin', 'F', 'Comptable');

        $teachers = [
            $this->tenantUser('prof.maths', 'prof.maths@'.$tenantId.'.test', 'Oumarou', 'Mahamadou', 'admin', 'M', 'Professeur'),
            $this->tenantUser('prof.francais', 'prof.francais@'.$tenantId.'.test', 'Ramatou', 'Ali', 'admin', 'F', 'Professeur'),
            $this->tenantUser('prof.svt', 'prof.svt@'.$tenantId.'.test', 'Harouna', 'Ibrahim', 'admin', 'M', 'Professeur'),
            $this->tenantUser('prof.hg', 'prof.hg@'.$tenantId.'.test', 'Fatima', 'Adamou', 'admin', 'F', 'Professeur'),
        ];

        return [
            'admin' => $admin,
            'cashier' => $cashier,
            'accountant' => $accountant,
            'teachers' => $teachers,
        ];
    }

    private function tenantUser(string $username, string $email, string $firstname, string $lastname, string $application, string $sex, string $role): TenantUser
    {
        $user = TenantUser::updateOrCreate(
            ['username' => $username],
            [
                'email' => $email,
                'password' => Hash::make(self::PASSWORD),
                'firstname' => $firstname,
                'lastname' => $lastname,
                'application' => $application,
                'is_active' => true,
                'sex' => $sex,
                'phone' => '+2279000'.str_pad((string) crc32($username), 4, '0', STR_PAD_LEFT),
                'mobile' => '+2279100'.str_pad((string) crc32($email), 4, '0', STR_PAD_LEFT),
                'city' => 'Niamey',
                'country' => 'Niger',
            ]
        );

        $user->assignRole($role);

        return $user;
    }

    private function seedAcademicReferences(): void
    {
        $this->call(CycleAndLevelSeeder::class);
        $this->call(SeriesSeeder::class);
        $this->call(SubjectSeeder::class);
        $this->call(SubjectCoefficientSeeder::class);
    }

    private function seedAcademicYears(): AcademicYear
    {
        AcademicYear::query()->where('is_active', true)->update(['is_active' => false]);

        AcademicYear::updateOrCreate(
            ['name' => '2025-2026'],
            [
                'start_date' => '2025-10-01',
                'end_date' => '2026-06-30',
                'is_active' => false,
            ]
        );

        return AcademicYear::updateOrCreate(
            ['name' => '2026-2027'],
            [
                'start_date' => '2026-10-01',
                'end_date' => '2027-06-30',
                'is_active' => true,
            ]
        );
    }

    private function seedSemesters(AcademicYear $academicYear): Semester
    {
        $s1 = Semester::updateOrCreate(
            ['academic_year_id' => $academicYear->id, 'name' => 'S1'],
            [
                'start_date' => '2026-10-01',
                'end_date' => '2027-01-31',
                'is_active' => true,
            ]
        );

        Semester::updateOrCreate(
            ['academic_year_id' => $academicYear->id, 'name' => 'S2'],
            [
                'start_date' => '2027-02-01',
                'end_date' => '2027-06-30',
                'is_active' => false,
            ]
        );

        return $s1;
    }

    /**
     * @param  list<TenantUser>  $teachers
     * @return list<Classe>
     */
    private function seedClasses(AcademicYear $academicYear, array $teachers): array
    {
        $levels = Level::query()->pluck('id', 'code');
        $series = Series::query()->pluck('id', 'code');

        $classDefinitions = [
            ['level' => '6E', 'series' => null, 'sections' => ['A', 'B']],
            ['level' => '5E', 'series' => null, 'sections' => ['A', 'B']],
            ['level' => '4E', 'series' => null, 'sections' => ['A', 'B']],
            ['level' => '3E', 'series' => null, 'sections' => ['A', 'B']],
            ['level' => '2NDE', 'series' => null, 'sections' => ['A', 'B']],
            ['level' => '1ERE', 'series' => 'A', 'sections' => ['1']],
            ['level' => '1ERE', 'series' => 'C', 'sections' => ['1']],
            ['level' => '1ERE', 'series' => 'D', 'sections' => ['1']],
            ['level' => 'TLE', 'series' => 'A', 'sections' => ['1']],
            ['level' => 'TLE', 'series' => 'C', 'sections' => ['1']],
            ['level' => 'TLE', 'series' => 'D', 'sections' => ['1']],
        ];

        $classes = [];
        $teacherIndex = 0;

        foreach ($classDefinitions as $definition) {
            foreach ($definition['sections'] as $section) {
                $classe = new Classe([
                    'academic_year_id' => $academicYear->id,
                    'level_id' => $levels[$definition['level']],
                    'series_id' => $definition['series'] ? $series[$definition['series']] : null,
                    'section' => $section,
                    'max_capacity' => 60,
                    'classroom' => 'Salle '.str_pad((string) (count($classes) + 1), 2, '0', STR_PAD_LEFT),
                    'head_teacher_id' => $teachers[$teacherIndex % count($teachers)]->id,
                ]);
                $name = $classe->generateName();

                $classes[] = Classe::updateOrCreate(
                    ['academic_year_id' => $academicYear->id, 'name' => $name],
                    [
                        'level_id' => $levels[$definition['level']],
                        'series_id' => $definition['series'] ? $series[$definition['series']] : null,
                        'section' => $section,
                        'max_capacity' => 60,
                        'classroom' => 'Salle '.str_pad((string) (count($classes) + 1), 2, '0', STR_PAD_LEFT),
                        'head_teacher_id' => $teachers[$teacherIndex % count($teachers)]->id,
                    ]
                );

                $teacherIndex++;
            }
        }

        return $classes;
    }

    private function seedProgramme(TenantUser $responsable): Programme
    {
        $programme = Programme::updateOrCreate(
            ['code' => 'SEC-GEN'],
            [
                'libelle' => 'Secondaire General',
                'type' => 'Licence',
                'duree_annees' => 3,
                'description' => 'Programme technique utilise par les donnees demo secondaires.',
                'responsable_id' => $responsable->id,
                'statut' => 'Actif',
            ]
        );

        foreach (['L1', 'L2', 'L3'] as $level) {
            DB::connection('tenant')->table('program_levels')->updateOrInsert(
                ['program_id' => $programme->id, 'level' => $level],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        return $programme;
    }

    /**
     * @return array<string, FeeType>
     */
    private function seedFeeTypes(AcademicYear $academicYear): array
    {
        $definitions = [
            'INSCRIPTION' => ['Frais d’inscription', 'registration', 10000],
            'SCOLARITE' => ['Scolarite annuelle', 'tuition', 60000],
            'CARTE' => ['Carte scolaire', 'card', 2500],
            'EXAMEN' => ['Frais d’examen', 'exam', 5000],
        ];

        $feeTypes = [];

        foreach ($definitions as $code => [$name, $category, $amount]) {
            $feeTypes[$code] = FeeType::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'description' => $name.' - '.$academicYear->name,
                    'default_amount' => $amount,
                    'category' => $category,
                    'is_mandatory' => true,
                    'applies_to' => ['levels' => ['6E', '5E', '4E', '3E', '2NDE', '1ERE', 'TLE']],
                    'academic_year_id' => $academicYear->id,
                ]
            );
        }

        return $feeTypes;
    }

    /**
     * @param  list<Classe>  $classes
     * @param  array{admin: TenantUser, cashier: TenantUser, accountant: TenantUser, teachers: list<TenantUser>}  $users
     * @param  array<string, FeeType>  $feeTypes
     */
    private function seedStudents(string $tenantId, array $classes, Programme $programme, AcademicYear $academicYear, Semester $semester, array $users, array $feeTypes): void
    {
        $firstnames = ['Ali', 'Amina', 'Moussa', 'Fatouma', 'Ibrahim', 'Rakia', 'Abdou', 'Zeinab', 'Mahamadou', 'Hadiza', 'Ousmane', 'Salamatou'];
        $lastnames = ['Adamou', 'Moussa', 'Issoufou', 'Ibrahim', 'Mahamane', 'Garba', 'Ali', 'Oumarou', 'Souley', 'Abdoulaye', 'Sani', 'Boubacar'];
        $parentFirstnames = ['Malam', 'Hadiza', 'Oumarou', 'Aichatou', 'Issa', 'Ramatou', 'Harouna', 'Maimouna'];
        $professions = ['Commercant', 'Enseignante', 'Chauffeur', 'Infirmiere', 'Artisan', 'Fonctionnaire', 'Agriculteur', 'Entrepreneure'];
        $studentIndex = 1;

        foreach ($classes as $classe) {
            $level = $classe->level;

            for ($position = 1; $position <= self::STUDENTS_PER_CLASS; $position++) {
                $firstname = $firstnames[$studentIndex % count($firstnames)];
                $lastname = $lastnames[($studentIndex + $position) % count($lastnames)];
                $matricule = sprintf('DEMO-%s-%03d', str_replace('-', '', $academicYear->name), $studentIndex);
                $studentUser = $this->tenantUser(
                    sprintf('eleve.%03d', $studentIndex),
                    sprintf('eleve.%03d@%s.test', $studentIndex, $tenantId),
                    $firstname,
                    $lastname,
                    'frontend',
                    $studentIndex % 2 === 0 ? 'F' : 'M',
                    'Élève'
                );

                $student = Student::updateOrCreate(
                    ['matricule' => $matricule],
                    [
                        'user_id' => $studentUser->id,
                        'firstname' => $firstname,
                        'lastname' => $lastname,
                        'birthdate' => $this->birthdateForLevel($level->code, $studentIndex),
                        'birthplace' => $studentIndex % 3 === 0 ? 'Maradi' : 'Niamey',
                        'sex' => $studentIndex % 2 === 0 ? 'F' : 'M',
                        'nationality' => 'Nigérienne',
                        'phone' => '+22796'.str_pad((string) $studentIndex, 6, '0', STR_PAD_LEFT),
                        'address' => 'Quartier demo '.$classe->name,
                        'city' => 'Niamey',
                        'quarter' => $studentIndex % 2 === 0 ? 'Yantala' : 'Lazaret',
                        'blood_group' => ['A+', 'B+', 'O+', 'AB+'][$studentIndex % 4],
                        'health_notes' => $studentIndex % 10 === 0 ? 'Suivi medical particulier.' : null,
                        'photo' => null,
                        'status' => 'Actif',
                        'emergency_contact_name' => $parentFirstnames[$studentIndex % count($parentFirstnames)].' '.$lastname,
                        'emergency_contact_phone' => '+22797'.str_pad((string) $studentIndex, 6, '0', STR_PAD_LEFT),
                    ]
                );

                $this->seedParentForStudent($tenantId, $student, $studentIndex, $parentFirstnames, $professions);
                $this->seedEnrollmentForStudent($student, $programme, $academicYear, $semester, $classe, $users['admin']);
                $this->seedInvoiceForStudent($student, $academicYear, $feeTypes, $users['cashier'], $studentIndex);

                $studentIndex++;
            }
        }

        if (! Schema::connection('tenant')->hasTable('class_enrollments')) {
            $this->command?->warn('Aucune table class_enrollments: les eleves demo sont crees, mais la liaison eleve-classe reste dans les notes d’inscription.');
        }
    }

    private function birthdateForLevel(string $levelCode, int $offset): string
    {
        $birthYear = match ($levelCode) {
            '6E' => 2015,
            '5E' => 2014,
            '4E' => 2013,
            '3E' => 2012,
            '2NDE' => 2011,
            '1ERE' => 2010,
            'TLE' => 2009,
            default => 2012,
        };

        return Carbon::create($birthYear, ($offset % 12) + 1, ($offset % 26) + 1)->toDateString();
    }

    /**
     * @param  list<string>  $parentFirstnames
     * @param  list<string>  $professions
     */
    private function seedParentForStudent(string $tenantId, Student $student, int $index, array $parentFirstnames, array $professions): void
    {
        $relationship = $index % 2 === 0 ? 'Mère' : 'Père';
        $firstname = $parentFirstnames[$index % count($parentFirstnames)];
        $lastname = $student->lastname;
        $phone = '+22798'.str_pad((string) $index, 6, '0', STR_PAD_LEFT);

        $parentUser = $this->tenantUser(
            sprintf('parent.%03d', $index),
            sprintf('parent.%03d@%s.test', $index, $tenantId),
            $firstname,
            $lastname,
            'frontend',
            $relationship === 'Mère' ? 'F' : 'M',
            'Parent'
        );

        $parent = ParentModel::updateOrCreate(
            ['phone' => $phone],
            [
                'user_id' => $parentUser->id,
                'firstname' => $firstname,
                'lastname' => $lastname,
                'relationship' => $relationship,
                'phone_secondary' => null,
                'email' => sprintf('parent.%03d@%s.test', $index, $tenantId),
                'profession' => $professions[$index % count($professions)],
                'address' => $student->address,
            ]
        );

        $student->parents()->syncWithoutDetaching([
            $parent->id => [
                'is_primary_contact' => true,
                'is_financial_responsible' => true,
            ],
        ]);
    }

    private function seedEnrollmentForStudent(Student $student, Programme $programme, AcademicYear $academicYear, Semester $semester, Classe $classe, TenantUser $admin): void
    {
        ClassEnrollment::updateOrCreate(
            [
                'student_id' => $student->id,
                'academic_year_id' => $academicYear->id,
            ],
            [
                'classe_id' => $classe->id,
                'previous_class_enrollment_id' => null,
                'assigned_by' => $admin->id,
                'enrolled_at' => $academicYear->start_date,
                'assignment_type' => 'Nouvelle inscription',
                'status' => 'Actif',
                'final_decision' => null,
                'final_decision_at' => null,
                'notes' => 'Affectation demo: '.$classe->name,
            ]
        );

        StudentEnrollment::updateOrCreate(
            [
                'student_id' => $student->id,
                'programme_id' => $programme->id,
                'academic_year_id' => $academicYear->id,
                'semester_id' => $semester->id,
            ],
            [
                'level' => $classe->level->code,
                'group_id' => null,
                'enrollment_date' => $academicYear->start_date,
                'status' => 'Actif',
                'notes' => 'Classe demo: '.$classe->name,
                'enrolled_by' => $admin->id,
            ]
        );
    }

    /**
     * @param  array<string, FeeType>  $feeTypes
     */
    private function seedInvoiceForStudent(Student $student, AcademicYear $academicYear, array $feeTypes, TenantUser $cashier, int $index): void
    {
        $invoiceNumber = sprintf('DEMO-INV-%s-%03d', str_replace('-', '', $academicYear->name), $index);
        $total = (float) $feeTypes['INSCRIPTION']->default_amount
            + (float) $feeTypes['SCOLARITE']->default_amount
            + (float) $feeTypes['CARTE']->default_amount;
        $paid = match ($index % 4) {
            0 => $total,
            1 => 25000,
            default => 0,
        };
        $status = match (true) {
            $paid >= $total => 'paid',
            $paid > 0 => 'partial',
            $index % 5 === 0 => 'overdue',
            default => 'pending',
        };

        $invoice = Invoice::updateOrCreate(
            ['invoice_number' => $invoiceNumber],
            [
                'student_id' => $student->id,
                'academic_year_id' => $academicYear->id,
                'invoice_date' => '2026-10-05',
                'due_date' => $status === 'overdue' ? '2026-10-20' : '2026-11-30',
                'total_amount' => $total,
                'paid_amount' => $paid,
                'status' => $status,
                'notes' => 'Facture de demonstration.',
            ]
        );

        foreach (['INSCRIPTION', 'SCOLARITE', 'CARTE'] as $code) {
            $feeType = $feeTypes[$code];

            InvoiceItem::updateOrCreate(
                ['invoice_id' => $invoice->id, 'fee_type_id' => $feeType->id],
                [
                    'description' => $feeType->name,
                    'quantity' => 1,
                    'unit_price' => $feeType->default_amount,
                    'amount' => $feeType->default_amount,
                    'discount_amount' => 0,
                ]
            );
        }

        if ($paid <= 0) {
            return;
        }

        Payment::updateOrCreate(
            ['receipt_number' => sprintf('DEMO-REC-%s-%03d', str_replace('-', '', $academicYear->name), $index)],
            [
                'invoice_id' => $invoice->id,
                'student_id' => $student->id,
                'payment_date' => '2026-10-10',
                'amount' => $paid,
                'payment_method' => $index % 2 === 0 ? 'cash' : 'mobile_money',
                'reference_number' => sprintf('PAY-DEMO-%03d', $index),
                'notes' => 'Paiement de demonstration.',
                'recorded_by' => $cashier->id,
            ]
        );
    }
}
