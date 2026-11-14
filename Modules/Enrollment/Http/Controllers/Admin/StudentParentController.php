<?php

namespace Modules\Enrollment\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Http\Requests\LinkStudentParentRequest;
use Modules\Enrollment\Http\Requests\StoreStudentParentRequest;
use Modules\Enrollment\Services\PortalAccountService;
use Modules\PortailParent\Entities\ParentModel;

/**
 * Parents d'un élève : création, liaison d'un parent existant, déliaison (Admin | Manager).
 */
class StudentParentController extends Controller
{
    /**
     * Valeurs de parents.relationship.
     */
    public const RELATIONSHIPS = ['Père', 'Mère', 'Tuteur', 'Tutrice', 'Autre'];

    public function __construct(private PortalAccountService $accountService) {}

    /**
     * Parents liés à l'élève (avec leur compte de connexion).
     */
    public function index(int $studentId): JsonResponse
    {
        $student = Student::query()->findOrFail($studentId);

        $parents = $student->parents()
            ->with('user.roles')
            ->withCount('students')
            ->orderBy('lastname')
            ->get();

        return response()->json([
            'data' => $parents->map(fn (ParentModel $parent): array => $this->present($parent))->all(),
        ]);
    }

    /**
     * Recherche de parents existants (nom, prénom, téléphone, e-mail) pour les lier.
     */
    public function search(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $parents = ParentModel::query()
            ->with('user.roles')
            ->withCount('students')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($parentQuery) use ($search): void {
                    $parentQuery->where('lastname', 'like', "%{$search}%")
                        ->orWhere('firstname', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $parents->map(fn (ParentModel $parent): array => $this->present($parent))->all(),
        ]);
    }

    /**
     * Crée un parent et le lie à l'élève.
     */
    public function store(StoreStudentParentRequest $request, int $studentId): JsonResponse
    {
        $student = Student::query()->findOrFail($studentId);
        $data = $request->validated();

        $duplicate = ParentModel::query()->where('phone', $data['phone'])->first();

        if ($duplicate) {
            return response()->json([
                'message' => "Un parent avec ce téléphone existe déjà ({$duplicate->full_name}). Utilisez « Lier un parent existant ».",
                'errors' => ['phone' => ['Ce téléphone est déjà enregistré pour un autre parent.']],
                'data' => ['existing_parent_id' => $duplicate->id],
            ], 422);
        }

        $parent = DB::connection('tenant')->transaction(function () use ($student, $data): ParentModel {
            $parent = ParentModel::query()->create(collect($data)->except(['is_primary_contact', 'is_financial_responsible'])->all());

            $student->parents()->attach($parent->id, [
                'is_primary_contact' => (bool) ($data['is_primary_contact'] ?? false),
                'is_financial_responsible' => (bool) ($data['is_financial_responsible'] ?? false),
            ]);

            return $parent;
        });

        return response()->json([
            'message' => "Parent {$parent->full_name} créé et lié à l'élève.",
            'data' => $this->present($parent->load('user.roles')->loadCount('students')),
        ], 201);
    }

    /**
     * Lie un parent existant à l'élève.
     */
    public function link(LinkStudentParentRequest $request, int $studentId): JsonResponse
    {
        $student = Student::query()->findOrFail($studentId);
        $parent = ParentModel::query()->findOrFail($request->integer('parent_id'));

        if ($student->parents()->whereKey($parent->id)->exists()) {
            return response()->json([
                'message' => "{$parent->full_name} est déjà lié à cet élève.",
                'errors' => ['parent_id' => ['Parent déjà lié.']],
            ], 422);
        }

        $student->parents()->attach($parent->id, [
            'is_primary_contact' => $request->boolean('is_primary_contact'),
            'is_financial_responsible' => $request->boolean('is_financial_responsible'),
        ]);

        return response()->json([
            'message' => "{$parent->full_name} est maintenant lié à l'élève.",
            'data' => $this->present($parent->load('user.roles')->loadCount('students')),
        ], 201);
    }

    /**
     * Délie un parent de l'élève (la fiche du parent et son compte sont conservés).
     */
    public function unlink(int $studentId, int $parentId): JsonResponse
    {
        $student = Student::query()->findOrFail($studentId);

        if ($student->parents()->detach($parentId) === 0) {
            return response()->json(['message' => "Ce parent n'est pas lié à cet élève."], 404);
        }

        return response()->json(['message' => "Parent délié de l'élève."]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ParentModel $parent): array
    {
        return [
            'id' => $parent->id,
            'firstname' => $parent->firstname,
            'lastname' => $parent->lastname,
            'full_name' => $parent->full_name,
            'relationship' => $parent->relationship,
            'phone' => $parent->phone,
            'phone_secondary' => $parent->phone_secondary,
            'email' => $parent->email,
            'profession' => $parent->profession,
            'address' => $parent->address,
            'is_primary_contact' => $parent->pivot ? (bool) $parent->pivot->is_primary_contact : null,
            'is_financial_responsible' => $parent->pivot ? (bool) $parent->pivot->is_financial_responsible : null,
            'children_count' => $parent->students_count ?? null,
            'account' => $this->accountService->present($parent->user),
        ];
    }
}
