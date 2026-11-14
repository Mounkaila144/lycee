<?php

namespace Tests\Feature\Settings;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Modules\Settings\Entities\TenantSetting;
use Modules\Settings\Services\SchoolProfileService;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Profil de l'établissement : GET pour tout utilisateur authentifié, écriture Administrator.
 */
class SchoolProfileApiTest extends TestCase
{
    use InteractsWithTenancy;

    private const URI = '/api/admin/settings/school-profile';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function tokenFor(string $role): string
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, $role);

        return $user->createToken('test-token')->plainTextToken;
    }

    private function getAs(string $role, string $uri = self::URI): TestResponse
    {
        return $this->withToken($this->tokenFor($role))->getJson($uri);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function putAs(string $role, array $data): TestResponse
    {
        return $this->withToken($this->tokenFor($role))->putJson(self::URI, $data);
    }

    #[Test]
    public function it_returns_niger_defaults_when_nothing_is_configured(): void
    {
        $this->getAs('Administrator')
            ->assertOk()
            ->assertJsonPath('data.name', null)
            ->assertJsonPath('data.is_configured', false)
            ->assertJsonPath('data.type', 'Lycée')
            ->assertJsonPath('data.country', 'Niger')
            ->assertJsonPath('data.currency', 'FCFA')
            ->assertJsonPath('data.head_title', 'Le Proviseur')
            ->assertJsonPath('data.official_header', SchoolProfileService::DEFAULT_OFFICIAL_HEADER)
            ->assertJsonPath('data.has_logo', false)
            ->assertJsonPath('data.has_stamp', false);
    }

    #[Test]
    public function admin_updates_the_profile(): void
    {
        $this->putAs('Administrator', [
            'name' => '  Lycée Issa Korombé  ',
            'short_name' => 'LIK',
            'type' => 'Lycée',
            'motto' => 'Travail – Discipline – Réussite',
            'region' => 'DREN de Niamey',
            'inspection' => 'Inspection Niamey 2',
            'city' => 'Niamey',
            'po_box' => '1234',
            'phone' => '+227 20 00 00 00',
            'email' => 'contact@lycee.ne',
            'head_name' => 'M. Moussa Ali',
            'censor_name' => 'Mme Aïcha Hamani',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Lycée Issa Korombé')
            ->assertJsonPath('data.display_name', 'Lycée Issa Korombé')
            ->assertJsonPath('data.is_configured', true)
            ->assertJsonPath('data.region', 'DREN de Niamey');

        $this->assertSame('Lycée Issa Korombé', TenantSetting::query()->where('key', 'school.name')->value('value'));
        $this->assertSame('school', TenantSetting::query()->where('key', 'school.city')->value('category'));

        $this->putAs('Administrator', ['motto' => ''])->assertOk()->assertJsonPath('data.motto', null)
            ->assertJsonPath('data.city', 'Niamey');
    }

    #[Test]
    public function every_authenticated_role_can_read_the_profile(): void
    {
        app(SchoolProfileService::class)->update(['name' => 'Lycée de Test']);

        foreach (['Manager', 'Professeur', 'Élève', 'Parent', 'Caissier', 'Comptable'] as $role) {
            $this->getAs($role)->assertOk()->assertJsonPath('data.name', 'Lycée de Test');
        }
    }

    #[Test]
    public function reading_requires_authentication(): void
    {
        $this->getJson(self::URI)->assertUnauthorized();
    }

    #[Test]
    public function only_the_administrator_can_write(): void
    {
        foreach (['Manager', 'Professeur', 'Élève', 'Comptable'] as $role) {
            $this->putAs($role, ['name' => 'Piratage'])->assertForbidden();
        }

        $this->withToken($this->tokenFor('Manager'))
            ->post(self::URI.'/logo', ['file' => UploadedFile::fake()->image('logo.png')], ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->assertNull(TenantSetting::query()->where('key', 'school.name')->value('value'));
    }

    #[Test]
    public function it_validates_the_payload(): void
    {
        $this->putAs('Administrator', [
            'type' => 'Université',
            'email' => 'pas-un-email',
            'name' => str_repeat('x', 200),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'email', 'name']);
    }

    #[Test]
    public function admin_uploads_serves_and_deletes_the_logo(): void
    {
        $token = $this->tokenFor('Administrator');

        $this->withToken($token)
            ->post(self::URI.'/logo', ['file' => UploadedFile::fake()->image('logo.jpg', 1200, 800)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.has_logo', true);

        $path = TenantSetting::query()->where('key', 'school.logo_path')->value('value');
        Storage::disk('local')->assertExists($path);
        [$width, $height] = getimagesizefromstring(Storage::disk('local')->get($path));
        $this->assertSame([600, 400], [$width, $height]);

        $response = $this->withToken($this->tokenFor('Élève'))->get(self::URI.'/logo');
        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('content-type'));

        $this->assertStringStartsWith('data:image/png;base64,', (string) app(SchoolProfileService::class)->imageDataUri('logo'));

        $this->withToken($token)->deleteJson(self::URI.'/logo')->assertOk()->assertJsonPath('data.has_logo', false);
        Storage::disk('local')->assertMissing($path);
        $this->withToken($token)->get(self::URI.'/logo', ['Accept' => 'application/json'])->assertNotFound();
    }

    #[Test]
    public function upload_rejects_non_images_and_unknown_slots(): void
    {
        $token = $this->tokenFor('Administrator');

        $this->withToken($token)
            ->post(self::URI.'/stamp', ['file' => UploadedFile::fake()->create('cachet.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);

        $this->withToken($token)
            ->post(self::URI.'/banner', ['file' => UploadedFile::fake()->image('x.png')], ['Accept' => 'application/json'])
            ->assertNotFound();
    }

    #[Test]
    public function documents_header_uses_the_profile(): void
    {
        $service = app(SchoolProfileService::class);
        $service->update(['name' => 'CEG 3 de Zinder', 'city' => 'Zinder', 'po_box' => '45', 'phone' => '96 00 00 00', 'region' => 'DREN de Zinder']);
        $service->storeImage('logo', UploadedFile::fake()->image('logo.png', 100, 100));

        $school = $service->forDocuments();
        $this->assertSame('BP 45', $school['po_box_label']);
        $this->assertSame(['RÉPUBLIQUE DU NIGER', 'Fraternité – Travail – Progrès', "MINISTÈRE DE L'ÉDUCATION NATIONALE"], $school['header_lines']);

        $html = view('settings::pdf.header', ['school' => $school])->render();
        $this->assertStringContainsString('CEG 3 de Zinder', $html);
        $this->assertStringContainsString('DREN de Zinder', $html);
        $this->assertStringContainsString('RÉPUBLIQUE DU NIGER', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);

        $variables = $service->templateVariables();
        $this->assertSame('CEG 3 de Zinder', $variables['school_name']);
        $this->assertSame('Zinder', $variables['issue_place']);
    }

    #[Test]
    public function legacy_name_setting_is_used_as_fallback(): void
    {
        TenantSetting::query()->create(['key' => 'school_name', 'value' => 'Ancien Nom', 'type' => 'string']);

        $this->getAs('Administrator')
            ->assertOk()
            ->assertJsonPath('data.name', null)
            ->assertJsonPath('data.display_name', 'Ancien Nom');
    }
}
