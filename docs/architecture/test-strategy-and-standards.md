# Stratégie et Standards de Tests

[← Retour à l'index](./index.md)

---

## Tests Backend (PHPUnit)

### Configuration

**Fichier** : `phpunit.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
            <directory suffix="Test.php">./Modules/*/Tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
            <directory suffix="Test.php">./Modules/*/Tests/Feature</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

### 1. Tests Unitaires

**Objectif** : Tester logique métier isolée (Services, Helpers)

**Template** :
```php
<?php

namespace Modules\Notes\Tests\Unit;

use Tests\TestCase;
use Modules\Notes\Services\GradeCalculatorService;
use Modules\Notes\Entities\Grade;
use Modules\Notes\Entities\Evaluation;
use Modules\Inscriptions\Entities\Student;
use Modules\StructureAcademique\Entities\Subject;
use Modules\StructureAcademique\Entities\Semester;

class GradeCalculatorServiceTest extends TestCase
{
    private GradeCalculatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GradeCalculatorService();
    }

    #[Test]
    public function it_calculates_subject_average_correctly()
    {
        // Arrange
        $student = Student::factory()->create();
        $subject = Subject::factory()->create();
        $semester = Semester::factory()->create();

        // Créer évaluations avec poids
        $eval1 = Evaluation::factory()->create([
            'subject_id' => $subject->id,
            'weight' => 1,  // Devoir
        ]);
        $eval2 = Evaluation::factory()->create([
            'subject_id' => $subject->id,
            'weight' => 2,  // Composition
        ]);

        // Créer notes
        Grade::factory()->create([
            'student_id' => $student->id,
            'evaluation_id' => $eval1->id,
            'score' => 10,
        ]);
        Grade::factory()->create([
            'student_id' => $student->id,
            'evaluation_id' => $eval2->id,
            'score' => 16,
        ]);

        // Act
        $average = $this->service->calculateSubjectAverage($student, $subject, $semester);

        // Assert
        // (10*1 + 16*2) / (1+2) = 42/3 = 14
        $this->assertEquals(14.00, round($average, 2));
    }

    #[Test]
    public function it_calculates_general_average_with_coefficients()
    {
        // Arrange : Math (coeff 4, moy 15), Français (coeff 3, moy 12)
        // Act : General average = (15*4 + 12*3) / (4+3) = 96/7 = 13.71
        // Assert
    }

    #[Test]
    public function it_returns_zero_when_no_grades_exist()
    {
        $student = Student::factory()->create();
        $subject = Subject::factory()->create();
        $semester = Semester::factory()->create();

        $average = $this->service->calculateSubjectAverage($student, $subject, $semester);

        $this->assertEquals(0, $average);
    }
}
```

### 2. Tests Feature (API)

**Objectif** : Tester endpoints complets avec auth, validation, base de données

**Template** :
```php
<?php

namespace Tests\Feature\Inscriptions;

use Modules\UsersGuard\Entities\User;
use Modules\Inscriptions\Entities\Student;
use Modules\StructureAcademique\Entities\ClassModel;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class StudentControllerTest extends TestCase
{
    use InteractsWithTenancy;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        $this->user = User::factory()->create();
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

    private function authPostJson(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->postJson($uri, $data);
    }

    private function authPutJson(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->putJson($uri, $data);
    }

    private function authDeleteJson(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->deleteJson($uri);
    }

    #[Test]
    public function admin_can_list_students()
    {
        Student::factory()->count(5)->create();

        $response = $this->authGetJson('/api/admin/students');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         '*' => ['id', 'matricule', 'firstname', 'lastname']
                     ],
                     'links',
                     'meta'
                 ])
                 ->assertJsonCount(5, 'data');
    }

    #[Test]
    public function admin_can_create_student_with_parent()
    {
        $class = ClassModel::factory()->create();

        $studentData = [
            'firstname' => 'Amadou',
            'lastname' => 'Diallo',
            'birthdate' => '2010-03-15',
            'sex' => 'M',
            'nationality' => 'Nigérienne',
            'class_id' => $class->id,
            'parent_firstname' => 'Ibrahim',
            'parent_lastname' => 'Diallo',
            'parent_phone' => '+227 90 12 34 56',
        ];

        $response = $this->authPostJson('/api/admin/students', $studentData);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'message',
                     'data' => ['id', 'matricule', 'firstname']
                 ]);

        $this->assertDatabaseHas('students', [
            'firstname' => 'Amadou',
            'lastname' => 'Diallo',
        ]);
    }

    #[Test]
    public function it_validates_required_fields()
    {
        $response = $this->authPostJson('/api/admin/students', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['firstname', 'lastname', 'birthdate', 'sex']);
    }

    #[Test]
    public function unauthenticated_user_cannot_access()
    {
        $response = $this->getJson('/api/admin/students');

        $response->assertStatus(401);
    }

    #[Test]
    public function admin_can_update_student()
    {
        $student = Student::factory()->create();

        $response = $this->authPutJson("/api/admin/students/{$student->id}", [
            'firstname' => 'Moussa',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'firstname' => 'Moussa',
        ]);
    }

    #[Test]
    public function admin_can_delete_student()
    {
        $student = Student::factory()->create();

        $response = $this->authDeleteJson("/api/admin/students/{$student->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('students', ['id' => $student->id]);
    }
}
```

### 3. Factories

**Template** :
```php
<?php

namespace Modules\Inscriptions\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inscriptions\Entities\Student;

class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'matricule' => $this->faker->unique()->numerify('2025-####'),
            'firstname' => $this->faker->firstName(),
            'lastname' => $this->faker->lastName(),
            'birthdate' => $this->faker->date('Y-m-d', '2012-12-31'),
            'sex' => $this->faker->randomElement(['M', 'F']),
            'nationality' => 'Nigérienne',
            'status' => 'Actif',
            'emergency_contact_name' => $this->faker->name(),
            'emergency_contact_phone' => $this->faker->phoneNumber(),
        ];
    }

    public function transferred(): self
    {
        return $this->state(fn() => ['status' => 'Transféré']);
    }

    public function excluded(): self
    {
        return $this->state(fn() => ['status' => 'Exclu']);
    }

    public function graduated(): self
    {
        return $this->state(fn() => ['status' => 'Diplômé']);
    }
}
```

### Commandes de Test

```bash
# Tous les tests
php artisan test

# Tests d'un module spécifique
php artisan test Modules/Inscriptions/Tests

# Tests avec filtre
php artisan test --filter=StudentControllerTest

# Tests unitaires uniquement
php artisan test --testsuite=Unit

# Tests feature uniquement
php artisan test --testsuite=Feature

# Couverture de code
php artisan test --coverage
php artisan test --coverage-html coverage
```

---

## Tests Frontend (Jest + React Testing Library)

### Configuration

**Fichier** : `jest.config.js`

```javascript
module.exports = {
  testEnvironment: 'jsdom',
  setupFilesAfterSetup: ['<rootDir>/jest.setup.js'],
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/src/$1',
  },
  collectCoverageFrom: [
    'src/**/*.{ts,tsx}',
    '!src/**/*.types.ts',
    '!src/**/*.d.ts',
  ],
};
```

### 1. Tests Composants

**Template** :
```typescript
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { StudentList } from '../StudentList';
import { studentService } from '../../services/studentService';

// Mock service
jest.mock('../../services/studentService');

describe('StudentList', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('renders students correctly', async () => {
    const mockStudents = [
      { id: 1, firstname: 'Amadou', lastname: 'Diallo', matricule: '2025-0001' },
      { id: 2, firstname: 'Fatima', lastname: 'Moussa', matricule: '2025-0002' },
    ];

    (studentService.getAll as jest.Mock).mockResolvedValue(mockStudents);

    render(<StudentList />);

    await waitFor(() => {
      expect(screen.getByText('Amadou Diallo')).toBeInTheDocument();
      expect(screen.getByText('Fatima Moussa')).toBeInTheDocument();
    });
  });

  it('displays loading state', () => {
    (studentService.getAll as jest.Mock).mockImplementation(
      () => new Promise(() => {}) // Never resolves
    );

    render(<StudentList />);

    expect(screen.getByText(/chargement/i)).toBeInTheDocument();
  });

  it('displays error message on fetch failure', async () => {
    (studentService.getAll as jest.Mock).mockRejectedValue(new Error('API Error'));

    render(<StudentList />);

    await waitFor(() => {
      expect(screen.getByText(/erreur/i)).toBeInTheDocument();
    });
  });
});
```

### 2. Tests Hooks

```typescript
import { renderHook, waitFor } from '@testing-library/react';
import { useStudents } from '../useStudents';
import { studentService } from '../../services/studentService';

jest.mock('../../services/studentService');

describe('useStudents', () => {
  it('fetches students successfully', async () => {
    const mockStudents = [{ id: 1, firstname: 'Amadou' }];
    (studentService.getAll as jest.Mock).mockResolvedValue(mockStudents);

    const { result } = renderHook(() => useStudents());

    await waitFor(() => {
      expect(result.current.data).toEqual(mockStudents);
      expect(result.current.isLoading).toBe(false);
    });
  });

  it('handles errors correctly', async () => {
    (studentService.getAll as jest.Mock).mockRejectedValue(new Error('API Error'));

    const { result } = renderHook(() => useStudents());

    await waitFor(() => {
      expect(result.current.error).toBeTruthy();
      expect(result.current.isLoading).toBe(false);
    });
  });
});
```

### 3. Tests Services

```typescript
import axios from 'axios';
import { studentService } from '../studentService';

jest.mock('axios');
const mockedAxios = axios as jest.Mocked<typeof axios>;

describe('StudentService', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('fetches all students', async () => {
    const mockResponse = {
      data: {
        data: [{ id: 1, firstname: 'Amadou' }],
      },
    };

    mockedAxios.get.mockResolvedValue(mockResponse);

    const result = await studentService.getAll();

    expect(mockedAxios.get).toHaveBeenCalledWith('/admin/students');
    expect(result).toEqual(mockResponse.data.data);
  });

  it('creates a student', async () => {
    const studentData = { firstname: 'Amadou', lastname: 'Diallo', birthdate: '2010-05-15' };
    const mockResponse = {
      data: {
        data: { id: 1, ...studentData },
      },
    };

    mockedAxios.post.mockResolvedValue(mockResponse);

    const result = await studentService.create(studentData);

    expect(mockedAxios.post).toHaveBeenCalledWith('/admin/students', studentData);
    expect(result).toEqual(mockResponse.data.data);
  });
});
```

### Commandes de Test

```bash
# Tous les tests
npm test

# Mode watch
npm test -- --watch

# Couverture
npm test -- --coverage

# Tests d'un fichier spécifique
npm test StudentList.test.tsx
```

---

## Couverture de Tests Cibles

| Composant | Couverture | Priorité |
|-----------|------------|----------|
| Services métier (backend) | 90%+ | Critique |
| Controllers API | 80%+ | Critique |
| Models | 70%+ | Haute |
| Composants UI | 70%+ | Haute |
| Hooks | 80%+ | Haute |
| Services (frontend) | 80%+ | Haute |
| Utilities | 80%+ | Moyenne |

---

## Bonnes Pratiques

### Backend

1. **Utiliser InteractsWithTenancy + Bearer token** pour tests multi-tenant (voir CLAUDE.md)
2. **Factories pour données de test** (pas de données hardcodées)
3. **Tester les happy paths ET edge cases**
4. **Tester la validation** (erreurs 422)
5. **Tester l'authentification** (401, 403)

### Frontend

1. **Mock des services API** (pas de vraies requêtes)
2. **Tester comportement utilisateur** (clicks, inputs)
3. **Tester états** (loading, error, success)
4. **Accessibility** : Utiliser screen queries sémantiques
5. **Éviter snapshots** (fragiles, peu maintenables)

---

[Suivant : Sécurité →](./security.md)
