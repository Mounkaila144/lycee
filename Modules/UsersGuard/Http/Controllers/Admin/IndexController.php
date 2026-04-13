<?php

namespace Modules\UsersGuard\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class IndexController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'UsersGuard Admin Module',
            'tenant' => [
                'id' => tenancy()->tenant?->getTenantKey(),
                'name' => tenancy()->tenant?->company_name,
            ],
        ]);
    }
}
