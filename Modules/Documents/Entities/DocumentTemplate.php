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
    public function renderTemplate(array $data): string
    {
        $template = $this->content_template;

        foreach ($data as $key => $value) {
            $placeholder = '{{'.$key.'}}';
            $template = str_replace($placeholder, $value, $template);
        }

        return $template;
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
