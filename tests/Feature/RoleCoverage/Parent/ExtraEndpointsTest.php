<?php

namespace Tests\Feature\RoleCoverage\Parent;

use Illuminate\Support\Facades\Artisan;
use Modules\Documents\Entities\Document;
use Modules\Enrollment\Entities\ClassEnrollment;
use Modules\Enrollment\Entities\Group;
use Modules\Enrollment\Entities\Student;
use Modules\PortailParent\Entities\ParentModel;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Semester;
use Modules\StructureAcademique\Entities\Subject;
use Modules\Timetable\Entities\TimetableSlot;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Stories Parent 04 (EDT enfant), 08 (Annonces), 09 (Documents enfant).
 *
 * Suit le pattern de ChildDataTest : ownership via ChildPolicy + cross-parent
 * blocking + permission Spatie pour les annonces (sans ownership enfant).
 */
class ExtraEndpointsTest extends TestCase
{
    use InteractsWithTenancy {
        runMigrationsOnce as baseRunMigrationsOnce;
    }

    protected static bool $extraMigrationsRan = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$extraMigrationsRan = false;
    }

    /**
     * Migrations partagées + tables Timetable et Documents.
     */
    protected function runMigrationsOnce(): void
    {
        $this->baseRunMigrationsOnce();

        if (self::$extraMigrationsRan) {
            return;
        }

        foreach (['Modules/Timetable/Database/Migrations/tenant', 'Modules/Documents/Database/Migrations/tenant'] as $path) {
            Artisan::call('migrate', [
                '--database' => 'mysql',
                '--path' => base_path($path),
                '--realpath' => true,
                '--force' => true,
            ]);
        }

        self::$extraMigrationsRan = true;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        foreach (['view children', 'view children timetable', 'view children documents', 'view announcements'] as $name) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $name, 'guard_name' => 'tenant']);
        }
        \Spatie\Permission\Models\Role::where('name', 'Parent')->where('guard_name', 'tenant')->first()
            ?->givePermissionTo(['view children', 'view children timetable', 'view children documents', 'view announcements']);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function parentWithChild(): array
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Parent');
        $parent = ParentModel::factory()->create(['user_id' => $user->id]);
        $child = Student::factory()->create();
        $parent->students()->attach($child->id);
        $token = $user->createToken('test-token')->plainTextToken;

        return [$user, $child, $token];
    }

    #[Test]
    public function parent_can_view_own_child_timetable(): void
    {
        [, $child, $token] = $this->parentWithChild();

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$child->id}/timetable")
            ->assertOk()
            ->assertJsonPath('meta.student_id', $child->id);
    }

    #[Test]
    public function parent_cannot_view_other_parents_child_timetable(): void
    {
        [, , $token] = $this->parentWithChild();

        $otherUser = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($otherUser, 'Parent');
        $otherParent = ParentModel::factory()->create(['user_id' => $otherUser->id]);
        $otherChild = Student::factory()->create();
        $otherParent->students()->attach($otherChild->id);

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$otherChild->id}/timetable")
            ->assertForbidden();
    }

    #[Test]
    public function parent_can_view_own_child_documents(): void
    {
        [, $child, $token] = $this->parentWithChild();

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$child->id}/documents")
            ->assertOk()
            ->assertJsonPath('meta.student_id', $child->id);
    }

    #[Test]
    public function parent_cannot_view_other_parents_child_documents(): void
    {
        [, , $token] = $this->parentWithChild();

        $otherUser = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($otherUser, 'Parent');
        $otherParent = ParentModel::factory()->create(['user_id' => $otherUser->id]);
        $otherChild = Student::factory()->create();
        $otherParent->students()->attach($otherChild->id);

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$otherChild->id}/documents")
            ->assertForbidden();
    }

    #[Test]
    public function parent_can_view_announcements(): void
    {
        [, , $token] = $this->parentWithChild();

        $this->withToken($token)
            ->getJson('/api/admin/parent/announcements')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    #[Test]
    public function parent_without_announcements_permission_is_blocked(): void
    {
        [, , $token] = $this->parentWithChild();

        \Spatie\Permission\Models\Role::where('name', 'Parent')->where('guard_name', 'tenant')->first()
            ?->revokePermissionTo('view announcements');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->withToken($token)
            ->getJson('/api/admin/parent/announcements')
            ->assertForbidden();
    }

    #[Test]
    public function etudiant_cannot_access_parent_endpoints(): void
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Étudiant');
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/parent/announcements')
            ->assertForbidden();
    }

    #[Test]
    public function timetable_returns_slots_of_the_child_active_class(): void
    {
        [, $child, $token] = $this->parentWithChild();
        $academicYear = AcademicYear::factory()->create(['name' => '2090-2091', 'is_active' => true]);
        $semester = Semester::factory()->create(['academic_year_id' => $academicYear->id, 'is_active' => true]);
        $classe = Classe::factory()->create(['academic_year_id' => $academicYear->id, 'name' => '6ème A']);
        $otherClasse = Classe::factory()->create(['academic_year_id' => $academicYear->id]);
        $subject = Subject::factory()->create(['name' => 'Mathématiques']);

        ClassEnrollment::query()->create([
            'student_id' => $child->id,
            'classe_id' => $classe->id,
            'academic_year_id' => $academicYear->id,
            'enrolled_at' => now()->toDateString(),
            'status' => 'Actif',
        ]);

        $groupId = Group::factory()->forSemester($semester)->create()->id;

        $slot = TimetableSlot::factory()->create([
            'group_id' => $groupId,
            'classe_id' => $classe->id,
            'subject_id' => $subject->id,
            'semester_id' => $semester->id,
            'day_of_week' => 'Lundi',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
        ]);
        TimetableSlot::factory()->create([
            'group_id' => $groupId,
            'classe_id' => $otherClasse->id,
            'semester_id' => $semester->id,
        ]);

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$child->id}/timetable")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $slot->id)
            ->assertJsonPath('data.0.subject', 'Mathématiques')
            ->assertJsonPath('data.0.start_time', '08:00')
            ->assertJsonPath('meta.classe.id', $classe->id);
    }

    #[Test]
    public function timetable_is_empty_when_child_has_no_active_class(): void
    {
        [, $child, $token] = $this->parentWithChild();

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$child->id}/timetable")
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.classe', null);
    }

    #[Test]
    public function documents_return_only_issued_documents_of_the_child(): void
    {
        [, $child, $token] = $this->parentWithChild();
        $academicYearId = AcademicYear::factory()->create(['name' => '2091-2092'])->id;

        $issued = Document::factory()->create([
            'student_id' => $child->id,
            'academic_year_id' => $academicYearId,
            'status' => 'issued',
            'document_type' => 'certificate_enrollment',
            'document_number' => 'DOC-0001',
        ]);
        Document::factory()->create(['student_id' => $child->id, 'academic_year_id' => $academicYearId, 'status' => 'cancelled']);
        Document::factory()->create([
            'student_id' => Student::factory()->create()->id,
            'academic_year_id' => $academicYearId,
            'status' => 'issued',
        ]);

        $this->withToken($token)
            ->getJson("/api/admin/parent/children/{$child->id}/documents")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $issued->id)
            ->assertJsonPath('data.0.type', 'certificate_enrollment')
            ->assertJsonPath('data.0.document_number', 'DOC-0001');
    }
}
