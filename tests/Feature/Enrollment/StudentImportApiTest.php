<?php

namespace Tests\Feature\Enrollment;

use Illuminate\Http\UploadedFile;
use Modules\Enrollment\Entities\Student;
use Modules\UsersGuard\Entities\TenantUser as User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Import CSV des élèves (secondaire) : colonnes obligatoires nom, prenom,
 * date_naissance, sexe. Doublons détectés sur nom + prénom + date de naissance.
 */
class StudentImportApiTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function authGet(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->get($uri);
    }

    private function authPostJson(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->postJson($uri, $data);
    }

    private function authPost(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->post($uri, $data);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string>>  $rows
     */
    private function createCsvContent(array $headers, array $rows): string
    {
        $lines = [implode(';', $headers)];
        foreach ($rows as $row) {
            $lines[] = implode(';', $row);
        }

        return implode("\n", $lines);
    }

    private function createCsvFile(string $content, string $filename = 'test.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($filename, $content);
    }

    private function previewCsv(array $headers, array $rows): \Illuminate\Testing\TestResponse
    {
        return $this->authPost('/api/admin/enrollment/students/import/preview', [
            'file' => $this->createCsvFile($this->createCsvContent($headers, $rows)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validRow(int $rowNumber, array $overrides = []): array
    {
        return array_merge([
            'row_number' => $rowNumber,
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'date_naissance' => '15/03/2010',
            'sexe' => 'M',
            'is_valid' => true,
            'errors' => [],
        ], $overrides);
    }

    // ==================== TEMPLATE ====================

    #[Test]
    public function it_can_download_import_template(): void
    {
        $response = $this->authGet('/api/admin/enrollment/students/import/template');

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('template_import_etudiants.csv', $response->headers->get('content-disposition'));

        $content = (string) file_get_contents($response->baseResponse->getFile()->getPathname());
        $this->assertStringContainsString('nom;prenom;date_naissance;sexe', $content);
        $this->assertStringNotContainsString('email', $content);
        $this->assertStringNotContainsString('programme', $content);
    }

    #[Test]
    public function it_requires_authentication_for_template_download(): void
    {
        $this->getJson('/api/admin/enrollment/students/import/template')->assertStatus(401);
    }

    // ==================== PREVIEW ====================

    #[Test]
    public function it_can_preview_valid_csv_file_without_email(): void
    {
        $response = $this->previewCsv(
            ['nom', 'prenom', 'date_naissance', 'sexe'],
            [
                ['Dupont', 'Jean', '15/03/2010', 'M'],
                ['Martin', 'Marie', '20/05/2011', 'F'],
            ]
        );

        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'data' => ['rows', 'valid_count', 'error_count', 'headers'],
        ]);

        $this->assertEquals(2, $response->json('data.valid_count'));
        $this->assertEquals(0, $response->json('data.error_count'));
    }

    #[Test]
    public function it_detects_missing_required_columns(): void
    {
        $response = $this->previewCsv(['nom', 'prenom'], [['Dupont', 'Jean']]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.headers.0', fn ($error) => str_contains($error, 'Colonnes obligatoires manquantes'));
        $this->assertStringNotContainsString('email', $response->json('errors.headers.0'));
    }

    #[Test]
    public function it_detects_duplicate_students_in_file(): void
    {
        $response = $this->previewCsv(
            ['nom', 'prenom', 'date_naissance', 'sexe'],
            [
                ['Dupont', 'Jean', '15/03/2010', 'M'],
                ['dupont', 'jean', '2010-03-15', 'M'],
            ]
        );

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.valid_count'));
        $this->assertEquals(1, $response->json('data.error_count'));
        $this->assertFalse($response->json('data.rows.1.is_valid'));
    }

    #[Test]
    public function it_detects_student_already_in_database(): void
    {
        Student::factory()->withMatricule('2026-0007')->create([
            'firstname' => 'Jean',
            'lastname' => 'Dupont',
            'birthdate' => '2010-03-15',
        ]);

        $response = $this->previewCsv(
            ['nom', 'prenom', 'date_naissance', 'sexe'],
            [['Dupont', 'Jean', '15/03/2010', 'M']]
        );

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.valid_count'));
        $row = $response->json('data.rows.0');
        $this->assertFalse($row['is_valid']);
        $this->assertTrue(collect($row['errors'])->contains(fn ($e) => str_contains($e, '2026-0007')));
    }

    #[Test]
    public function it_detects_invalid_date_format(): void
    {
        $response = $this->previewCsv(
            ['nom', 'prenom', 'date_naissance', 'sexe'],
            [['Dupont', 'Jean', 'invalid-date', 'M']]
        );

        $response->assertOk();
        $row = $response->json('data.rows.0');
        $this->assertFalse($row['is_valid']);
        $this->assertTrue(collect($row['errors'])->contains(fn ($e) => str_contains($e, 'date')));
    }

    #[Test]
    public function it_detects_invalid_sex_value(): void
    {
        $response = $this->previewCsv(
            ['nom', 'prenom', 'date_naissance', 'sexe'],
            [['Dupont', 'Jean', '15/03/2010', 'O']]
        );

        $response->assertOk();
        $row = $response->json('data.rows.0');
        $this->assertFalse($row['is_valid']);
        $this->assertContains('Sexe invalide (utilisez M ou F)', $row['errors']);
    }

    #[Test]
    public function it_ignores_legacy_programme_column(): void
    {
        $response = $this->previewCsv(
            ['nom', 'prenom', 'date_naissance', 'sexe', 'email', 'programme'],
            [['Dupont', 'Jean', '15/03/2010', 'M', 'jean@test.com', 'INCONNU']]
        );

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.valid_count'));
    }

    #[Test]
    public function it_requires_authentication_for_preview(): void
    {
        $file = $this->createCsvFile($this->createCsvContent(
            ['nom', 'prenom', 'date_naissance', 'sexe'],
            [['Dupont', 'Jean', '15/03/2010', 'M']]
        ));

        $this->postJson('/api/admin/enrollment/students/import/preview', ['file' => $file])
            ->assertStatus(401);
    }

    // ==================== CONFIRM ====================

    #[Test]
    public function it_can_import_valid_rows(): void
    {
        $response = $this->authPostJson('/api/admin/enrollment/students/import/confirm', [
            'rows' => [
                $this->validRow(2),
                $this->validRow(3, ['nom' => 'Martin', 'prenom' => 'Marie', 'date_naissance' => '20/05/2011', 'sexe' => 'F']),
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.imported_count', 2);

        $this->assertDatabaseHas('students', ['firstname' => 'Jean', 'lastname' => 'Dupont', 'birthdate' => '2010-03-15'], 'tenant');
        $this->assertDatabaseHas('students', ['firstname' => 'Marie', 'lastname' => 'Martin', 'sex' => 'F'], 'tenant');
    }

    #[Test]
    public function it_generates_matricules_for_imported_students(): void
    {
        $response = $this->authPostJson('/api/admin/enrollment/students/import/confirm', [
            'rows' => [$this->validRow(2), $this->validRow(3, ['prenom' => 'Paul'])],
        ]);

        $response->assertStatus(201);

        $first = $response->json('data.imported_students.0.matricule');
        $second = $response->json('data.imported_students.1.matricule');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{4}$/', $first);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{4}$/', $second);
        $this->assertNotSame($first, $second);
    }

    #[Test]
    public function it_skips_invalid_rows_during_import(): void
    {
        $response = $this->authPostJson('/api/admin/enrollment/students/import/confirm', [
            'rows' => [
                $this->validRow(2),
                $this->validRow(3, ['nom' => 'Martin', 'prenom' => 'Marie', 'date_naissance' => 'invalid-date', 'is_valid' => false, 'errors' => ['Date invalide']]),
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.imported_count', 1);
        $this->assertDatabaseHas('students', ['lastname' => 'Dupont'], 'tenant');
        $this->assertDatabaseMissing('students', ['lastname' => 'Martin'], 'tenant');
    }

    #[Test]
    public function it_revalidates_rows_server_side_even_if_client_marks_them_valid(): void
    {
        Student::factory()->create([
            'firstname' => 'Jean',
            'lastname' => 'Dupont',
            'birthdate' => '2010-03-15',
        ]);

        $response = $this->authPostJson('/api/admin/enrollment/students/import/confirm', [
            'rows' => [
                $this->validRow(2),
                $this->validRow(3, ['nom' => 'Sani', 'sexe' => 'X']),
                $this->validRow(4, ['nom' => 'Moussa', 'prenom' => 'Aïcha', 'sexe' => 'F']),
                $this->validRow(5, ['nom' => 'Moussa', 'prenom' => 'Aïcha', 'sexe' => 'F']),
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.imported_count', 1);
        $this->assertCount(3, $response->json('data.errors'));
        $this->assertSame(1, Student::on('tenant')->where('lastname', 'Moussa')->count());
        $this->assertSame(1, Student::on('tenant')->where('lastname', 'Dupont')->count());
        $this->assertDatabaseMissing('students', ['lastname' => 'Sani'], 'tenant');
    }

    #[Test]
    public function it_returns_error_when_no_valid_rows(): void
    {
        $response = $this->authPostJson('/api/admin/enrollment/students/import/confirm', [
            'rows' => [$this->validRow(2, ['date_naissance' => 'invalid', 'is_valid' => false, 'errors' => ['Erreurs multiples']])],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Aucune ligne valide à importer');
    }

    #[Test]
    public function it_requires_authentication_for_confirm(): void
    {
        $this->postJson('/api/admin/enrollment/students/import/confirm', ['rows' => [$this->validRow(2)]])
            ->assertStatus(401);
    }

    #[Test]
    public function professeur_cannot_import_students(): void
    {
        $teacher = User::factory()->create();
        $this->assignRole($teacher, 'Professeur');
        $teacherToken = $teacher->createToken('test-token')->plainTextToken;

        $this->withToken($teacherToken)
            ->postJson('/api/admin/enrollment/students/import/confirm', ['rows' => [$this->validRow(2)]])
            ->assertForbidden();

        $this->assertDatabaseMissing('students', ['lastname' => 'Dupont'], 'tenant');
    }

    #[Test]
    public function it_sets_default_values_for_imported_students(): void
    {
        $this->authPostJson('/api/admin/enrollment/students/import/confirm', [
            'rows' => [$this->validRow(2)],
        ])->assertStatus(201);

        $student = Student::on('tenant')->where('lastname', 'Dupont')->first();
        $this->assertEquals('Actif', $student->status);
        $this->assertEquals('Nigérienne', $student->nationality);
        $this->assertEquals('Niamey', $student->city);
    }

    #[Test]
    public function it_handles_optional_fields(): void
    {
        $this->authPostJson('/api/admin/enrollment/students/import/confirm', [
            'rows' => [$this->validRow(2, [
                'telephone' => '+22790123456',
                'adresse' => '123 Rue Example',
                'ville' => 'Zinder',
                'quartier' => 'Birni',
                'nationalite' => 'Nigérienne',
                'lieu_naissance' => 'Maradi',
                'email' => 'ignored@test.com',
                'pays' => 'Niger',
            ])],
        ])->assertStatus(201);

        $student = Student::on('tenant')->where('lastname', 'Dupont')->first();
        $this->assertEquals('+22790123456', $student->phone);
        $this->assertEquals('123 Rue Example', $student->address);
        $this->assertEquals('Zinder', $student->city);
        $this->assertEquals('Birni', $student->quarter);
        $this->assertEquals('Maradi', $student->birthplace);
    }

    #[Test]
    public function it_uses_mobile_as_phone_fallback(): void
    {
        $this->authPostJson('/api/admin/enrollment/students/import/confirm', [
            'rows' => [$this->validRow(2, ['mobile' => '+22796123456'])],
        ])->assertStatus(201);

        $this->assertDatabaseHas('students', ['lastname' => 'Dupont', 'phone' => '+22796123456'], 'tenant');
    }

    // ==================== REVALIDATE ROW ====================

    #[Test]
    public function it_can_revalidate_corrected_row(): void
    {
        $row = $this->validRow(2, ['is_valid' => false, 'errors' => ['Sexe invalide']]);

        $response = $this->authPostJson('/api/admin/enrollment/students/import/revalidate-row', [
            'row' => $row,
            'all_rows' => [$row],
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('data.is_valid'));
        $this->assertEmpty($response->json('data.errors'));
    }

    #[Test]
    public function it_detects_errors_in_revalidation(): void
    {
        $row = $this->validRow(2, ['sexe' => 'X']);

        $response = $this->authPostJson('/api/admin/enrollment/students/import/revalidate-row', [
            'row' => $row,
            'all_rows' => [$row],
        ]);

        $response->assertOk();
        $this->assertFalse($response->json('data.is_valid'));
        $this->assertNotEmpty($response->json('data.errors'));
    }

    #[Test]
    public function it_detects_duplicate_with_other_rows_in_revalidation(): void
    {
        $first = $this->validRow(2);
        $second = $this->validRow(3);

        $response = $this->authPostJson('/api/admin/enrollment/students/import/revalidate-row', [
            'row' => $second,
            'all_rows' => [$first, $second],
        ]);

        $response->assertOk();
        $this->assertFalse($response->json('data.is_valid'));
    }

    #[Test]
    public function it_requires_authentication_for_revalidate(): void
    {
        $row = $this->validRow(2);

        $this->postJson('/api/admin/enrollment/students/import/revalidate-row', [
            'row' => $row,
            'all_rows' => [$row],
        ])->assertStatus(401);
    }
}
