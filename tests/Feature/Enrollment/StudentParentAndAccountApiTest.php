<?php

namespace Tests\Feature\Enrollment;

use Illuminate\Support\Facades\Hash;
use Modules\Enrollment\Entities\Student;
use Modules\PortailParent\Entities\ParentModel;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Parents d'un élève (créer, lier, délier) et comptes de connexion élève/parent
 * (création avec mot de passe affiché une fois, réinitialisation, activation).
 */
class StudentParentAndAccountApiTest extends TestCase
{
    use InteractsWithTenancy;

    private TenantUser $admin;

    private Student $student;

    /**
     * @var array<int, string>
     */
    private array $tokens = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        $this->admin = $this->userWithRole('Administrator');
        $this->student = Student::factory()->withMatricule('LYC-2026-001')->create(['firstname' => 'Awa', 'lastname' => 'Issa']);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function userWithRole(string $role): TenantUser
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, $role);

        return $user;
    }

    private function as(TenantUser $user): self
    {
        $this->tokens[$user->id] ??= $user->createToken('test-token')->plainTextToken;

        return $this->withToken($this->tokens[$user->id]);
    }

    private function studentUri(string $suffix = ''): string
    {
        return '/api/admin/enrollment/students/'.$this->student->id.$suffix;
    }

    /**
     * @return array<string, mixed>
     */
    private function parentPayload(array $overrides = []): array
    {
        return array_merge([
            'firstname' => 'Malam',
            'lastname' => 'Issa',
            'relationship' => 'Père',
            'phone' => '+22790000001',
            'email' => 'malam.issa@example.test',
            'is_primary_contact' => true,
        ], $overrides);
    }

    #[Test]
    public function it_creates_a_parent_linked_to_the_student(): void
    {
        $response = $this->as($this->admin)->postJson($this->studentUri('/parents'), $this->parentPayload());

        $response->assertCreated()
            ->assertJsonPath('data.full_name', 'Malam Issa')
            ->assertJsonPath('data.relationship', 'Père')
            ->assertJsonPath('data.account', null);

        $parent = ParentModel::query()->where('phone', '+22790000001')->firstOrFail();
        $this->assertDatabaseHas('parent_student', [
            'parent_id' => $parent->id,
            'student_id' => $this->student->id,
            'is_primary_contact' => true,
            'is_financial_responsible' => false,
        ], 'tenant');

        $this->as($this->admin)->getJson($this->studentUri('/parents'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $parent->id)
            ->assertJsonPath('data.0.is_primary_contact', true)
            ->assertJsonPath('data.0.children_count', 1);
    }

    #[Test]
    public function it_validates_parent_creation_and_refuses_duplicate_phone(): void
    {
        $this->as($this->admin)->postJson($this->studentUri('/parents'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['firstname', 'lastname', 'relationship', 'phone']);

        $this->as($this->admin)->postJson($this->studentUri('/parents'), $this->parentPayload(['relationship' => 'Cousin']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('relationship');

        $existing = ParentModel::factory()->create(['phone' => '+22790000001']);

        $this->as($this->admin)->postJson($this->studentUri('/parents'), $this->parentPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone')
            ->assertJsonPath('data.existing_parent_id', $existing->id);
    }

    #[Test]
    public function it_links_searches_and_unlinks_an_existing_parent(): void
    {
        $parent = ParentModel::factory()->create(['firstname' => 'Hadiza', 'lastname' => 'Garba', 'phone' => '+22791111111']);
        $sibling = Student::factory()->create();
        $parent->students()->attach($sibling->id);

        $this->as($this->admin)->getJson('/api/admin/enrollment/parents?search=Garba')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $parent->id)
            ->assertJsonPath('data.0.children_count', 1);

        $this->as($this->admin)->postJson($this->studentUri('/parents/link'), ['parent_id' => $parent->id, 'is_financial_responsible' => true])
            ->assertCreated()
            ->assertJsonPath('data.children_count', 2);

        $this->as($this->admin)->postJson($this->studentUri('/parents/link'), ['parent_id' => $parent->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent_id');

        $this->as($this->admin)->postJson($this->studentUri('/parents/link'), ['parent_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent_id');

        $this->as($this->admin)->deleteJson($this->studentUri('/parents/'.$parent->id))->assertOk();

        $this->assertDatabaseMissing('parent_student', ['parent_id' => $parent->id, 'student_id' => $this->student->id], 'tenant');
        $this->assertDatabaseHas('parent_student', ['parent_id' => $parent->id, 'student_id' => $sibling->id], 'tenant');
        $this->assertNotNull($parent->fresh());

        $this->as($this->admin)->deleteJson($this->studentUri('/parents/'.$parent->id))->assertNotFound();
    }

    #[Test]
    public function it_creates_a_student_account_and_returns_the_password_once(): void
    {
        $response = $this->as($this->admin)->postJson($this->studentUri('/account'));

        $response->assertCreated()
            ->assertJsonPath('data.account.username', 'eleve.lyc-2026-001')
            ->assertJsonPath('data.account.is_active', true)
            ->assertJsonPath('data.account.roles', ['Élève']);

        $password = $response->json('data.password');
        $this->assertIsString($password);
        $this->assertSame(10, strlen($password));

        $account = TenantUser::query()->where('username', 'eleve.lyc-2026-001')->firstOrFail();
        $this->assertSame($account->id, $this->student->fresh()->user_id);
        $this->assertSame('frontend', $account->application);
        $this->assertTrue(Hash::check($password, $account->password));
        $this->assertTrue($account->hasRole('Élève'));

        $this->as($this->admin)->getJson($this->studentUri('/account'))
            ->assertOk()
            ->assertJsonPath('data.username', 'eleve.lyc-2026-001')
            ->assertJsonMissingPath('data.password');

        $this->as($this->admin)->postJson($this->studentUri('/account'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('account');
    }

    #[Test]
    public function the_created_student_account_can_log_in(): void
    {
        $password = $this->as($this->admin)->postJson($this->studentUri('/account'))->json('data.password');

        $this->postJson('/api/admin/auth/login', [
            'username' => 'eleve.lyc-2026-001',
            'password' => $password,
            'application' => 'frontend',
        ])->assertOk()->assertJsonPath('success', true);
    }

    #[Test]
    public function it_accepts_a_custom_username_and_makes_generated_usernames_unique(): void
    {
        TenantUser::factory()->create(['username' => 'eleve.lyc-2026-001']);

        $this->as($this->admin)->postJson($this->studentUri('/account'))
            ->assertCreated()
            ->assertJsonPath('data.account.username', 'eleve.lyc-2026-0012');

        $other = Student::factory()->create();

        $this->as($this->admin)->postJson('/api/admin/enrollment/students/'.$other->id.'/account', ['username' => 'Bad Name!'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('username');

        $this->as($this->admin)->postJson('/api/admin/enrollment/students/'.$other->id.'/account', ['username' => 'eleve.lyc-2026-001'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('username');

        $this->as($this->admin)->postJson('/api/admin/enrollment/students/'.$other->id.'/account', ['username' => 'awa.custom'])
            ->assertCreated()
            ->assertJsonPath('data.account.username', 'awa.custom');
    }

    #[Test]
    public function it_creates_a_parent_account_with_firstname_lastname_username(): void
    {
        $parent = ParentModel::factory()->create(['firstname' => 'Aïchatou', 'lastname' => 'Mahamane', 'email' => 'aicha@example.test', 'relationship' => 'Mère']);
        $this->student->parents()->attach($parent->id);
        TenantUser::factory()->create(['username' => 'aichatou.mahamane']);

        $response = $this->as($this->admin)->postJson("/api/admin/enrollment/parents/{$parent->id}/account");

        $response->assertCreated()
            ->assertJsonPath('data.account.username', 'aichatou.mahamane2')
            ->assertJsonPath('data.account.email', 'aicha@example.test')
            ->assertJsonPath('data.account.roles', ['Parent']);

        $account = TenantUser::query()->where('username', 'aichatou.mahamane2')->firstOrFail();
        $this->assertSame($account->id, $parent->fresh()->user_id);
        $this->assertSame('F', $account->sex);
        $this->assertTrue(Hash::check($response->json('data.password'), $account->password));

        $this->as($this->admin)->getJson($this->studentUri('/parents'))
            ->assertOk()
            ->assertJsonPath('data.0.account.username', 'aichatou.mahamane2');

        $this->as($this->admin)->postJson("/api/admin/enrollment/parents/{$parent->id}/account")
            ->assertStatus(422)
            ->assertJsonValidationErrors('account');
    }

    #[Test]
    public function it_uses_a_placeholder_email_when_the_parent_email_is_missing_or_taken(): void
    {
        TenantUser::factory()->create(['email' => 'taken@example.test']);
        $parent = ParentModel::factory()->create(['firstname' => 'Issa', 'lastname' => 'Souley', 'email' => 'taken@example.test']);

        $response = $this->as($this->admin)->postJson("/api/admin/enrollment/parents/{$parent->id}/account")->assertCreated();

        $this->assertStringStartsWith('issa.souley@', $response->json('data.account.email'));
    }

    #[Test]
    public function it_resets_the_password_and_revokes_sessions(): void
    {
        $oldPassword = $this->as($this->admin)->postJson($this->studentUri('/account'))->json('data.password');
        $account = TenantUser::query()->findOrFail($this->student->fresh()->user_id);
        $account->createToken('session');

        $response = $this->as($this->admin)->postJson($this->studentUri('/account/reset-password'));

        $response->assertOk()->assertJsonPath('data.account.id', $account->id);
        $newPassword = $response->json('data.password');
        $this->assertNotSame($oldPassword, $newPassword);
        $this->assertTrue(Hash::check($newPassword, $account->fresh()->password));
        $this->assertSame(0, $account->tokens()->count());
    }

    #[Test]
    public function it_deactivates_and_reactivates_an_account(): void
    {
        $password = $this->as($this->admin)->postJson($this->studentUri('/account'))->json('data.password');
        $account = TenantUser::query()->findOrFail($this->student->fresh()->user_id);
        $account->createToken('session');

        $this->as($this->admin)->patchJson($this->studentUri('/account/status'), ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
        $this->assertFalse($account->fresh()->is_active);
        $this->assertSame(0, $account->tokens()->count());

        $this->postJson('/api/admin/auth/login', ['username' => $account->username, 'password' => $password, 'application' => 'frontend'])
            ->assertStatus(422);

        $this->as($this->admin)->patchJson($this->studentUri('/account/status'), ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->as($this->admin)->patchJson($this->studentUri('/account/status'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('is_active');
    }

    #[Test]
    public function it_handles_parent_account_reset_and_status(): void
    {
        $parent = ParentModel::factory()->create();
        $this->as($this->admin)->postJson("/api/admin/enrollment/parents/{$parent->id}/account")->assertCreated();

        $this->as($this->admin)->postJson("/api/admin/enrollment/parents/{$parent->id}/account/reset-password")
            ->assertOk()
            ->assertJsonStructure(['data' => ['account' => ['id', 'username'], 'password']]);

        $this->as($this->admin)->patchJson("/api/admin/enrollment/parents/{$parent->id}/account/status", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    #[Test]
    public function it_refuses_to_manage_missing_or_staff_accounts(): void
    {
        $this->as($this->admin)->postJson($this->studentUri('/account/reset-password'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('account');

        $staff = $this->userWithRole('Administrator');
        $parent = ParentModel::factory()->create(['user_id' => $staff->id]);

        $this->as($this->admin)->postJson("/api/admin/enrollment/parents/{$parent->id}/account/reset-password")
            ->assertStatus(422)
            ->assertJsonValidationErrors('account');
        $this->as($this->admin)->patchJson("/api/admin/enrollment/parents/{$parent->id}/account/status", ['is_active' => false])
            ->assertStatus(422);
        $this->assertTrue($staff->fresh()->is_active);
    }

    #[Test]
    public function a_manager_can_manage_parents_and_accounts(): void
    {
        $manager = $this->userWithRole('Manager');

        $this->as($manager)->postJson($this->studentUri('/parents'), $this->parentPayload())->assertCreated();
        $this->as($manager)->postJson($this->studentUri('/account'))->assertCreated();
        $this->as($manager)->postJson($this->studentUri('/account/reset-password'))->assertOk();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function forbiddenRoles(): array
    {
        return [
            'Professeur' => ['Professeur'],
            'Élève' => ['Élève'],
            'Parent' => ['Parent'],
            'Comptable' => ['Comptable'],
        ];
    }

    #[Test]
    #[DataProvider('forbiddenRoles')]
    public function other_roles_cannot_manage_parents_or_accounts(string $role): void
    {
        $user = $this->userWithRole($role);
        $parent = ParentModel::factory()->create();

        $this->as($user)->getJson($this->studentUri('/parents'))->assertForbidden();
        $this->as($user)->postJson($this->studentUri('/parents'), $this->parentPayload())->assertForbidden();
        $this->as($user)->postJson($this->studentUri('/parents/link'), ['parent_id' => $parent->id])->assertForbidden();
        $this->as($user)->deleteJson($this->studentUri('/parents/'.$parent->id))->assertForbidden();
        $this->as($user)->getJson('/api/admin/enrollment/parents')->assertForbidden();
        $this->as($user)->getJson($this->studentUri('/account'))->assertForbidden();
        $this->as($user)->postJson($this->studentUri('/account'))->assertForbidden();
        $this->as($user)->postJson($this->studentUri('/account/reset-password'))->assertForbidden();
        $this->as($user)->patchJson($this->studentUri('/account/status'), ['is_active' => false])->assertForbidden();
        $this->as($user)->postJson("/api/admin/enrollment/parents/{$parent->id}/account")->assertForbidden();

        $this->assertNull($this->student->fresh()->user_id);
    }
}
