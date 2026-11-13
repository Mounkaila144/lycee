<?php

namespace Modules\Documents\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Documents\Database\Factories\DocumentTemplateFactory;

class DocumentTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): DocumentTemplateFactory
    {
        return DocumentTemplateFactory::new();
    }

    protected $connection = 'tenant';

    protected $table = 'document_templates';

    protected $fillable = [
        'type',
        'name',
        'description',
        'content_template',
        'header_html',
        'footer_html',
        'watermark',
        'variables',
        'settings',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Relations
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'template_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Business Logic
     */
    /**
     * Replace {{placeholders}} with HTML-escaped scalar values.
     * Non-scalar values (arrays of grades, history...) are ignored and any
     * placeholder left unresolved is removed.
     *
     * @param  array<string, mixed>  $data
     */
    public function renderTemplate(array $data): string
    {
        $template = (string) $this->content_template;

        foreach ($data as $key => $value) {
            if ($value !== null && ! is_scalar($value)) {
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? 'Oui' : 'Non';
            }

            $template = str_replace('{{'.$key.'}}', e((string) $value), $template);
        }

        return (string) preg_replace('/\{\{\s*[a-zA-Z0-9_]+\s*\}\}/', '', $template);
    }

    public function getAvailableVariables(): array
    {
        return $this->variables ?? [];
    }

    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }
}
