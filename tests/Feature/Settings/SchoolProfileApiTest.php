<?php

namespace Tests\Feature\Settings;

use Illuminate\Http\UploadedFile;
use Modules\Settings\Entities\TenantSetting;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class SchoolProfileApiTest extends TestCase
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

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->getJson('/api/admin/settings/school-profile')->assertStatus(401);
    }

    #[Test]
    public function it_returns_an_empty_profile_initially(): void
    {
        $this->authGetJson('/api/admin/settings/school-profile')
            ->assertOk()
            ->assertJsonPath('data.name', null)
            ->assertJsonPath('data.logo', null);
    }

    #[Test]
    public function it_saves_the_school_identity_fields(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/admin/settings/school-profile', [
            'name' => 'Lycée Jandoo',
            'nif' => 'NIF-123',
            'address' => 'Avenue X',
            'city' => 'Niamey',
            'phone' => '+227 20 00 00 00',
            'email' => 'contact@jandoo.ne',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Lycée Jandoo')
            ->assertJsonPath('data.nif', 'NIF-123');

        $this->assertSame('Lycée Jandoo', TenantSetting::query()->where('key', 'school.name')->value('value'));
        $this->assertSame('school', TenantSetting::query()->where('key', 'school.nif')->value('category'));
    }

    #[Test]
    public function it_stores_the_logo_as_a_base64_data_uri(): void
    {
        $response = $this->withToken($this->token)->post('/api/admin/settings/school-profile', [
            'name' => 'Lycée Logo',
            'logo' => UploadedFile::fake()->image('logo.png', 32, 32),
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $logo = $response->json('data.logo');
        $this->assertIsString($logo);
        $this->assertStringStartsWith('data:image/png;base64,', $logo);

        $this->assertSame('file', TenantSetting::query()->where('key', 'school.logo')->value('type'));
    }

    #[Test]
    public function it_rejects_a_non_image_logo(): void
    {
        $response = $this->withToken($this->token)->post('/api/admin/settings/school-profile', [
            'logo' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
    }
}
