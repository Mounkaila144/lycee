<?php

namespace Modules\Enrollment\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\Programme;

class CsvImportService
{
    /**
     * Required CSV columns
     */
    public const REQUIRED_COLUMNS = [
        'nom',
        'prenom',
        'date_naissance',
        'sexe',
        'email',
    ];

    /**
     * Optional CSV columns
     */
    public const OPTIONAL_COLUMNS = [
        'telephone',
        'mobile',
        'adresse',
        'ville',
        'pays',
        'nationalite',
        'lieu_naissance',
        'programme', // Programme code
    ];

    /**
     * Valid sex values
     */
    public const VALID_SEX_VALUES = ['M', 'F', 'O'];

    public function __construct(
        private MatriculeGeneratorService $matriculeGenerator
    ) {}

    /**
     * Parse and validate a CSV file
     *
     * @return array{rows: array, errors: array, valid_count: int, error_count: int, headers: array}
     */
    public function parseAndValidate(UploadedFile $file): array
    {
        $content = file_get_contents($file->getRealPath());

        // Handle BOM (Byte Order Mark) for UTF-8
        $content = $this->removeBom($content);

        // Detect delimiter (comma or semicolon)
        $delimiter = $this->detectDelimiter($content);

        $lines = array_filter(explode("\n", $content), fn ($line) => trim($line) !== '');

        if (count($lines) < 2) {
            return [
                'rows' => [],
                'errors' => ['general' => 'Le fichier CSV doit contenir au moins une ligne d\'en-têtes et une ligne de données'],
                'valid_count' => 0,
                'error_count' => 0,
                'headers' => [],
            ];
        }

        // Parse headers
        $headers = $this->parseHeaders(array_shift($lines), $delimiter);

        // Validate headers
        $headerErrors = $this->validateHeaders($headers);
        if (! empty($headerErrors)) {
            return [
                'rows' => [],
                'errors' => ['headers' => $headerErrors],
                'valid_count' => 0,
                'error_count' => 0,
                'headers' => $headers,
            ];
        }

        // Parse and validate rows
        $rows = [];
        $validCount = 0;
        $errorCount = 0;
        $seenEmails = [];
        $rowNumber = 1;

        foreach ($lines as $line) {
            $rowNumber++;
            $values = str_getcsv(trim($line), $delimiter);

            // Skip empty rows
            if (count($values) === 1 && empty(trim($values[0]))) {
                continue;
            }

            $rowData = $this->mapRowToData($headers, $values);
            $rowErrors = $this->validateRow($rowData, $seenEmails, $rowNumber);

            if (empty($rowErrors)) {
                $validCount++;
                $rowData['is_valid'] = true;
                $rowData['errors'] = [];
            } else {
                $errorCount++;
                $rowData['is_valid'] = false;
                $rowData['errors'] = $rowErrors;
            }

            $rowData['row_number'] = $rowNumber;

            // Track emails for duplicate detection within file
            if (! empty($rowData['email'])) {
                $seenEmails[strtolower($rowData['email'])] = $rowNumber;
            }

            $rows[] = $rowData;
        }

        return [
            'rows' => $rows,
            'errors' => [],
            'valid_count' => $validCount,
            'error_count' => $errorCount,
            'headers' => $headers,
        ];
    }

    /**
     * Import validated rows
     *
     * @return array{imported_count: int, errors: array, imported_students: array}
     */
    public function import(array $validatedRows, ?Programme $defaultProgramme = null): array
    {
        $importedStudents = [];
        $errors = [];
        $importedCount = 0;

        return DB::connection('tenant')->transaction(function () use ($validatedRows, $defaultProgramme, &$importedStudents, &$errors, &$importedCount) {
            foreach ($validatedRows as $row) {
                if (! $row['is_valid']) {
                    continue;
                }

                try {
                    // Find programme if specified
                    $programme = $defaultProgramme;
                    if (! empty($row['programme'])) {
                        $programme = Programme::on('tenant')
                            ->where('code', strtoupper($row['programme']))
                            ->first();

                        if (! $programme) {
                            $errors[] = [
                                'row_number' => $row['row_number'],
                                'error' => "Programme '{$row['programme']}' non trouvé",
                            ];

                            continue;
                        }
                    }

                    // Generate matricule
                    $matricule = null;
                    if ($programme) {
                        $matricule = $this->matriculeGenerator->generateNext($programme);
                    } else {
                        // Generate a simple matricule without programme
                        $matricule = $this->generateSimpleMatricule();
                    }

                    // Prepare mobile value (required field)
                    $mobile = $this->sanitize($row['mobile'] ?? $row['telephone'] ?? null);
                    if (empty($mobile)) {
                        // Use a placeholder value if no phone/mobile provided
                        $mobile = 'N/A';
                    }

                    // Create student
                    $student = Student::on('tenant')->create([
                        'matricule' => $matricule,
                        'firstname' => $this->sanitize($row['prenom']),
                        'lastname' => $this->sanitize($row['nom']),
                        'birthdate' => $this->parseDate($row['date_naissance']),
                        'birthplace' => $this->sanitize($row['lieu_naissance'] ?? null),
                        'sex' => strtoupper($row['sexe']),
                        'email' => strtolower(trim($row['email'])),
                        'phone' => $this->sanitize($row['telephone'] ?? null),
                        'mobile' => $mobile,
                        'address' => $this->sanitize($row['adresse'] ?? null),
                        'city' => $this->sanitize($row['ville'] ?? null),
                        'country' => $this->sanitize($row['pays'] ?? 'Niger'),
                        'nationality' => $this->sanitize($row['nationalite'] ?? 'Niger'),
                        'status' => 'Actif',
                    ]);

                    $importedStudents[] = [
                        'id' => $student->id,
                        'matricule' => $student->matricule,
                        'full_name' => $student->full_name,
                        'email' => $student->email,
                        'row_number' => $row['row_number'],
                    ];

                    $importedCount++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'row_number' => $row['row_number'],
                        'error' => 'Erreur lors de l\'import: '.$e->getMessage(),
                    ];
                }
            }

            return [
                'imported_count' => $importedCount,
                'errors' => $errors,
                'imported_students' => $importedStudents,
            ];
        });
    }

    /**
     * Generate CSV template content
     */
    public function generateTemplate(): string
    {
        $headers = [
            'nom',
            'prenom',
            'date_naissance',
            'sexe',
            'email',
            'telephone',
            'mobile',
            'adresse',
            'ville',
            'pays',
            'nationalite',
            'lieu_naissance',
            'programme',
        ];

        $exampleRow = [
            'Dupont',
            'Jean',
            '15/03/2005',
            'M',
            'jean.dupont@email.com',
            '+22790123456',
            '+22796123456',
            '123 Rue Example',
            'Niamey',
            'Niger',
            'Niger',
            'Niamey',
            'LINF',
        ];

        return implode(';', $headers)."\n".implode(';', $exampleRow)."\n";
    }

    /**
     * Update a row's data (for inline correction)
     */
    public function updateRowData(array $row, array $updates): array
    {
        foreach ($updates as $key => $value) {
            if (array_key_exists($key, $row)) {
                $row[$key] = $value;
            }
        }

        return $row;
    }

    /**
     * Revalidate a single row
     */
    public function revalidateRow(array $row, array $allRows): array
    {
        // Build seenEmails from other rows (excluding current row)
        $seenEmails = [];
        foreach ($allRows as $otherRow) {
            if ($otherRow['row_number'] !== $row['row_number'] && ! empty($otherRow['email'])) {
                $seenEmails[strtolower($otherRow['email'])] = $otherRow['row_number'];
            }
        }

        $errors = $this->validateRow($row, $seenEmails, $row['row_number']);

        $row['is_valid'] = empty($errors);
        $row['errors'] = $errors;

        return $row;
    }

    /**
     * Parse CSV headers
     */
    private function parseHeaders(string $headerLine, string $delimiter): array
    {
        return array_map(
            fn ($h) => $this->normalizeHeader($h),
            str_getcsv(trim($headerLine), $delimiter)
        );
    }

    /**
     * Normalize header name
     */
    private function normalizeHeader(string $header): string
    {
        $header = trim(strtolower($header));

        // Remove accents (convert accented characters to their base form)
        $header = $this->removeAccents($header);

        // Replace non-alphanumeric characters with underscore
        $header = preg_replace('/[^a-z0-9_]/', '_', $header);

        // Remove multiple consecutive underscores
        $header = preg_replace('/_+/', '_', $header);

        // Remove leading/trailing underscores
        $header = trim($header, '_');

        // Map common variations
        $mappings = [
            'date_de_naissance' => 'date_naissance',
            'datenaissance' => 'date_naissance',
            'naissance' => 'date_naissance',
            'prenoms' => 'prenom',
            'nom_famille' => 'nom',
            'genre' => 'sexe',
            'sex' => 'sexe',
            'mail' => 'email',
            'courriel' => 'email',
            'tel' => 'telephone',
            'phone' => 'telephone',
            'portable' => 'mobile',
            'cellulaire' => 'mobile',
            'filiere' => 'programme',
            'program' => 'programme',
            'lieu_de_naissance' => 'lieu_naissance',
            'birthplace' => 'lieu_naissance',
            'nationality' => 'nationalite',
            'country' => 'pays',
            'address' => 'adresse',
            'city' => 'ville',
        ];

        return $mappings[$header] ?? $header;
    }

    /**
     * Remove accents from a string
     */
    private function removeAccents(string $string): string
    {
        $accents = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Ñ' => 'N',
        ];

        return strtr($string, $accents);
    }

    /**
     * Validate headers
     */
    private function validateHeaders(array $headers): array
    {
        $errors = [];
        $missingRequired = [];

        foreach (self::REQUIRED_COLUMNS as $required) {
            if (! in_array($required, $headers)) {
                $missingRequired[] = $required;
            }
        }

        if (! empty($missingRequired)) {
            $errors[] = 'Colonnes obligatoires manquantes: '.implode(', ', $missingRequired);
        }

        return $errors;
    }

    /**
     * Map row values to data array
     */
    private function mapRowToData(array $headers, array $values): array
    {
        $data = [];
        foreach ($headers as $index => $header) {
            $data[$header] = $values[$index] ?? null;
        }

        return $data;
    }

    /**
     * Validate a single row
     */
    private function validateRow(array $row, array $seenEmails, int $rowNumber): array
    {
        $errors = [];

        // Validate required fields
        if (empty(trim($row['nom'] ?? ''))) {
            $errors[] = 'Le nom est obligatoire';
        }

        if (empty(trim($row['prenom'] ?? ''))) {
            $errors[] = 'Le prénom est obligatoire';
        }

        // Validate email
        $email = trim($row['email'] ?? '');
        if (empty($email)) {
            $errors[] = 'L\'email est obligatoire';
        } else {
            // Check email format
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Format d\'email invalide';
            } else {
                // Check for duplicates in the file
                $emailLower = strtolower($email);
                if (isset($seenEmails[$emailLower]) && $seenEmails[$emailLower] !== $rowNumber) {
                    $errors[] = "Email en doublon avec la ligne {$seenEmails[$emailLower]}";
                }

                // Check for duplicates in the database
                $existingStudent = Student::on('tenant')
                    ->where('email', $emailLower)
                    ->first();

                if ($existingStudent) {
                    $errors[] = "Email déjà utilisé par l'étudiant {$existingStudent->matricule}";
                }
            }
        }

        // Validate date of birth
        $dateNaissance = trim($row['date_naissance'] ?? '');
        if (empty($dateNaissance)) {
            $errors[] = 'La date de naissance est obligatoire';
        } else {
            $parsedDate = $this->parseDate($dateNaissance);
            if (! $parsedDate) {
                $errors[] = 'Format de date invalide (utilisez JJ/MM/AAAA ou AAAA-MM-JJ)';
            } else {
                // Check if date is realistic (between 1950 and today - 10 years for minimum age)
                $minDate = Carbon::parse('1950-01-01');
                $maxDate = Carbon::now()->subYears(10);

                if ($parsedDate->lt($minDate) || $parsedDate->gt($maxDate)) {
                    $errors[] = 'Date de naissance non réaliste';
                }
            }
        }

        // Validate sex
        $sex = strtoupper(trim($row['sexe'] ?? ''));
        if (empty($sex)) {
            $errors[] = 'Le sexe est obligatoire';
        } elseif (! in_array($sex, self::VALID_SEX_VALUES)) {
            $errors[] = 'Sexe invalide (utilisez M, F ou O)';
        }

        // Validate programme if provided
        if (! empty($row['programme'])) {
            $programme = Programme::on('tenant')
                ->where('code', strtoupper($row['programme']))
                ->first();

            if (! $programme) {
                $errors[] = "Programme '{$row['programme']}' non trouvé";
            }
        }

        return $errors;
    }

    /**
     * Parse date from various formats
     */
    private function parseDate(?string $date): ?Carbon
    {
        if (empty($date)) {
            return null;
        }

        $date = trim($date);

        // Try common formats
        $formats = [
            'd/m/Y',    // 15/03/2005
            'd-m-Y',    // 15-03-2005
            'Y-m-d',    // 2005-03-15
            'd.m.Y',    // 15.03.2005
            'm/d/Y',    // 03/15/2005 (US format)
        ];

        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $date);
                if ($parsed && $parsed->format($format) === $date) {
                    return $parsed;
                }
            } catch (\Exception $e) {
                // Try next format
            }
        }

        // Try natural parsing as fallback
        try {
            return Carbon::parse($date);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Sanitize string value
     */
    private function sanitize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Detect CSV delimiter
     */
    private function detectDelimiter(string $content): string
    {
        $firstLine = strtok($content, "\n");

        $commaCount = substr_count($firstLine, ',');
        $semicolonCount = substr_count($firstLine, ';');

        return $semicolonCount > $commaCount ? ';' : ',';
    }

    /**
     * Remove BOM from content
     */
    private function removeBom(string $content): string
    {
        $bom = "\xef\xbb\xbf";
        if (substr($content, 0, 3) === $bom) {
            return substr($content, 3);
        }

        return $content;
    }

    /**
     * Generate a simple matricule without programme
     */
    private function generateSimpleMatricule(): string
    {
        $year = now()->year;
        $prefix = "{$year}-IMP";

        $lastMatricule = Student::on('tenant')
            ->where('matricule', 'like', "{$prefix}-%")
            ->orderByRaw('CAST(SUBSTRING_INDEX(matricule, \'-\', -1) AS UNSIGNED) DESC')
            ->first();

        $sequence = 1;
        if ($lastMatricule) {
            $parts = explode('-', $lastMatricule->matricule);
            $lastSequence = (int) end($parts);
            $sequence = $lastSequence + 1;
        }

        return sprintf('%s-%03d', $prefix, $sequence);
    }
}
