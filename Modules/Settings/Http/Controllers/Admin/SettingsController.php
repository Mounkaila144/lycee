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

    /**
     * School identity profile (logo, name, NIF, address…) used in documents.
     */
    public function schoolProfile(): JsonResponse
    {
        return response()->json(['data' => TenantSetting::schoolProfile()]);
    }

    /**
     * Upsert the school profile. Text fields + an optional logo image
     * (stored inline as a base64 data URI so PDFs can embed it directly).
     */
    public function updateSchoolProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'nif' => ['nullable', 'string', 'max:100'],
            'rccm' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'bp' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'website' => ['nullable', 'string', 'max:150'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
        ]);

        foreach (['name', 'nif', 'rccm', 'address', 'city', 'bp', 'phone', 'email', 'website'] as $field) {
            if ($request->exists($field)) {
                TenantSetting::updateOrCreate(
                    ['key' => 'school.'.$field],
                    ['value' => $validated[$field] ?? null, 'type' => 'string', 'category' => 'school'],
                );
            }
        }

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $dataUri = 'data:'.$file->getMimeType().';base64,'.base64_encode(file_get_contents($file->getRealPath()));
            TenantSetting::updateOrCreate(
                ['key' => 'school.logo'],
                ['value' => $dataUri, 'type' => 'file', 'category' => 'school'],
            );
        }

        return response()->json([
            'message' => 'Profil de l\'établissement enregistré.',
            'data' => TenantSetting::schoolProfile(),
        ]);
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
