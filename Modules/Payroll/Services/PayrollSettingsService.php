<?php

namespace Modules\Payroll\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Payroll\Entities\PayrollSetting;
use Modules\Payroll\Exceptions\PayrollException;
use Throwable;

/**
 * Paramètres de paie de l'école, versionnés par date d'effet.
 *
 * Tolérant : si la table `payroll_settings` n'existe pas encore (migration non
 * exécutée) ou ne contient aucune version applicable, les valeurs par défaut
 * (Niger, à vérifier par le comptable) sont utilisées.
 */
class PayrollSettingsService
{
    /**
     * Valeurs par défaut (Niger) définies dans Modules/Payroll/Config/config.php.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return $this->normalize((array) config('payroll.default_settings', []));
    }

    /**
     * La table de stockage est-elle installée ?
     */
    public function storageAvailable(): bool
    {
        try {
            return Schema::connection('tenant')->hasTable('payroll_settings');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Version en vigueur à une date (null si aucune : valeurs par défaut).
     */
    public function versionAt(CarbonInterface|string $date): ?PayrollSetting
    {
        if (! $this->storageAvailable()) {
            return null;
        }

        $day = $date instanceof CarbonInterface ? $date->toDateString() : Carbon::parse($date)->toDateString();

        return PayrollSetting::on('tenant')->effectiveAt($day)->first();
    }

    /**
     * Paramètres applicables à une date, avec les métadonnées de version.
     *
     * @return array{settings: array<string, mixed>, version: array{id: int|null, version: int|null, effective_date: string|null, is_default: bool}}
     */
    public function resolveForDate(CarbonInterface|string $date): array
    {
        $version = $this->versionAt($date);

        if (! $version) {
            return [
                'settings' => $this->defaults(),
                'version' => ['id' => null, 'version' => null, 'effective_date' => null, 'is_default' => true],
            ];
        }

        return [
            'settings' => $this->normalize($version->settings ?? []),
            'version' => [
                'id' => $version->id,
                'version' => $version->version,
                'effective_date' => $version->effective_date?->toDateString(),
                'is_default' => false,
            ],
        ];
    }

    /**
     * Historique complet des versions (plus récente d'abord).
     *
     * @return \Illuminate\Support\Collection<int, PayrollSetting>
     */
    public function history(): \Illuminate\Support\Collection
    {
        if (! $this->storageAvailable()) {
            return collect();
        }

        return PayrollSetting::on('tenant')
            ->orderByDesc('effective_date')
            ->orderByDesc('version')
            ->get();
    }

    /**
     * Enregistre une nouvelle version (les versions existantes ne sont jamais modifiées).
     *
     * @param  array<string, mixed>  $settings
     */
    public function createVersion(array $settings, string $effectiveDate, ?string $notes, ?int $userId): PayrollSetting
    {
        if (! $this->storageAvailable()) {
            throw new PayrollException("Les paramètres de paie ne peuvent pas encore être enregistrés : la migration 'payroll_settings' n'a pas été exécutée.");
        }

        return DB::connection('tenant')->transaction(function () use ($settings, $effectiveDate, $notes, $userId) {
            $nextVersion = ((int) PayrollSetting::on('tenant')->lockForUpdate()->max('version')) + 1;

            return PayrollSetting::on('tenant')->create([
                'version' => $nextVersion,
                'effective_date' => Carbon::parse($effectiveDate)->toDateString(),
                'settings' => $this->normalize($settings),
                'notes' => $notes,
                'created_by' => $userId,
            ]);
        });
    }

    /**
     * Crée la version 1 avec les valeurs par défaut si aucune version n'existe (seeders).
     */
    public function ensureDefaultVersion(string $effectiveDate, ?int $userId = null): ?PayrollSetting
    {
        if (! $this->storageAvailable() || PayrollSetting::on('tenant')->exists()) {
            return null;
        }

        return $this->createVersion(
            $this->defaults(),
            $effectiveDate,
            'Valeurs par défaut Niger — à vérifier par le comptable.',
            $userId
        );
    }

    /**
     * Normalise une structure de paramètres (types, valeurs manquantes).
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function normalize(array $settings): array
    {
        $nullableNumber = fn (mixed $value): ?float => ($value === null || $value === '') ? null : (float) $value;

        $contributions = array_values(array_map(fn (array $line): array => [
            'code' => (string) ($line['code'] ?? ''),
            'label' => (string) ($line['label'] ?? ''),
            'employee_rate' => (float) ($line['employee_rate'] ?? 0),
            'employer_rate' => (float) ($line['employer_rate'] ?? 0),
            'ceiling' => $nullableNumber($line['ceiling'] ?? null),
            'floor' => $nullableNumber($line['floor'] ?? null),
            'active' => (bool) ($line['active'] ?? true),
            'tax_deductible' => (bool) ($line['tax_deductible'] ?? false),
        ], array_filter((array) ($settings['contributions'] ?? []), 'is_array')));

        $incomeTax = (array) ($settings['income_tax'] ?? []);
        $brackets = array_values(array_map(fn (array $bracket): array => [
            'from' => (float) ($bracket['from'] ?? 0),
            'to' => $nullableNumber($bracket['to'] ?? null),
            'rate' => (float) ($bracket['rate'] ?? 0),
        ], array_filter((array) ($incomeTax['brackets'] ?? []), 'is_array')));
        usort($brackets, fn (array $a, array $b): int => $a['from'] <=> $b['from']);

        $employerCharges = array_values(array_map(fn (array $line): array => [
            'code' => (string) ($line['code'] ?? ''),
            'label' => (string) ($line['label'] ?? ''),
            'rate' => (float) ($line['rate'] ?? 0),
            'active' => (bool) ($line['active'] ?? true),
        ], array_filter((array) ($settings['employer_charges'] ?? []), 'is_array')));

        return [
            'country' => (string) ($settings['country'] ?? 'NE'),
            'currency' => (string) ($settings['currency'] ?? 'XOF'),
            'currency_label' => (string) ($settings['currency_label'] ?? 'FCFA'),
            'rounding_precision' => max(0, min(2, (int) ($settings['rounding_precision'] ?? 0))),
            'requires_review' => (bool) ($settings['requires_review'] ?? false),
            'review_notice' => isset($settings['review_notice']) ? (string) $settings['review_notice'] : null,
            'contributions' => $contributions,
            'income_tax' => [
                'label' => (string) ($incomeTax['label'] ?? 'Impôt sur salaire'),
                'brackets' => $brackets,
                'flat_allowance_rate' => (float) ($incomeTax['flat_allowance_rate'] ?? 0),
                'deduct_employee_contributions' => (bool) ($incomeTax['deduct_employee_contributions'] ?? false),
            ],
            'employer_charges' => $employerCharges,
        ];
    }

    /**
     * Règles de validation d'une structure de paramètres (préfixe : 'settings.' ou '').
     *
     * @return array<string, array<int, mixed>>
     */
    public static function validationRules(string $prefix = 'settings.', bool $required = true): array
    {
        $root = rtrim($prefix, '.');

        return [
            $root => [$required ? 'required' : 'nullable', 'array'],
            "{$prefix}currency" => ['nullable', 'string', 'max:10'],
            "{$prefix}currency_label" => ["required_with:{$root}", 'string', 'max:10'],
            "{$prefix}rounding_precision" => ['nullable', 'integer', 'min:0', 'max:2'],
            "{$prefix}requires_review" => ['nullable', 'boolean'],
            "{$prefix}review_notice" => ['nullable', 'string', 'max:1000'],
            "{$prefix}contributions" => ['nullable', 'array', 'max:30'],
            "{$prefix}contributions.*.code" => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/', 'distinct'],
            "{$prefix}contributions.*.label" => ['required', 'string', 'max:150'],
            "{$prefix}contributions.*.employee_rate" => ['required', 'numeric', 'min:0', 'max:100'],
            "{$prefix}contributions.*.employer_rate" => ['required', 'numeric', 'min:0', 'max:100'],
            "{$prefix}contributions.*.ceiling" => ['nullable', 'numeric', 'min:0'],
            "{$prefix}contributions.*.floor" => ['nullable', 'numeric', 'min:0'],
            "{$prefix}contributions.*.active" => ['nullable', 'boolean'],
            "{$prefix}contributions.*.tax_deductible" => ['nullable', 'boolean'],
            "{$prefix}income_tax" => ["required_with:{$root}", 'array'],
            "{$prefix}income_tax.label" => ['nullable', 'string', 'max:150'],
            "{$prefix}income_tax.brackets" => ["required_with:{$root}", 'array', 'min:1', 'max:30'],
            "{$prefix}income_tax.brackets.*.from" => ['required', 'numeric', 'min:0'],
            "{$prefix}income_tax.brackets.*.to" => ['nullable', 'numeric', 'min:0'],
            "{$prefix}income_tax.brackets.*.rate" => ['required', 'numeric', 'min:0', 'max:100'],
            "{$prefix}income_tax.flat_allowance_rate" => ['nullable', 'numeric', 'min:0', 'max:100'],
            "{$prefix}income_tax.deduct_employee_contributions" => ['nullable', 'boolean'],
            "{$prefix}employer_charges" => ['nullable', 'array', 'max:20'],
            "{$prefix}employer_charges.*.code" => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/', 'distinct'],
            "{$prefix}employer_charges.*.label" => ['required', 'string', 'max:150'],
            "{$prefix}employer_charges.*.rate" => ['required', 'numeric', 'min:0', 'max:100'],
            "{$prefix}employer_charges.*.active" => ['nullable', 'boolean'],
        ];
    }

    /**
     * Messages de validation en français.
     *
     * @return array<string, string>
     */
    public static function validationMessages(string $prefix = 'settings.'): array
    {
        return [
            "{$prefix}contributions.*.code.required" => 'Chaque cotisation doit avoir un code.',
            "{$prefix}contributions.*.code.regex" => 'Le code de cotisation ne doit contenir que lettres, chiffres, « _ » ou « - ».',
            "{$prefix}contributions.*.code.distinct" => 'Deux cotisations ne peuvent pas avoir le même code.',
            "{$prefix}contributions.*.label.required" => 'Chaque cotisation doit avoir un libellé.',
            "{$prefix}contributions.*.employee_rate.*" => 'Le taux salarial doit être un pourcentage entre 0 et 100.',
            "{$prefix}contributions.*.employer_rate.*" => 'Le taux patronal doit être un pourcentage entre 0 et 100.',
            "{$prefix}contributions.*.ceiling.*" => 'Le plafond doit être un montant positif ou vide.',
            "{$prefix}contributions.*.floor.*" => 'Le plancher doit être un montant positif ou vide.',
            "{$prefix}income_tax.brackets.required_with" => "Le barème d'impôt doit contenir au moins une tranche.",
            "{$prefix}income_tax.brackets.min" => "Le barème d'impôt doit contenir au moins une tranche.",
            "{$prefix}income_tax.brackets.*.from.*" => 'Le début de tranche doit être un montant positif.',
            "{$prefix}income_tax.brackets.*.to.*" => 'La fin de tranche doit être un montant positif ou vide (dernière tranche).',
            "{$prefix}income_tax.brackets.*.rate.*" => 'Le taux de tranche doit être un pourcentage entre 0 et 100.',
            "{$prefix}income_tax.flat_allowance_rate.*" => "L'abattement forfaitaire doit être un pourcentage entre 0 et 100.",
            "{$prefix}employer_charges.*.code.distinct" => 'Deux charges patronales ne peuvent pas avoir le même code.',
            "{$prefix}employer_charges.*.code.regex" => 'Le code de charge ne doit contenir que lettres, chiffres, « _ » ou « - ».',
            "{$prefix}employer_charges.*.label.required" => 'Chaque charge patronale doit avoir un libellé.',
            "{$prefix}employer_charges.*.rate.*" => 'Le taux de charge patronale doit être un pourcentage entre 0 et 100.',
            "{$prefix}currency_label.required_with" => 'Le libellé de la devise est obligatoire.',
        ];
    }

    /**
     * Contrôles de cohérence non exprimables en règles simples
     * (tranches ordonnées sans chevauchement, plancher ≤ plafond).
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, string> Erreurs indexées par champ.
     */
    public static function structuralErrors(array $settings, string $prefix = 'settings.'): array
    {
        $errors = [];

        foreach ((array) ($settings['contributions'] ?? []) as $index => $line) {
            $ceiling = $line['ceiling'] ?? null;
            $floor = $line['floor'] ?? null;

            if (is_numeric($ceiling) && is_numeric($floor) && (float) $floor > (float) $ceiling) {
                $errors["{$prefix}contributions.{$index}.floor"] = 'Le plancher ne peut pas dépasser le plafond.';
            }
        }

        $brackets = array_values((array) ($settings['income_tax']['brackets'] ?? []));
        $count = count($brackets);

        foreach ($brackets as $index => $bracket) {
            if (! is_array($bracket) || ! is_numeric($bracket['from'] ?? null)) {
                continue;
            }

            $to = $bracket['to'] ?? null;
            $isLast = $index === $count - 1;

            if (($to === null || $to === '') && ! $isLast) {
                $errors["{$prefix}income_tax.brackets.{$index}.to"] = 'Seule la dernière tranche peut être sans limite supérieure.';
            }

            if (is_numeric($to) && (float) $to <= (float) $bracket['from']) {
                $errors["{$prefix}income_tax.brackets.{$index}.to"] = 'La fin de tranche doit être supérieure à son début.';
            }

            if ($index > 0) {
                $previousTo = $brackets[$index - 1]['to'] ?? null;

                if (is_numeric($previousTo) && (float) $bracket['from'] < (float) $previousTo) {
                    $errors["{$prefix}income_tax.brackets.{$index}.from"] = 'Les tranches doivent être ordonnées et ne pas se chevaucher.';
                }

                if (is_numeric($brackets[$index - 1]['from'] ?? null) && (float) $bracket['from'] <= (float) $brackets[$index - 1]['from']) {
                    $errors["{$prefix}income_tax.brackets.{$index}.from"] = 'Les tranches doivent être saisies par ordre croissant.';
                }
            }
        }

        return $errors;
    }
}
