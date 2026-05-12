<?php

namespace Tests\Feature\Enrollment;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Enrollment\Entities\Student;
use Modules\UsersGuard\Entities\TenantUser as User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Story 7.1 — Création d'un Élève (Étape 1 : données personnelles).
 *
 * Couvre les AC 1-6 du PRD Inscriptions §1.1 / Story 7.1.
 */
class StudentStoreApiTest extends TestCase
{
    use InteractsWithTenancy;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        $this->user = User::factory()->create();
        $this->assignRole($this->user, 'Administrator');
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        Storage::fake('tenant');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function authPost(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)
            ->withHeader('Accept', 'application/json')
            ->post($uri, $data);
    }

    private function authPostJson(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->postJson($uri, $data);
    }

    private function minimalPayload(array $overrides = []): array
    {
        return array_merge([
            'firstname' => 'Aïssa',
            'lastname' => 'Maïga',
            'birthdate' => '2012-09-15',
            'sex' => 'M',
        ], $overrides);
    }

    #[Test]
    public function admin_can_create_student_with_minimum_fields(): void
    {
        $response = $this->authPostJson('/api/admin/students', $this->minimalPayload());

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'firstname', 'lastname', 'birthdate', 'sex', 'matricule', 'status'],
            ])
            ->assertJsonPath('data.matricule', null)
            ->assertJsonPath('data.status', 'Actif')
            ->assertJsonPath('data.nationality', 'Nigérienne')
            ->assertJsonPath('data.city', 'Niamey');

        $this->assertDatabaseHas('students', [
            'firstname' => 'Aïssa',
            'lastname' => 'Maïga',
            'matricule' => null,
            'status' => 'Actif',
        ], 'tenant');
    }

    #[Test]
    public function admin_can_create_student_with_all_optional_fields(): void
    {
        $payload = $this->minimalPayload([
            'birthplace' => 'Tahoua',
            'nationality' => 'Nigérienne',
            'phone' => '+22790000000',
            'address' => 'Quartier Plateau',
            'city' => 'Niamey',
            'quarter' => 'Plateau',
            'blood_group' => 'O+',
            'health_notes' => 'Aucune allergie connue.',
            'emergency_contact_name' => 'Papa Maïga',
            'emergency_contact_phone' => '+22790000001',
        ]);

        $response = $this->authPostJson('/api/admin/students', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.blood_group', 'O+')
            ->assertJsonPath('data.quarter', 'Plateau')
            ->assertJsonPath('data.health_notes', 'Aucune allergie connue.');
    }

    #[Test]
    public function it_validates_required_fields(): void
    {
        $response = $this->authPostJson('/api/admin/students', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['firstname', 'lastname', 'birthdate', 'sex']);
    }

    #[Test]
    public function it_validates_birthdate_must_be_in_past(): void
    {
        $response = $this->authPostJson('/api/admin/students', $this->minimalPayload([
            'birthdate' => now()->addDay()->format('Y-m-d'),
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['birthdate']);
    }

    #[Test]
    public function it_validates_sex_enum(): void
    {
        $response = $this->authPostJson('/api/admin/students', $this->minimalPayload([
            'sex' => 'X',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sex']);
    }

    #[Test]
    public function it_validates_photo_mime(): void
    {
        $response = $this->authPost('/api/admin/students', $this->minimalPayload([
            'photo' => UploadedFile::fake()->create('not-image.pdf', 100, 'application/pdf'),
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);
    }

    #[Test]
    public function it_validates_photo_size_limit(): void
    {
        $response = $this->authPost('/api/admin/students', $this->minimalPayload([
            'photo' => UploadedFile::fake()->image('big-photo.jpg')->size(3000),
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);
    }

    #[Test]
    public function it_warns_on_duplicate_student(): void
    {
        $existing = Student::factory()->create([
            'firstname' => 'Ali',
            'lastname' => 'Souley',
            'birthdate' => '2011-06-10',
        ]);

        $response = $this->authPostJson('/api/admin/students', [
            'firstname' => 'Ali',
            'lastname' => 'Souley',
            'birthdate' => '2011-06-10',
            'sex' => 'M',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('code', 'STUDENT_DUPLICATE_FOUND')
            ->assertJsonPath('duplicate.id', $existing->id);
    }

    #[Test]
    public function it_allows_duplicate_when_force_true(): void
    {
        Student::factory()->create([
            'firstname' => 'Mariam',
            'lastname' => 'Adamou',
            'birthdate' => '2010-03-20',
        ]);

        $response = $this->authPostJson('/api/admin/students', [
            'firstname' => 'Mariam',
            'lastname' => 'Adamou',
            'birthdate' => '2010-03-20',
            'sex' => 'F',
            'force' => true,
        ]);

        $response->assertStatus(201);

        $this->assertSame(2, Student::on('tenant')
            ->where('firstname', 'Mariam')
            ->where('lastname', 'Adamou')
            ->whereDate('birthdate', '2010-03-20')
            ->count());
    }

    #[Test]
    public function it_stores_photo_on_tenant_disk(): void
    {
        $response = $this->authPost('/api/admin/students', $this->minimalPayload([
            'photo' => UploadedFile::fake()->image('photo.jpg', 200, 200)->size(500),
        ]));

        $response->assertStatus(201);

        $path = $response->json('data.photo');
        $this->assertNotNull($path);
        Storage::disk('tenant')->assertExists($path);
    }

    #[Test]
    public function unauthenticated_user_cannot_create_student(): void
    {
        $response = $this->postJson('/api/admin/students', $this->minimalPayload());

        $response->assertStatus(401);
    }
}
