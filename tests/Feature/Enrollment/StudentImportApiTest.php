<?php

namespace Tests\Feature\Enrollment;

use Illuminate\Http\UploadedFile;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\Programme;
use Modules\UsersGuard\Entities\User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class StudentImportApiTest extends TestCase
{
    use InteractsWithTenancy;

    private User $user;

    private string $token;

    private Programme $programme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        $this->programme = Programme::factory()->create([
            'code' => 'LINF',
            'libelle' => 'Licence Informatique',
        ]);
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
     * Create a CSV content string
     */
    private function createCsvContent(array $headers, array $rows): string
    {
        $lines = [implode(';', $headers)];
        foreach ($rows as $row) {
            $lines[] = implode(';', $row);
        }

        return implode("\n", $lines);
    }

    /**
     * Create a temporary CSV file
     */
    private function createCsvFile(string $content, string $filename = 'test.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($filename, $content);
    }

    // ==================== TEMPLATE DOWNLOAD TESTS ====================

    #[Test]
    public function it_can_download_import_template(): void
    {
        $response = $this->authGet('/api/admin/enrollment/students/import/template');

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('template_import_etudiants.csv', $response->headers->get('content-disposition'));
    }

    #[Test]
    public function it_requires_authentication_for_template_download(): void
    {
        $response = $this->get('/api/admin/enrollment/students/import/template');

        $response->assertStatus(401);
    }

    // ==================== PREVIEW TESTS ====================

    #[Test]
    public function it_can_preview_valid_csv_file(): void
    {
        $csvContent = $this->createCsvContent(
            ['nom', 'prenom', 'date_naissance', 'sexe', 'email'],
            [
                ['Dupont', 'Jean', '15/03/2005', 'M', 'jean.dupont@test.com'],
                ['Martin', 'Marie', '20/05/2004', 'F', 'marie.martin@test.com'],
            ]
        );

        $file = $this->createCsvFile($csvContent);

        $response = $this->authPost('/api/admin/enrollment/students/import/preview', [
            'file' => $file,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'data' => [
                'rows',
                'valid_count',
                'error_count',
                'headers',
            ],
        ]);

        $this->assertEquals(2, $response->json('data.valid_count'));
        $this->assertEquals(0, $response->json('data.error_count'));
    }

    #[Test]
    public function it_detects_missing_required_columns(): void
    {
        $csvContent = $this->createCsvContent(
            ['nom', 'prenom'], // Missing date_naissance, sexe, email
            [
                ['Dupont', 'Jean'],
            ]
        );

        $file = $this->createCsvFile($csvContent);

        $response = $this->authPost('/api/admin/enrollment/students/import/preview', [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.headers.0', fn ($error) => str_contains($error, 'Colonnes obligatoires manquantes'));
    }

    #[Test]
    public function it_detects_invalid_email_format(): void
    {
        $csvContent = $this->createCsvContent(
            ['nom', 'prenom', 'date_naissance', 'sexe', 'email'],
            [
                ['Dupont', 'Jean', '15/03/2005', 'M', 'invalid-email'],
            ]
        );

        $file = $this->createCsvFile($csvContent);

        $response = $this->authPost('/api/admin/enrollment/students/import/preview', [
            'file' => $file,
        ]);

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.valid_count'));
        $this->assertEquals(1, $response->json('data.error_count'));

        $row = $response->json('data.rows.0');
        $this->assertFalse($row['is_valid']);
        $this->assertContains('Format d\'email invalide', $row['errors']);
    }

    #[Test]
    public function it_detects_duplicate_emails_in_file(): void
    {
        $csvContent = $this->createCsvContent(
            ['nom', 'prenom', 'date_naissance', 'sexe', 'email'],
            [
                ['Dupont', 'Jean', '15/03/2005', 'M', 'same@test.com'],
                ['Martin', 'Marie', '20/05/2004', 'F', 'same@test.com'],
            ]
        );

        $file = $this->createCsvFile($csvContent);

        $response = $this->authPost('/api/admin/enrollment/students/import/preview', [
            'file' => $file,
        ]);

        $response->assertOk();
        // First row is valid, second has duplicate
        $this->assertEquals(1, $response->json('data.valid_count'));
        $this->assertEquals(1, $response->json('data.error_count'));

        $secondRow = $response->json('data.rows.1');
        $this->assertFalse($secondRow['is_valid']);
    }

    #[Test]
    public function it_detects_email_already_exists_in_database(): void
    {
        // Create existing student
        Student::factory()->create(['email' => 'existing@test.com']);

        $csvContent = $this->createCsvContent(
            ['nom', 'prenom', 'date_naissance', 'sexe', 'email'],
            [
                ['Dupont', 'Jean', '15/03/2005', 'M', 'existing@test.com'],
            ]
        );

        $file = $this->createCsvFile($csvContent);

        $response = $this->authPost('/api/admin/enrollment/students/import/preview', [
            'file' => $file,
        ]);

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.valid_count'));
        $this->assertEquals(1, $response->json('data.error_count'));

        $row = $response->json('data.rows.0');
        $this->assertFalse($row['is_valid']);
    }

    #[Test]
    public function it_detects_invalid_date_format(): void
    {
        $csvContent = $this->createCsvContent(
            ['nom', 'prenom', 'date_naissance', 'sexe', 'email'],
            [
                ['Dupont', 'Jean', 'invalid-date', 'M', 'jean@test.com'],
            ]
        );

        $file = $this->createCsvFile($csvContent);

        $response = $this->authPost('/api/admin/enrollment/students/import/preview', [
            'file' => $file,
        ]);

        $response->assertOk();
        $row = $response->json('data.rows.0');
        $this->assertFalse($row['is_valid']);
        $this->assertTrue(
            collect($row['errors'])->contains(fn ($e) => str_contains($e, 'date'))
        );
    }

    #[Test]
    public function it_detects_invalid_sex_value(): void
    {
        $csvContent = $this->createCsvContent(
            ['nom', 'prenom', 'date_naissance', 'sexe', 'email'],
            [
                ['Dupont', 'Jean', '15/03/2005', 'X', 'jean@test.com'],
            ]
        );

        $file = $this->createCsvFile($csvContent);

        $response = $this->authPost('/api/admin/enrollment/students/import/preview', [
            'file' => $file,
        ]);

        $response->assertOk();
        $row = $response->json('data.rows.0');
        $this->assertFalse($row['is_valid']);
        $this->assertContains('Sexe invalide (utilisez M, F ou O)', $row['errors']);
    }

    #[Test]
    public function it_validates_programme_code_exists(): void
    {
        $csvContent = $this->createCsvContent(
            ['nom', 'prenom', 'date_naissance', 'sexe', 'email', 'programme'],
            [
                ['Dupont', 'Jean', '15/03/2005', 'M', 'jean@test.com', 'INVALID'],
            ]
        );

        $file = $this->createCsvFile($csvContent);

        $response = $this->authPost('/api/admin/enrollment/students/import/preview', [
            'file' => $file,
        ]);

        $response->assertOk();
        $row = $response->json('data.rows.0');
        $this->assertFalse($row['is_valid']);
    }

    #[Test]
    public function it_accepts_valid_programme_code(): void
    {
        $csvContent = $this->createCsvContent(
            ['nom', 'prenom', 'date_naissance', 'sexe', 'email', 'programme'],
            [
                ['Dupont', 'Jean', '15/03/2005', 'M', 'jean@test.com', 'LINF'],
            ]
        );

        $file = $this->createCsvFile($csvContent);

        $response = $this->authPost('/api/admin/enrollment/students/import/preview', [
            'file' => $file,
        ]);

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.valid_count'));
    }

    #[Test]
    public function it_requires_authentication_for_preview(): void
    {
        $csvContent = $this->createCsvContent(
            ['nom', 'prenom', 'date_naissance', 'sexe', 'email'],
            [['Dupont', 'Jean', '15/03/2005', 'M', 'jean@test.com']]
        );

        $file = $this->createCsvFile($csvContent);

        $response = $this->post('/api/admin/enrollment/students/import/preview', [
            'file' => $file,
        ]);

        $response->assertStatus(401);
    }

    // ==================== CONFIRM IMPORT TESTS ====================

    #[Test]
    public function it_can_import_valid_rows(): void
    {
        $rows = [
            [
                'row_number' => 2,
                'nom' => 'Dupont',
                'prenom' => 'Jean',
                'date_naissance' => '15/03/2005',
                'sexe' => 'M',
                'email' => 'jean.dupont@test.com',
                'is_valid' => true,
                'errors' => [],
            ],
            [
                'row_number' => 3,
                'nom' => 'Martin',
                'prenom' => 'Marie',
                'date_naissance' => '20/05/2004',
                'sexe' => 'F',
                'email' => 'marie.martin@test.com',
                'is_valid' => true,
                'errors' => [],
            ],
        ];

        $response = $this->authPostJson('/api/admin/enrollment/students/import/confirm', [
            'rows' => $rows,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.imported_count', 2);

        // Verify students were created
        $this->assertDatabaseHas('students', [
            'email' => 'jean.dupont@test.com',
            'firstname' => 'Jean',
            'lastname' => 'Dupont',
        ], 'tenant');

        $this->assertDatabaseHas('students', [
            'email' => 'marie.martin@test.com',
            'firstname' => 'Marie',
            'lastname' => 'Martin',
        ], 'tenant');
    }

    #[Test]
    public function it_generates_matricules_for_imported_students(): void
    {
        $rows = [
            [
                'row_number' => 2,
                'nom' => 'Dupont',
                'prenom' => 'Jean',
                'date_naissance' => '15/03/2005',
                'sexe' => 'M',
                'email' => 'jean@test.com',
                'programme' => 'LINF',
                'is_valid' => true,
                'errors' => [],
            ],
        ];

        $response = $this->authPostJson('/api/admin/enrollment/students/import/confirm', [
            'rows' => $rows,
        ]);

        $response->assertStatus(201);

        $importedStudent = $response->json('data.imported_students.0');
        $this->assertNotEmpty($importedStudent['matricule']);
        $this->assertStringContainsString('LINF', $importedStudent['matricule']);
    }

    #[Test]
    public function it_skips_invalid_rows_during_import(): void
    {
        $rows = [
            [
                'row_number' => 2,
                'nom' => 'Dupont',
                'prenom' => 'Jean',
                'date_naissance' => '15/03/2005',
                'sexe' => 'M',
                'email' => 'jean@test.com',
                'is_valid' => true,
                'errors' => [],
            ],
            [
                'row_number' => 3,
                'nom' => 'Martin',
                'prenom' => 'Marie',
                'date_naissance' => 'invalid-date',
                'sexe' => 'F',
                'email' => 'marie@test.com',
                'is_valid' => false,
                'errors' => ['Date invalide'],
            ],
        ];

        $response = $this->authPostJson('/api/admin/enrollment/students/import/confirm', [
            'rows' => $rows,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.imported_count', 1);

        // Only valid student should be created
        $this->assertDatabaseHas('students', ['email' => 'jean@test.com'], 'tenant');
        $this->assertDatabaseMissing('students', ['email' => 'marie@test.com'], 'tenant');
    }

    #[Test]
    public function it_returns_error_when_no_valid_rows(): void
    {
        $rows = [
            [
                'row_number' => 2,
                'nom' => 'Dupont',
                'prenom' => 'Jean',
                'date_naissance' => 'invalid',
                'sexe' => 'M',
                'email' => 'invalid',
                'is_valid' => false,
                'errors' => ['Erreurs multiples'],
            ],
        ];

        $response = $this->authPostJson('/api/admin/enrollment/students/import/confirm', [
            'rows' => $rows,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Aucune ligne valide à importer');
    }

    #[Test]
    public function it_requires_authentication_for_confirm(): void
    {
        $rows = [
            [
                'row_number' => 2,
                'nom' => 'Dupont',
                'prenom' => 'Jean',
                'date_naissance' => '15/03/2005',
                'sexe' => 'M',
                'email' => 'jean@test.com',
                'is_valid' => true,
                'errors' => [],
            ],
        ];

        $response = $this->postJson('/api/admin/enrollment/students/import/confirm', [
            'rows' => $rows,
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function it_sets_default_values_for_imported_students(): void
    {
        $rows = [
            [
                'row_number' => 2,
                'nom' => 'Dupont',
                'prenom' => 'Jean',
                'date_naissance' => '15/03/2005',
                'sexe' => 'M',
                'email' => 'jean@test.com',
                'is_valid' => true,
                'errors' => [],
            ],
        ];

        $response = $this->authPostJson('/api/admin/enrollment/students/import/confirm', [
            'rows' => $rows,
        ]);

        $response->assertStatus(201);

        $student = Student::on('tenant')->where('email', 'jean@test.com')->first();
        $this->assertEquals('Actif', $student->status);
        $this->assertEquals('Niger', $student->nationality);
        $this->assertEquals('Niger', $student->country);
    }

    #[Test]
    public function it_handles_optional_fields(): void
    {
        $rows = [
            [
                'row_number' => 2,
                'nom' => 'Dupont',
                'prenom' => 'Jean',
                'date_naissance' => '15/03/2005',
                'sexe' => 'M',
                'email' => 'jean@test.com',
                'telephone' => '+22790123456',
                'mobile' => '+22796123456',
                'adresse' => '123 Rue Example',
                'ville' => 'Niamey',
                'pays' => 'Niger',
                'nationalite' => 'Nigérien',
                'lieu_naissance' => 'Niamey',
                'is_valid' => true,
                'errors' => [],
            ],
        ];

        $response = $this->authPostJson('/api/admin/enrollment/students/import/confirm', [
            'rows' => $rows,
        ]);

        $response->assertStatus(201);

        $student = Student::on('tenant')->where('email', 'jean@test.com')->first();
        $this->assertEquals('+22790123456', $student->phone);
        $this->assertEquals('+22796123456', $student->mobile);
        $this->assertEquals('123 Rue Example', $student->address);
        $this->assertEquals('Niamey', $student->city);
        $this->assertEquals('Niamey', $student->birthplace);
    }

    // ==================== REVALIDATE ROW TESTS ====================

    #[Test]
    public function it_can_revalidate_corrected_row(): void
    {
        $row = [
            'row_number' => 2,
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'date_naissance' => '15/03/2005',
            'sexe' => 'M',
            'email' => 'jean.dupont@test.com', // Now valid
            'is_valid' => false,
            'errors' => ['Email invalide'],
        ];

        $allRows = [$row];

        $response = $this->authPostJson('/api/admin/enrollment/students/import/revalidate-row', [
            'row' => $row,
            'all_rows' => $allRows,
        ]);

        $response->assertOk();
        $revalidated = $response->json('data');
        $this->assertTrue($revalidated['is_valid']);
        $this->assertEmpty($revalidated['errors']);
    }

    #[Test]
    public function it_detects_errors_in_revalidation(): void
    {
        $row = [
            'row_number' => 2,
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'date_naissance' => '15/03/2005',
            'sexe' => 'X', // Invalid
            'email' => 'jean@test.com',
            'is_valid' => true,
            'errors' => [],
        ];

        $allRows = [$row];

        $response = $this->authPostJson('/api/admin/enrollment/students/import/revalidate-row', [
            'row' => $row,
            'all_rows' => $allRows,
        ]);

        $response->assertOk();
        $revalidated = $response->json('data');
        $this->assertFalse($revalidated['is_valid']);
        $this->assertNotEmpty($revalidated['errors']);
    }

    #[Test]
    public function it_requires_authentication_for_revalidate(): void
    {
        $row = [
            'row_number' => 2,
            'nom' => 'Test',
            'prenom' => 'Test',
            'date_naissance' => '15/03/2005',
            'sexe' => 'M',
            'email' => 'test@test.com',
            'is_valid' => true,
            'errors' => [],
        ];

        $response = $this->postJson('/api/admin/enrollment/students/import/revalidate-row', [
            'row' => $row,
            'all_rows' => [$row],
        ]);

        $response->assertStatus(401);
    }
}
