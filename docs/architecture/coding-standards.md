# Standards de Codage

[← Retour à l'index](./index.md)

---

## Backend - Laravel

### Conventions de Nommage

| Élément | Convention | Exemple |
|---------|-----------|---------|
| Module | PascalCase | `StructureAcademique`, `ConseilDeClasse` |
| Model | PascalCase singular | `Student`, `ClassModel`, `Grade` |
| Table | snake_case plural | `students`, `classes`, `grades` |
| Controller | PascalCase + Controller | `StudentController` |
| Request | PascalCase + Request | `StoreStudentRequest` |
| Resource | PascalCase + Resource | `StudentResource` |
| Service | PascalCase + Service | `GradeCalculatorService` |
| Method | camelCase | `calculateSubjectAverage()` |
| Variable | camelCase | `$classEnrollment` |
| Route | kebab-case | `/class-enrollments` |

### Template Model

```php
<?php

namespace Modules\{Module}\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Student extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';  // TOUJOURS déclarer
    protected $table = 'students';

    protected $fillable = [
        'matricule', 'firstname', 'lastname',
        'birthdate', 'sex', 'nationality', 'status',
    ];

    // Laravel 12 : casts() method
    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
        ];
    }

    // Relations avec type hints
    public function classEnrollments(): HasMany
    {
        return $this->hasMany(ClassEnrollment::class);
    }

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(ParentModel::class, 'student_parent')
            ->withPivot('relationship', 'is_primary_contact', 'is_financial_responsible');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'Actif');
    }

    // Accessors (Laravel 12)
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn() => "{$this->firstname} {$this->lastname}",
        );
    }
}
```

### Template Controller

```php
<?php

namespace Modules\{Module}\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class {Entity}Controller extends Controller
{
    /**
     * Liste des entités
     */
    public function index(Request $request)
    {
        $entities = {Entity}::query()
            ->when($request->search, fn($q, $search) =>
                $q->where('name', 'like', "%{$search}%")
            )
            ->with(['relations'])
            ->latest()
            ->paginate($request->per_page ?? 15);

        return {Entity}Resource::collection($entities);
    }

    /**
     * Créer une entité
     */
    public function store(Store{Entity}Request $request): JsonResponse
    {
        $entity = {Entity}::create($request->validated());

        return response()->json([
            'message' => 'Créé avec succès.',
            'data' => new {Entity}Resource($entity),
        ], 201);
    }

    /**
     * Détails d'une entité
     */
    public function show({Entity} $entity)
    {
        return new {Entity}Resource($entity->load(['relations']));
    }

    /**
     * Modifier une entité
     */
    public function update(Update{Entity}Request $request, {Entity} $entity): JsonResponse
    {
        $entity->update($request->validated());

        return response()->json([
            'message' => 'Modifié avec succès.',
            'data' => new {Entity}Resource($entity),
        ]);
    }

    /**
     * Supprimer une entité
     */
    public function destroy({Entity} $entity): JsonResponse
    {
        $entity->delete();

        return response()->json([
            'message' => 'Supprimé avec succès.',
        ]);
    }
}
```

### Template Form Request

```php
<?php

namespace Modules\{Module}\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Store{Entity}Request extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé
     */
    public function authorize(): bool
    {
        return true; // Géré par middleware/policies
    }

    /**
     * Règles de validation
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'unique:entities,code'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Messages d'erreur personnalisés
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est obligatoire.',
            'code.unique' => 'Ce code est déjà utilisé.',
        ];
    }
}
```

### Template API Resource

```php
<?php

namespace Modules\{Module}\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class {Entity}Resource extends JsonResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'is_active' => $this->is_active,

            // Relations conditionnelles
            'related' => $this->whenLoaded('related'),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

### Template Service

```php
<?php

namespace Modules\{Module}\Services;

class {Service}Service
{
    /**
     * Méthode avec type hints
     */
    public function process(Entity $entity, array $data): Result
    {
        // Logique métier

        return new Result([
            'success' => true,
            'data' => $processedData,
        ]);
    }

    /**
     * Méthode privée helper
     */
    private function validate(array $data): bool
    {
        // Validation métier
        return true;
    }
}
```

### Template Migration

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations
     */
    public function up(): void
    {
        Schema::create('entities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            // Foreign keys
            $table->foreignId('parent_id')
                  ->nullable()
                  ->constrained('entities')
                  ->onDelete('cascade');

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('code');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('entities');
    }
};
```

---

## Frontend - Next.js/React/TypeScript

### Conventions de Nommage

| Élément | Convention | Exemple |
|---------|-----------|---------|
| Composant | PascalCase | `StudentList`, `GradeInputTable` |
| Fichier composant | PascalCase.tsx | `StudentList.tsx` |
| Hook | camelCase + use prefix | `useStudents`, `useGrades` |
| Service | camelCase + Service suffix | `studentService`, `gradeService` |
| Type/Interface | PascalCase | `Student`, `Grade`, `ReportCard` |
| Variable | camelCase | `studentData`, `classEnrollment` |
| Constante | UPPER_SNAKE_CASE | `MAX_SCORE`, `MAX_STUDENTS_PER_CLASS` |

### Template Composant

```typescript
'use client';

import React, { useState } from 'react';
import { Box, Typography } from '@mui/material';

interface StudentListProps {
  tenantId?: string;
  classId?: number;
}

export const StudentList: React.FC<StudentListProps> = ({
  tenantId,
  classId,
}) => {
  const [students, setStudents] = useState<Student[]>([]);
  const [loading, setLoading] = useState(false);

  // Logique composant

  return (
    <Box>
      <Typography variant="h4">Liste des Élèves</Typography>
      {/* JSX */}
    </Box>
  );
};
```

### Template Hook

```typescript
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { studentService } from '../services/studentService';
import type { Student } from '../types/student.types';

export const useStudents = (tenantId?: string) => {
  return useQuery<Student[]>({
    queryKey: ['students', tenantId],
    queryFn: () => studentService.getAll(tenantId),
  });
};

export const useStudentMutations = () => {
  const queryClient = useQueryClient();

  const createMutation = useMutation({
    mutationFn: studentService.create,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['students'] });
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<Student> }) =>
      studentService.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['students'] });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: studentService.delete,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['students'] });
    },
  });

  return {
    createStudent: createMutation.mutate,
    updateStudent: updateMutation.mutate,
    deleteStudent: deleteMutation.mutate,
    isCreating: createMutation.isPending,
    isUpdating: updateMutation.isPending,
    isDeleting: deleteMutation.isPending,
  };
};
```

### Template Service

```typescript
import { createApiClient } from '@/lib/api/apiClient';
import type { Student, StudentFormData } from '../types/student.types';

class StudentService {
  async getAll(tenantId?: string): Promise<Student[]> {
    const client = createApiClient(tenantId);
    const response = await client.get<{ data: Student[] }>('/admin/students');
    return response.data.data;
  }

  async getById(id: number, tenantId?: string): Promise<Student> {
    const client = createApiClient(tenantId);
    const response = await client.get<{ data: Student }>(`/admin/students/${id}`);
    return response.data.data;
  }

  async create(data: StudentFormData, tenantId?: string): Promise<Student> {
    const client = createApiClient(tenantId);
    const response = await client.post<{ data: Student }>('/admin/students', data);
    return response.data.data;
  }

  async update(id: number, data: Partial<StudentFormData>, tenantId?: string): Promise<Student> {
    const client = createApiClient(tenantId);
    const response = await client.put<{ data: Student }>(`/admin/students/${id}`, data);
    return response.data.data;
  }

  async delete(id: number, tenantId?: string): Promise<void> {
    const client = createApiClient(tenantId);
    await client.delete(`/admin/students/${id}`);
  }
}

export const studentService = new StudentService();
```

### Template Types

```typescript
// student.types.ts

export interface Student {
  id: number;
  matricule: string;
  firstname: string;
  lastname: string;
  birthdate: string;
  sex: 'M' | 'F';
  nationality: string;
  status: 'Actif' | 'Transféré' | 'Exclu' | 'Diplômé';
  photo?: string;
  emergency_contact_name?: string;
  emergency_contact_phone?: string;
  created_at: string;
  updated_at: string;
}

export interface StudentFormData {
  firstname: string;
  lastname: string;
  birthdate: string;
  sex: 'M' | 'F';
  nationality: string;
  class_id: number;
  parent_firstname: string;
  parent_lastname: string;
  parent_phone: string;
  emergency_contact_name?: string;
  emergency_contact_phone?: string;
}

export interface StudentFilters {
  search?: string;
  status?: Student['status'];
  class_id?: number;
}
```

---

## Bonnes Pratiques Générales

### Backend

1. **Toujours utiliser Type Hints**
   ```php
   // ✅ BON
   public function calculate(float $amount): float

   // ❌ MAUVAIS
   public function calculate($amount)
   ```

2. **Toujours utiliser Form Requests pour validation**
   - Jamais de validation inline dans Controllers

3. **Toujours utiliser API Resources pour réponses**
   - Jamais retourner models bruts

4. **Eager Loading pour éviter N+1**
   ```php
   // ✅ BON
   Student::with(['classEnrollments.class', 'parents'])->get();

   // ❌ MAUVAIS
   Student::all(); // N+1 queries
   ```

5. **SoftDeletes sur toutes tables métier**

### Frontend

1. **Toujours typer avec TypeScript**
   - Aucun `any` toléré

2. **Composants fonctionnels uniquement**
   - Pas de class components

3. **Custom hooks pour logique réutilisable**

4. **Services pour appels API**
   - Jamais d'appels directs dans composants

5. **Responsive design obligatoire**
   - Tester mobile, tablette, desktop
   - Mobile-first pour les interfaces Élève et Parent

---

[Suivant : Stratégie et Standards de Tests →](./test-strategy-and-standards.md)
