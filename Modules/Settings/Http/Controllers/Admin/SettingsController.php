<?php

namespace Modules\Settings\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Settings\Entities\TenantSetting;

/**
 * Story Admin 13 — Réglages de l'établissement.
 *
 * Accès strictement réservé Administrator (cf. routes admin.php).
 */
class SettingsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TenantSetting::query();

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        return response()->json(['data' => $query->orderBy('category')->orderBy('key')->get()]);
    }

    public function show(string $key): JsonResponse
    {
        $setting = TenantSetting::where('key', $key)->firstOrFail();

        return response()->json(['data' => $setting]);
    }

    public function upsert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:100'],
            'value' => ['nullable', 'string'],
            'type' => ['required', 'in:string,integer,boolean,json,file'],
            'category' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $setting = TenantSetting::updateOrCreate(
            ['key' => $validated['key']],
            $validated,
        );

        return response()->json([
            'message' => 'Réglage enregistré.',
            'data' => $setting,
        ], $setting->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(string $key): JsonResponse
    {
        $setting = TenantSetting::where('key', $key)->firstOrFail();
        $setting->delete();

        return response()->json(['message' => 'Réglage supprimé.']);
    }
}
