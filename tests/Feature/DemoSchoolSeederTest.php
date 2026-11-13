<?php

namespace Tests\Feature;

use Database\Seeders\DemoSchoolSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class DemoSchoolSeederTest extends TestCase
{
    use InteractsWithTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    #[Test]
    public function it_seeds_complete_demo_school_data_idempotently(): void
    {
        $this->seed(DemoSchoolSeeder::class);

        $expectedClasses = 16;
        $expectedStudents = $expectedClasses * 6;

        $this->assertDatabaseHas('academic_years', [
            'name' => '2026-2027',
            'is_active' => true,
        ], 'tenant');

        $this->assertSame($expectedClasses, DB::connection('tenant')->table('classes')->count());
        $this->assertSame($expectedStudents, DB::connection('tenant')->table('students')->count());
        $this->assertSame($expectedStudents, DB::connection('tenant')->table('parents')->count());
        $this->assertSame($expectedStudents, DB::connection('tenant')->table('parent_student')->count());
        $this->assertSame($expectedStudents, DB::connection('tenant')->table('class_enrollments')->count());
        $this->assertSame($expectedStudents, DB::connection('tenant')->table('student_enrollments')->count());
        $this->assertSame($expectedStudents, DB::connection('tenant')->table('invoices')->count());
        $this->assertSame($expectedStudents * 3, DB::connection('tenant')->table('invoice_items')->count());
        $this->assertSame((int) floor($expectedStudents / 2), DB::connection('tenant')->table('payments')->count());

        foreach (['Administrator', 'Professeur', 'Élève', 'Parent', 'Caissier', 'Comptable'] as $role) {
            $this->assertDatabaseHas('roles', [
                'name' => $role,
                'guard_name' => 'tenant',
            ], 'tenant');
        }

        $this->seed(DemoSchoolSeeder::class);

        $this->assertSame($expectedClasses, DB::connection('tenant')->table('classes')->count());
        $this->assertSame($expectedStudents, DB::connection('tenant')->table('students')->count());
        $this->assertSame($expectedStudents, DB::connection('tenant')->table('class_enrollments')->count());
        $this->assertSame($expectedStudents, DB::connection('tenant')->table('invoices')->count());
    }
}
