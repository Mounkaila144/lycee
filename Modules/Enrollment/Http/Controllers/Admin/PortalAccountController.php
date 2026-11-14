<?php

namespace Modules\Enrollment\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Http\Requests\CreatePortalAccountRequest;
use Modules\Enrollment\Http\Requests\UpdatePortalAccountStatusRequest;
use Modules\Enrollment\Services\PortalAccountService;
use Modules\PortailParent\Entities\ParentModel;
use Modules\UsersGuard\Entities\TenantUser;

/**
 * Comptes de connexion des élèves et des parents (Admin | Manager).
 *
 * Le mot de passe généré n'est renvoyé qu'une fois, dans la réponse de création ou de réinitialisation.
 */
class PortalAccountController extends Controller
{
    public function __construct(private PortalAccountService $accountService) {}

    public function showForStudent(int $studentId): JsonResponse
    {
        $student = Student::query()->with('user.roles')->findOrFail($studentId);

        return response()->json(['data' => $this->accountService->present($student->user)]);
    }

    public function storeForStudent(CreatePortalAccountRequest $request, int $studentId): JsonResponse
    {
        $student = Student::query()->findOrFail($studentId);

        $result = $this->accountService->createForStudent($student, $request->validated('username'));

        return $this->credentialsResponse('Compte élève créé.', $result['account'], $result['password'], 201);
    }

    public function resetStudentPassword(int $studentId): JsonResponse
    {
        $student = Student::query()->with('user.roles')->findOrFail($studentId);

        $password = $this->accountService->resetPassword($student->user);

        return $this->credentialsResponse('Mot de passe réinitialisé.', $student->user, $password);
    }

    public function updateStudentStatus(UpdatePortalAccountStatusRequest $request, int $studentId): JsonResponse
    {
        $student = Student::query()->with('user.roles')->findOrFail($studentId);

        $account = $this->accountService->setActive($student->user, $request->boolean('is_active'));

        return response()->json([
            'message' => $account->is_active ? 'Compte activé.' : 'Compte désactivé.',
            'data' => $this->accountService->present($account),
        ]);
    }

    public function storeForParent(CreatePortalAccountRequest $request, int $parentId): JsonResponse
    {
        $parent = ParentModel::query()->findOrFail($parentId);

        $result = $this->accountService->createForParent($parent, $request->validated('username'));

        return $this->credentialsResponse('Compte parent créé.', $result['account'], $result['password'], 201);
    }

    public function resetParentPassword(int $parentId): JsonResponse
    {
        $parent = ParentModel::query()->with('user.roles')->findOrFail($parentId);

        $password = $this->accountService->resetPassword($parent->user);

        return $this->credentialsResponse('Mot de passe réinitialisé.', $parent->user, $password);
    }

    public function updateParentStatus(UpdatePortalAccountStatusRequest $request, int $parentId): JsonResponse
    {
        $parent = ParentModel::query()->with('user.roles')->findOrFail($parentId);

        $account = $this->accountService->setActive($parent->user, $request->boolean('is_active'));

        return response()->json([
            'message' => $account->is_active ? 'Compte activé.' : 'Compte désactivé.',
            'data' => $this->accountService->present($account),
        ]);
    }

    private function credentialsResponse(string $message, ?TenantUser $account, string $password, int $status = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => [
                'account' => $this->accountService->present($account),
                'password' => $password,
            ],
        ], $status)->header('Cache-Control', 'no-store');
    }
}
