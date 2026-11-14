<?php

namespace Modules\Payroll\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Payroll\Entities\PayrollSetting;
use Modules\Payroll\Http\Requests\SimulatePayrollRequest;
use Modules\Payroll\Http\Requests\StorePayrollSettingsRequest;
use Modules\Payroll\Services\PayrollCalculator;
use Modules\Payroll\Services\PayrollSettingsService;

/**
 * Paramètres de paie paramétrables par école (versionnés par date d'effet) et simulateur.
 * Lecture : Administrator, Manager, Comptable — écriture : Administrator, Comptable.
 */
class PayrollSettingsController extends Controller
{
    public function __construct(
        private PayrollSettingsService $settingsService,
        private PayrollCalculator $calculator
    ) {}

    /**
     * Paramètres en vigueur aujourd'hui + versions programmées.
     */
    public function show(): JsonResponse
    {
        $today = now()->toDateString();
        $resolved = $this->settingsService->resolveForDate($today);
        $history = $this->settingsService->history();

        return response()->json([
            'data' => [
                'current' => array_merge($resolved['version'], ['settings' => $resolved['settings']]),
                'upcoming' => $history
                    ->filter(fn (PayrollSetting $version): bool => $version->effective_date->toDateString() > $today)
                    ->sortBy('effective_date')
                    ->values()
                    ->map(fn (PayrollSetting $version): array => $this->presentVersion($version)),
                'storage_available' => $this->settingsService->storageAvailable(),
            ],
        ]);
    }

    /**
     * Historique complet des versions.
     */
    public function versions(): JsonResponse
    {
        return response()->json([
            'data' => $this->settingsService->history()
                ->map(fn (PayrollSetting $version): array => $this->presentVersion($version))
                ->values(),
        ]);
    }

    /**
     * Valeurs par défaut (Niger), à vérifier par le comptable.
     */
    public function defaults(): JsonResponse
    {
        return response()->json(['data' => $this->settingsService->defaults()]);
    }

    /**
     * Enregistre une nouvelle version (l'historique est conservé ; les paies déjà calculées ne changent pas).
     */
    public function store(StorePayrollSettingsRequest $request): JsonResponse
    {
        $version = $this->settingsService->createVersion(
            $request->validated('settings'),
            $request->validated('effective_date'),
            $request->validated('notes'),
            auth('tenant')->id()
        );

        return response()->json([
            'message' => "Paramètres de paie enregistrés (version {$version->version}, en vigueur au {$version->effective_date->format('d/m/Y')})",
            'data' => $this->presentVersion($version),
        ], 201);
    }

    /**
     * Simule un bulletin à partir d'un brut, avec les paramètres fournis (brouillon)
     * ou ceux en vigueur à la date donnée.
     */
    public function simulate(SimulatePayrollRequest $request): JsonResponse
    {
        if (is_array($request->validated('settings'))) {
            $settings = $this->settingsService->normalize($request->validated('settings'));
            $version = ['id' => null, 'version' => null, 'effective_date' => null, 'is_default' => false, 'is_draft' => true];
        } else {
            $resolved = $this->settingsService->resolveForDate($request->validated('date') ?? now()->toDateString());
            $settings = $resolved['settings'];
            $version = array_merge($resolved['version'], ['is_draft' => false]);
        }

        return response()->json([
            'data' => array_merge(
                $this->calculator->calculate((float) $request->validated('gross_salary'), $settings),
                ['settings_version' => $version]
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentVersion(PayrollSetting $version): array
    {
        return [
            'id' => $version->id,
            'version' => $version->version,
            'effective_date' => $version->effective_date?->toDateString(),
            'settings' => $this->settingsService->normalize($version->settings ?? []),
            'notes' => $version->notes,
            'created_by' => $version->created_by,
            'created_at' => $version->created_at?->toIso8601String(),
            'is_default' => false,
        ];
    }
}
