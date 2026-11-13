<?php

namespace Tests\Unit\Enrollment;

use Illuminate\Http\UploadedFile;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Services\CsvImportService;
use Modules\Enrollment\Services\MatriculeGeneratorService;
use Modules\StructureAcademique\Entities\Programme;
use Modules\UsersGuard\Entities\User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class CsvImportServiceTest extends TestCase
{
    use InteractsWithTenancy;

    private CsvImportService $service;

    private User $user;

    private Programme $programme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        $this->service = new CsvImportService(new MatriculeGeneratorService);
        $this->user = User::factory()->create();
        $this->programme = Programme::factory()->create([
            'code' => 'LINF',
            'libelle' => 'Licence Informatique',
        ]);

        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * Create a temporary CSV file
     */
    private function createCsvFile(string $content): UploadedFile
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_');
        file_put_contents($tempFile, $content);

        return new UploadedFile(
            $tempFile,
            'test.csv',
            'text/csv',
            null,
            true
        );
    }

    // ==================== TEMPLATE GENERATION TESTS ====================

    #[Test]
    public function it_generates_csv_template_with_headers(): void
    {
        $template = $this->service->generateTemplate();

        $this->assertStringContainsString('nom', $template);
        $this->assertStringContainsString('prenom', $template);
        $this->assertStringContainsString('date_naissance', $template);
        $this->assertStringContainsString('sexe', $template);
        $this->assertStringContainsString('quartier', $template);
        $this->assertStringNotContainsString('email', $template);
    }

    #[Test]
    public function it_generates_csv_template_with_example_row(): void
    {
        $template = $this->service->generateTemplate();

        $lines = explode("\n", trim($template));
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('Issoufou', $template);
        $this->assertStringContainsString('Amina', $template);
    }

    #[Test]
    public function it_parses_valid_csv_with_semicolon_delimiter(): void
    {
        $content = "nom;prenom;date_naissance;sexe;email\nDupont;Jean;15/03/2005;M;jean@test.com";
        $file = $this->createCsvFile($content);

        $result = $this->service->parseAndValidate($file);

        $this->assertEmpty($result['errors']);
        $this->assertCount(1, $result['rows']);
        $this->assertEquals(1, $result['valid_count']);
    }

    #[Test]
    public function it_parses_valid_csv_with_comma_delimiter(): void
    {
        $content = "nom,prenom,date_naissance,sexe,email\nDupont,Jean,15/03/2005,M,jean@test.com";
        $file = $this->createCsvFile($content);

        $result = $this->service->parseAndValidate($file);

        $this->assertEmpty($result['errors']);
        $this->assertCount(1, $result['rows']);
    }

    #[Test]
    public function it_normalizes_header_variations(): void
    {
        $content = "Nom;Prénom;Date de naissance;Genre;Email\nDupont;Jean;15/03/2005;M;jean@test.com";
        $file = $this->createCsvFile($content);

        $result = $this->service->parseAndValidate($file);

        $this->assertContains('nom', $result['headers']);
        $this->assertContains('prenom', $result['headers']);
        $this->assertContains('date_naissance', $result['headers']);
        $this->assertContains('sexe', $result['headers']);
    }

    #[Test]
    public function it_handles_bom_in_csv(): void
    {
        $bom = "\xEF\xBB\xBF";
        $content = $bom."nom;prenom;date_naissance;sexe;email\nDupont;Jean;15/03/2005;M;jean@test.com";
        $file = $this->createCsvFile($content);

        $result = $this->service->parseAndValidate($file);

        $this->assertEmpty($result['errors']);
        $this->assertContains('nom', $result['headers']);
    }

    #[Test]
    public function it_reports_missing_required_columns(): void
    {
        $content = "nom;prenom\nDupont;Jean";
        $file = $this->createCsvFile($content);

        $result = $this->service->parseAndValidate($file);

        $this->assertArrayHasKey('headers', $result['errors']);
    }

    // ==================== VALIDATION TESTS ====================

    #[Test]
    public function it_does_not_require_email_column(): void
    {
        $content = "nom;prenom;date_naissance;sexe\nDupont;Jean;15/03/2005;M";
        $file = $this->createCsvFile($content);

        $result = $this->service->parseAndValidate($file);

        $this->assertEmpty($result['errors']);
        $this->assertEquals(1, $result['valid_count']);
    }

    #[Test]
    public function it_detects_duplicate_students_in_file(): void
    {
        $content = "nom;prenom;date_naissance;sexe\nDupont;Jean;15/03/2005;M\nDUPONT;Jean;2005-03-15;M\nMartin;Marie;20/05/2004;F";
        $file = $this->createCsvFile($content);

        $result = $this->service->parseAndValidate($file);

        $this->assertTrue($result['rows'][0]['is_valid']);
        $this->assertFalse($result['rows'][1]['is_valid']);
        $this->assertTrue($result['rows'][2]['is_valid']);
    }

    #[Test]
    public function it_detects_existing_student_in_database(): void
    {
        Student::factory()->create(['firstname' => 'Jean', 'lastname' => 'Dupont', 'birthdate' => '2005-03-15']);

        $content = "nom;prenom;date_naissance;sexe\nDupont;Jean;15/03/2005;M";
        $file = $this->createCsvFile($content);

        $result = $this->service->parseAndValidate($file);

        $this->assertFalse($result['rows'][0]['is_valid']);
    }

    #[Test]
    public function it_validates_date_formats(): void
    {
        // Valid formats
        $validDates = ['15/03/2005', '15-03-2005', '2005-03-15'];

        foreach ($validDates as $date) {
            $content = "nom;prenom;date_naissance;sexe;email\nDupont;Jean;{$date};M;jean_{$date}@test.com";
            $file = $this->createCsvFile($content);

            $result = $this->service->parseAndValidate($file);

            $this->assertTrue(
                $result['rows'][0]['is_valid'],
                "Date format '{$date}' should be valid"
            );
        }
    }

    #[Test]
    public function it_rejects_invalid_date_format(): void
    {
        $content = "nom;prenom;date_naissance;sexe;email\nDupont;Jean;invalid-date;M;jean@test.com";
        $file = $this->createCsvFile($content);

        $result = $this->service->parseAndValidate($file);

        $this->assertFalse($result['rows'][0]['is_valid']);
    }

    #[Test]
    public function it_validates_sex_values(): void
    {
        foreach (['M', 'F', 'm', 'f'] as $index => $sex) {
            $content = "nom;prenom;date_naissance;sexe\nDupont{$index};Jean;15/03/2005;{$sex}";
            $file = $this->createCsvFile($content);

            $result = $this->service->parseAndValidate($file);

            $this->assertTrue(
                $result['rows'][0]['is_valid'],
                "Sex value '{$sex}' should be valid"
            );
        }
    }

    #[Test]
    public function it_rejects_invalid_sex_value(): void
    {
        $content = "nom;prenom;date_naissance;sexe\nDupont;Jean;15/03/2005;O";
        $file = $this->createCsvFile($content);

        $result = $this->service->parseAndValidate($file);

        $this->assertFalse($result['rows'][0]['is_valid']);
        $this->assertContains('Sexe invalide (utilisez M ou F)', $result['rows'][0]['errors']);
    }

    #[Test]
    public function it_validates_programme_code(): void
    {
        $content = "nom;prenom;date_naissance;sexe;email;programme\nDupont;Jean;15/03/2005;M;jean@test.com;LINF";
        $file = $this->createCsvFile($content);

        $result = $this->service->parseAndValidate($file);

        $this->assertTrue($result['rows'][0]['is_valid']);
    }

    #[Test]
    public function it_ignores_unknown_programme_code(): void
    {
        $content = "nom;prenom;date_naissance;sexe;programme\nDupont;Jean;15/03/2005;M;INVALID";
        $file = $this->createCsvFile($content);

        $result = $this->service->parseAndValidate($file);

        $this->assertTrue($result['rows'][0]['is_valid']);
    }

    #[Test]
    public function it_imports_valid_rows(): void
    {
        $validatedRows = [
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

        $result = $this->service->import($validatedRows);

        $this->assertEquals(1, $result['imported_count']);
        $this->assertCount(1, $result['imported_students']);
        $this->assertDatabaseHas('students', ['lastname' => 'Dupont', 'firstname' => 'Jean'], 'tenant');
    }

    #[Test]
    public function it_generates_matricule_during_import(): void
    {
        $validatedRows = [
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

        $result = $this->service->import($validatedRows);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{4}$/', $result['imported_students'][0]['matricule']);
    }

    #[Test]
    public function it_skips_invalid_rows_during_import(): void
    {
        $validatedRows = [
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
                'date_naissance' => 'invalid',
                'sexe' => 'F',
                'email' => 'marie@test.com',
                'is_valid' => false,
                'errors' => ['Date invalide'],
            ],
        ];

        $result = $this->service->import($validatedRows);

        $this->assertEquals(1, $result['imported_count']);
    }

    #[Test]
    public function it_sets_default_values_during_import(): void
    {
        $validatedRows = [
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

        $this->service->import($validatedRows);

        $student = Student::on('tenant')->where('lastname', 'Dupont')->first();
        $this->assertEquals('Actif', $student->status);
        $this->assertEquals('Nigérienne', $student->nationality);
        $this->assertEquals('Niamey', $student->city);
    }

    #[Test]
    public function it_uses_optional_fields_when_provided(): void
    {
        $validatedRows = [
            [
                'row_number' => 2,
                'nom' => 'Dupont',
                'prenom' => 'Jean',
                'date_naissance' => '15/03/2005',
                'sexe' => 'M',
                'email' => 'jean@test.com',
                'telephone' => '+22790123456',
                'ville' => 'Niamey',
                'lieu_naissance' => 'Zinder',
                'is_valid' => true,
                'errors' => [],
            ],
        ];

        $this->service->import($validatedRows);

        $student = Student::on('tenant')->where('lastname', 'Dupont')->first();
        $this->assertEquals('+22790123456', $student->phone);
        $this->assertEquals('Niamey', $student->city);
        $this->assertEquals('Zinder', $student->birthplace);
    }

    // ==================== REVALIDATION TESTS ====================

    #[Test]
    public function it_revalidates_corrected_row(): void
    {
        $row = [
            'row_number' => 2,
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'date_naissance' => '15/03/2005',
            'sexe' => 'M',
            'email' => 'jean@test.com',
            'is_valid' => false,
            'errors' => ['Previous errors'],
        ];

        $allRows = [$row];

        $result = $this->service->revalidateRow($row, $allRows);

        $this->assertTrue($result['is_valid']);
        $this->assertEmpty($result['errors']);
    }

    #[Test]
    public function it_detects_new_errors_during_revalidation(): void
    {
        $row = [
            'row_number' => 2,
            'nom' => '',
            'prenom' => 'Jean',
            'date_naissance' => '15/03/2005',
            'sexe' => 'M',
            'email' => 'jean@test.com',
            'is_valid' => true,
            'errors' => [],
        ];

        $allRows = [$row];

        $result = $this->service->revalidateRow($row, $allRows);

        $this->assertFalse($result['is_valid']);
        $this->assertContains('Le nom est obligatoire', $result['errors']);
    }
}
