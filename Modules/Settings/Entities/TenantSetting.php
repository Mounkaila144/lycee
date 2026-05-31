<?php

namespace Modules\Settings\Entities;

use Illuminate\Database\Eloquent\Model;

class TenantSetting extends Model
{
    protected $connection = 'tenant';

    protected $table = 'tenant_settings';

    protected $fillable = ['key', 'value', 'type', 'category', 'description'];

    protected function casts(): array
    {
        return [];
    }

    public function getTypedValueAttribute(): mixed
    {
        return match ($this->type) {
            'integer' => (int) $this->value,
            'boolean' => (bool) $this->value,
            'json' => json_decode($this->value ?? 'null', true),
            default => $this->value,
        };
    }

    /**
     * School identity fields exposed in documents (invoices, certificates…).
     */
    public const SCHOOL_FIELDS = ['name', 'nif', 'rccm', 'address', 'city', 'bp', 'phone', 'email', 'website', 'logo'];

    /**
     * Read the school profile (category "school") as a flat array.
     * `logo` is a base64 data URI (or null).
     *
     * @return array<string, string|null>
     */
    public static function schoolProfile(): array
    {
        $rows = static::query()->where('category', 'school')->pluck('value', 'key');

        $profile = [];
        foreach (self::SCHOOL_FIELDS as $field) {
            $profile[$field] = $rows->get('school.'.$field);
        }

        return $profile;
    }

    /**
     * Standard school header HTML for inline-rendered PDFs (receipts,
     * certificates, report cards…). Self-contained inline styles so it can be
     * dropped into any DomPDF document. Falls back gracefully when unset.
     */
    public static function schoolHeaderHtml(): string
    {
        $p = static::schoolProfile();

        $logo = ! empty($p['logo'])
            ? '<img src="'.e($p['logo']).'" style="max-height:62px;max-width:110px;margin-bottom:4px;">'
            : '';

        $name = ! empty($p['name']) ? e($p['name']) : 'Établissement scolaire';

        $bp = $p['bp'] ?? null;
        if ($bp && ! preg_match('/^\s*BP\b/i', $bp)) {
            $bp = 'BP '.$bp;
        }

        $contactParts = array_filter([
            $p['address'] ?? null,
            $p['city'] ?? null,
            $bp,
            ! empty($p['phone']) ? 'Tél : '.$p['phone'] : null,
            $p['email'] ?? null,
        ]);
        $contact = e(implode(' · ', $contactParts));

        $idParts = array_filter([
            ! empty($p['nif']) ? 'NIF : '.$p['nif'] : null,
            ! empty($p['rccm']) ? 'RCCM : '.$p['rccm'] : null,
        ]);
        $idLine = e(implode(' · ', $idParts));

        return <<<HTML
<div style="text-align:center;margin-bottom:18px;border-bottom:2px solid #1565c0;padding-bottom:8px;">
  {$logo}
  <div style="font-size:12px;font-weight:bold;">RÉPUBLIQUE DU NIGER</div>
  <div style="font-size:10px;color:#555;">Ministère de l'Éducation Nationale</div>
  <div style="font-size:14px;font-weight:bold;color:#1565c0;margin-top:3px;">{$name}</div>
  <div style="font-size:9px;color:#555;">{$contact}</div>
  <div style="font-size:9px;color:#555;">{$idLine}</div>
</div>
HTML;
    }
}
