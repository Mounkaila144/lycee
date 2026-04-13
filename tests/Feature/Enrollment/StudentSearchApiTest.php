<?php

namespace Tests\Feature\Enrollment;

use Modules\Enrollment\Entities\Student;
use Modules\UsersGuard\Entities\User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class StudentSearchApiTest extends TestCase
{
    use InteractsWithTenancy;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        // Disable tenancy middleware (required for tests)
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        // Create user and REAL token (TenantSanctumAuth requires Bearer token)
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * Make an authenticated JSON GET request
     */
    private function authGetJson(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->getJson($uri);
    }

    /**
     * Make an authenticated GET request (for file downloads)
     */
    private function authGet(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->get($uri);
    }

    #[Test]
    public function it_can_search_students_by_name(): void
    {
        Student::factory()->create(['firstname' => 'Moussa', 'lastname' => 'Ibrahim']);
        Student::factory()->create(['firstname' => 'Aminata', 'lastname' => 'Moussa']);
        Student::factory()->create(['firstname' => 'Ali', 'lastname' => 'Diallo']);

        $response = $this->authGetJson('/api/admin/enrollment/students?search=Moussa');

        $response->assertOk();
        $this->assertEquals(2, $response->json('meta.total'));
    }

    #[Test]
    public function it_can_search_students_by_matricule(): void
    {
        Student::factory()->create(['matricule' => '2025-INF-001']);
        Student::factory()->create(['matricule' => '2025-INF-002']);
        Student::factory()->create(['matricule' => '2025-ECO-001']);

        $response = $this->authGetJson('/api/admin/enrollment/students?search=2025-INF');

        $response->assertOk();
        $this->assertEquals(2, $response->json('meta.total'));
    }

    #[Test]
    public function it_can_filter_by_status(): void
    {
        Student::factory()->count(3)->create(['status' => 'Actif']);
        Student::factory()->count(2)->create(['status' => 'Suspendu']);

        $response = $this->authGetJson('/api/admin/enrollment/students?status=Actif');

        $response->assertOk();
        $this->assertEquals(3, $response->json('meta.total'));
    }

    #[Test]
    public function it_can_filter_by_sex(): void
    {
        Student::factory()->count(4)->create(['sex' => 'M']);
        Student::factory()->count(2)->create(['sex' => 'F']);

        $response = $this->authGetJson('/api/admin/enrollment/students?sex=F');

        $response->assertOk();
        $this->assertEquals(2, $response->json('meta.total'));
    }

    #[Test]
    public function it_can_filter_by_nationality(): void
    {
        Student::factory()->count(3)->create(['nationality' => 'Niger']);
        Student::factory()->count(2)->create(['nationality' => 'Nigeria']);

        $response = $this->authGetJson('/api/admin/enrollment/students?nationality=Niger');

        $response->assertOk();
        $this->assertEquals(3, $response->json('meta.total'));
    }

    #[Test]
    public function it_can_sort_students(): void
    {
        Student::factory()->create(['lastname' => 'Zara']);
        Student::factory()->create(['lastname' => 'Ali']);
        Student::factory()->create(['lastname' => 'Mohamed']);

        $response = $this->authGetJson('/api/admin/enrollment/students?sort_by=lastname&sort_order=asc');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals('Ali', $data[0]['lastname']);
    }

    #[Test]
    public function it_can_paginate_results(): void
    {
        Student::factory()->count(25)->create();

        $response = $this->authGetJson('/api/admin/enrollment/students?per_page=10');

        $response->assertOk();
        $this->assertEquals(10, count($response->json('data')));
        $this->assertEquals(25, $response->json('meta.total'));
        $this->assertEquals(3, $response->json('meta.last_page'));
    }

    #[Test]
    public function it_can_autocomplete_by_matricule(): void
    {
        Student::factory()->create(['matricule' => '2025-INF-001', 'firstname' => 'Ali', 'lastname' => 'Diallo']);
        Student::factory()->create(['matricule' => '2025-INF-002', 'firstname' => 'Moussa', 'lastname' => 'Ibrahim']);
        Student::factory()->create(['matricule' => '2025-ECO-001', 'firstname' => 'Aminata', 'lastname' => 'Diallo']);

        $response = $this->authGetJson('/api/admin/enrollment/students/search/autocomplete?q=2025-INF');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertArrayHasKey('label', $data[0]);
        $this->assertStringContains('2025-INF', $data[0]['label']);
    }

    #[Test]
    public function it_can_autocomplete_by_name(): void
    {
        Student::factory()->create(['matricule' => '2025-001', 'firstname' => 'Moussa', 'lastname' => 'Ibrahim']);
        Student::factory()->create(['matricule' => '2025-002', 'firstname' => 'Ali', 'lastname' => 'Moussa']);
        Student::factory()->create(['matricule' => '2025-003', 'firstname' => 'Aminata', 'lastname' => 'Diallo']);

        $response = $this->authGetJson('/api/admin/enrollment/students/search/autocomplete?q=Moussa');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    #[Test]
    public function it_requires_minimum_query_length_for_autocomplete(): void
    {
        $response = $this->authGetJson('/api/admin/enrollment/students/search/autocomplete?q=a');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['q']);
    }

    #[Test]
    public function it_limits_autocomplete_results(): void
    {
        Student::factory()->count(25)->create(['firstname' => 'Test']);

        $response = $this->authGetJson('/api/admin/enrollment/students/search/autocomplete?q=Test&limit=5');

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
    }

    #[Test]
    public function it_can_export_students_to_excel(): void
    {
        Student::factory()->count(5)->create();

        $response = $this->authGet('/api/admin/enrollment/students/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    #[Test]
    public function it_can_export_filtered_students(): void
    {
        Student::factory()->count(3)->create(['status' => 'Actif']);
        Student::factory()->count(2)->create(['status' => 'Suspendu']);

        $response = $this->authGet('/api/admin/enrollment/students/export?status=Actif');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    #[Test]
    public function it_requires_authentication_for_search(): void
    {
        $response = $this->getJson('/api/admin/enrollment/students');

        $response->assertStatus(401);
    }

    #[Test]
    public function it_requires_authentication_for_autocomplete(): void
    {
        $response = $this->getJson('/api/admin/enrollment/students/search/autocomplete?q=test');

        $response->assertStatus(401);
    }

    #[Test]
    public function it_requires_authentication_for_export(): void
    {
        $response = $this->get('/api/admin/enrollment/students/export');

        $response->assertStatus(401);
    }

    /**
     * Helper to check if string contains substring
     */
    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '$haystack' contains '$needle'"
        );
    }
}
