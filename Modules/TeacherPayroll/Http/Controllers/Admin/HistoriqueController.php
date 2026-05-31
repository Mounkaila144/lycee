<?php

namespace Modules\TeacherPayroll\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\TeacherPayroll\Entities\HistoriqueActionPaie;

class HistoriqueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = HistoriqueActionPaie::on('tenant');

        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }
        if ($entite = $request->input('entite')) {
            $query->where('entite', $entite);
        }

        return response()->json($query->orderBy('date_action', 'desc')->paginate($request->input('per_page', 30)));
    }
}
