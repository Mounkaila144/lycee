<?php

namespace Modules\PortailParent\Http\Controllers\Admin;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Enrollment\Entities\Student;
use Modules\PortailParent\Http\Resources\ChildResource;
use Modules\PortailParent\Http\Resources\ParentProfileResource;

/**
 * Story Parent 01 — Home & Mes Enfants.
 *
 * Endpoints :
 *   - GET /api/admin/parent/me                 → profil Parent connecté
 *   - GET /api/admin/parent/me/children        → liste de SES enfants (pivot parent_student)
 *   - GET /api/admin/parent/children/{student} → détail d'UN enfant (ChildPolicy::view)
 */
class ParentChildrenController extends Controller
{
    use AuthorizesRequests;

    /**
     * Profil du Parent connecté.
     */
    public function me(Request $request): JsonResponse|ParentProfileResource
    {
        $parent = $request->user()->parent;

        if (! $parent) {
            return response()->json([
                'message' => 'Aucun profil Parent associé à votre compte. Contactez l\'administration.',
                'code' => 'PARENT_PROFILE_MISSING',
            ], 404);
        }

        return new ParentProfileResource($parent->loadCount('students'));
    }

    /**
     * Liste des enfants du Parent connecté (filtre owner via pivot).
     */
    public function children(Request $request): JsonResponse|AnonymousResourceCollection
    {
        $parent = $request->user()->parent;

        if (! $parent) {
            return response()->json([
                'message' => 'Aucun profil Parent associé à votre compte.',
                'code' => 'PARENT_PROFILE_MISSING',
            ], 404);
        }

        return ChildResource::collection($parent->students()->get());
    }

    /**
     * Détail d'un enfant (avec ownership ChildPolicy::view).
     */
    public function show(Request $request, Student $student): ChildResource
    {
        // Note : on n'utilise pas $this->authorize() car TenantSanctumAuth ne définit
        // pas Auth::user() sur le guard par défaut (il utilise Auth::guard('tenant')).
        // Gate::forUser() permet de cibler explicitement l'utilisateur tenant connecté.
        if (! Gate::forUser($request->user())->allows('view', $student)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        return new ChildResource($student);
    }
}
