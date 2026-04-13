<?php

namespace Modules\UsersGuard\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\UsersGuard\Entities\Tenant;

class IndexController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'UsersGuard Superadmin Module',
            'tenants_count' => Tenant::count(),
        ]);
    }
}
