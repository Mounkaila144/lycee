<?php

namespace Modules\Settings\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Settings\Entities\TenantSetting;
use Throwable;

/**
 * Profil de l'établissement (nom, en-tête officiel, coordonnées, chef d'établissement, logo, cachet).
 *
 * Stocké dans tenant_settings (clés « school.<champ> », catégorie « school »). Les images sont
 * enregistrées sur le disque « local » du tenant (privé) et intégrées en data URI dans les PDF.
 */
class SchoolProfileService
{
    public const CATEGORY = 'school';

    public const IMAGES = ['logo', 'stamp'];

    public const TYPES = ['Lycée', 'CEG', 'Collège', 'Complexe scolaire'];

    public const DEFAULT_OFFICIAL_HEADER = "RÉPUBLIQUE DU NIGER\nFraternité – Travail – Progrès\nMINISTÈRE DE L'ÉDUCATION NATIONALE";

    /**
     * Champs texte du profil et leur valeur par défaut.
     *
     * @var array<string, string|null>
     */
    public const FIELDS = [
        'name' => null,
        'short_name' => null,
        'type' => 'Lycée',
        'motto' => null,
        'official_header' => self::DEFAULT_OFFICIAL_HEADER,
        'region' => null,
        'inspection' => null,
        'address' => null,
        'city' => null,
        'po_box' => null,
        'phone' => null,
        'phone_secondary' => null,
        'email' => null,
        'website' => null,
        'authorization_number' => null,
        'head_title' => 'Le Proviseur',
        'head_name' => null,
        'censor_name' => null,
        'country' => 'Niger',
        'currency' => 'FCFA',
    ];

    /**
     * Anciennes clés possibles du nom de l'établissement (réglages clé/valeur libres).
     */
    private const LEGACY_NAME_KEYS = ['school_name', 'establishment_name', 'etablissement_nom', 'institution_name', 'app_name'];

    private const MAX_IMAGE_SIZE = 600;

    /**
     * @var array<string, string|null>|null
     */
    private ?array $cachedRows = null;

    /**
     * Profil complet pour l'API (valeurs par défaut appliquées).
     *
     * @return array<string, mixed>
     */
    public function profile(): array
    {
        $rows = $this->rows();
        $profile = [];

        foreach (self::FIELDS as $field => $default) {
            $value = $rows['school.'.$field] ?? null;
            $profile[$field] = ($value === null || $value === '') ? $default : $value;
        }

        $profile['display_name'] = $this->displayName($profile['name']);
        $profile['is_configured'] = ! empty($profile['name']);

        foreach (self::IMAGES as $image) {
            $profile['has_'.$image] = $this->imagePath($image) !== null;
        }

        $profile['updated_at'] = $this->lastUpdate();

        return $profile;
    }

    /**
     * Enregistre les champs texte fournis (les clés absentes ne sont pas modifiées).
     *
     * @param  array<string, string|null>  $data
     * @return array<string, mixed>
     */
    public function update(array $data): array
    {
        foreach (array_keys(self::FIELDS) as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field] === null ? null : trim((string) $data[$field]);

            TenantSetting::query()->updateOrCreate(
                ['key' => 'school.'.$field],
                ['value' => $value === '' ? null : $value, 'type' => 'string', 'category' => self::CATEGORY],
            );
        }

        $this->cachedRows = null;

        return $this->profile();
    }

    /**
     * Enregistre le logo ou le cachet (converti en PNG, redimensionné à 600 px max).
     */
    public function storeImage(string $image, UploadedFile $file): void
    {
        $previous = $this->imagePath($image);
        $path = 'school-profile/'.$image.'-'.now()->format('YmdHis').'.png';

        Storage::disk('local')->put($path, $this->normalizedPng($file));

        TenantSetting::query()->updateOrCreate(
            ['key' => 'school.'.$image.'_path'],
            ['value' => $path, 'type' => 'file', 'category' => self::CATEGORY],
        );

        if ($previous !== null && $previous !== $path) {
            Storage::disk('local')->delete($previous);
        }

        $this->cachedRows = null;
    }

    public function deleteImage(string $image): void
    {
        $path = $this->imagePath($image);

        if ($path !== null) {
            Storage::disk('local')->delete($path);
        }

        TenantSetting::query()->where('key', 'school.'.$image.'_path')->delete();
        $this->cachedRows = null;
    }

    /**
     * Chemin (disque local du tenant) de l'image, null si absente.
     */
    public function imagePath(string $image): ?string
    {
        $path = $this->rows()['school.'.$image.'_path'] ?? null;

        if ($path === null || $path === '') {
            return null;
        }

        try {
            return Storage::disk('local')->exists($path) ? $path : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function imageDataUri(string $image): ?string
    {
        $path = $this->imagePath($image);

        if ($path === null) {
            return null;
        }

        $content = Storage::disk('local')->get($path);

        return $content === null ? null : 'data:image/png;base64,'.base64_encode($content);
    }

    /**
     * Données prêtes pour les PDF (en-tête commun, signatures, pied de page).
     *
     * @return array<string, mixed>
     */
    public function forDocuments(): array
    {
        $profile = $this->profile();

        $poBox = $profile['po_box'];
        if ($poBox && ! preg_match('/^\s*B\.?\s?P\b/i', $poBox)) {
            $poBox = 'BP '.$poBox;
        }

        $phones = implode(' / ', array_filter([$profile['phone'], $profile['phone_secondary']]));

        return $profile + [
            'header_lines' => $this->lines($profile['official_header']),
            'logo_data_uri' => $this->imageDataUri('logo'),
            'stamp_data_uri' => $this->imageDataUri('stamp'),
            'po_box_label' => $poBox,
            'phones' => $phones !== '' ? $phones : null,
            'contact_line' => implode(' · ', array_filter([
                $profile['address'],
                $profile['city'],
                $poBox,
                $phones !== '' ? 'Tél. '.$phones : null,
                $profile['email'],
                $profile['website'],
            ])),
        ];
    }

    /**
     * Variables scalaires pour les modèles de documents ({{school_name}}, {{head_name}}…).
     *
     * @return array<string, string>
     */
    public function templateVariables(): array
    {
        $school = $this->forDocuments();

        return [
            'school_name' => (string) $school['display_name'],
            'school_short_name' => (string) ($school['short_name'] ?? ''),
            'school_type' => (string) ($school['type'] ?? ''),
            'school_motto' => (string) ($school['motto'] ?? ''),
            'school_address' => (string) ($school['address'] ?? ''),
            'school_city' => (string) ($school['city'] ?? ''),
            'school_po_box' => (string) ($school['po_box_label'] ?? ''),
            'school_phone' => (string) ($school['phones'] ?? ''),
            'school_email' => (string) ($school['email'] ?? ''),
            'school_website' => (string) ($school['website'] ?? ''),
            'school_region' => (string) ($school['region'] ?? ''),
            'school_inspection' => (string) ($school['inspection'] ?? ''),
            'school_authorization' => (string) ($school['authorization_number'] ?? ''),
            'school_contact' => (string) $school['contact_line'],
            'head_title' => (string) $school['head_title'],
            'head_name' => (string) ($school['head_name'] ?? ''),
            'censor_name' => (string) ($school['censor_name'] ?? ''),
            'issue_place' => (string) ($school['city'] ?: '………………'),
        ];
    }

    /**
     * Fragments HTML (non échappés) pour les modèles : {{school_header}}, {{school_footer}}, {{school_stamp}}.
     *
     * @return array<string, string>
     */
    public function templateHtmlVariables(): array
    {
        $school = $this->forDocuments();

        return [
            'school_header' => view('settings::pdf.header', ['school' => $school, 'size' => 'large'])->render(),
            'school_footer' => view('settings::pdf.footer', ['school' => $school])->render(),
            'school_stamp' => $school['stamp_data_uri']
                ? '<img src="'.$school['stamp_data_uri'].'" style="max-height:80px;max-width:160px;" alt="">'
                : '',
        ];
    }

    private function displayName(?string $name): string
    {
        if (! empty($name)) {
            return $name;
        }

        $rows = $this->rows();
        foreach (self::LEGACY_NAME_KEYS as $key) {
            if (! empty($rows[$key])) {
                return (string) $rows[$key];
            }
        }

        $tenant = function_exists('tenant') ? tenant() : null;
        $tenantName = $tenant?->getAttribute('company_name') ?: $tenant?->getAttribute('name');

        return (string) ($tenantName ?: config('app.name'));
    }

    /**
     * @return list<string>
     */
    private function lines(?string $text): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/', (string) $text) ?: []),
            fn (string $line): bool => $line !== '',
        ));
    }

    private function lastUpdate(): ?string
    {
        if (! $this->tableExists()) {
            return null;
        }

        $updatedAt = TenantSetting::query()->where('category', self::CATEGORY)->max('updated_at');

        return $updatedAt ? (string) $updatedAt : null;
    }

    /**
     * @return array<string, string|null>
     */
    private function rows(): array
    {
        if ($this->cachedRows !== null) {
            return $this->cachedRows;
        }

        if (! $this->tableExists()) {
            return $this->cachedRows = [];
        }

        return $this->cachedRows = TenantSetting::query()
            ->where('category', self::CATEGORY)
            ->orWhereIn('key', self::LEGACY_NAME_KEYS)
            ->pluck('value', 'key')
            ->all();
    }

    private function tableExists(): bool
    {
        try {
            return Schema::connection('tenant')->hasTable('tenant_settings');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * PNG redimensionné (transparence conservée) ; contenu brut si GD indisponible.
     */
    private function normalizedPng(UploadedFile $file): string
    {
        $content = (string) file_get_contents($file->getRealPath());

        if (! function_exists('imagecreatefromstring')) {
            return $content;
        }

        $source = @imagecreatefromstring($content);
        if ($source === false) {
            return $content;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $ratio = min(1, self::MAX_IMAGE_SIZE / max($width, $height));
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagepng($target, null, 6);
        $png = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($target);

        return $png;
    }
}
