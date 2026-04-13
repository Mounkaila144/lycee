<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoefficientTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'coefficient_templates';

    protected $fillable = [
        'name',
        'description',
        'evaluations',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'evaluations' => 'array',
            'is_system' => 'boolean',
        ];
    }

    // Scopes

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    public function scopeCustom($query)
    {
        return $query->where('is_system', false);
    }

    // Business Logic

    public function getTotalCoefficientAttribute(): float
    {
        if (! is_array($this->evaluations)) {
            return 0;
        }

        return collect($this->evaluations)->sum('coefficient');
    }

    public function getEvaluationCountAttribute(): int
    {
        return is_array($this->evaluations) ? count($this->evaluations) : 0;
    }

    /**
     * Get default system templates
     */
    public static function getDefaultTemplates(): array
    {
        return [
            [
                'name' => 'Standard LMD',
                'description' => 'Template standard pour le système LMD',
                'evaluations' => [
                    ['type' => 'CC', 'coefficient' => 1, 'max_score' => 20],
                    ['type' => 'TP', 'coefficient' => 1, 'max_score' => 20],
                    ['type' => 'Examen', 'coefficient' => 2, 'max_score' => 20],
                ],
                'is_system' => true,
            ],
            [
                'name' => 'Sciences Expérimentales',
                'description' => 'Template pour les sciences expérimentales avec plus de poids sur les TP',
                'evaluations' => [
                    ['type' => 'CC', 'coefficient' => 1, 'max_score' => 20],
                    ['type' => 'TP', 'coefficient' => 2, 'max_score' => 20],
                    ['type' => 'Examen', 'coefficient' => 2, 'max_score' => 20],
                ],
                'is_system' => true,
            ],
            [
                'name' => 'Sciences Humaines',
                'description' => 'Template pour les sciences humaines avec exposés',
                'evaluations' => [
                    ['type' => 'CC', 'coefficient' => 2, 'max_score' => 20],
                    ['type' => 'Exposé', 'coefficient' => 1, 'max_score' => 20],
                    ['type' => 'Examen', 'coefficient' => 2, 'max_score' => 20],
                ],
                'is_system' => true,
            ],
            [
                'name' => 'Informatique',
                'description' => 'Template pour l\'informatique avec projets',
                'evaluations' => [
                    ['type' => 'CC', 'coefficient' => 1, 'max_score' => 20],
                    ['type' => 'Projet', 'coefficient' => 2, 'max_score' => 20],
                    ['type' => 'Examen', 'coefficient' => 2, 'max_score' => 20],
                ],
                'is_system' => true,
            ],
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Modules\NotesEvaluations\Database\Factories\CoefficientTemplateFactory::new();
    }
}
