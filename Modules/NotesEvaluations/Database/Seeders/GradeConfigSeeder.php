<?php

namespace Modules\NotesEvaluations\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\NotesEvaluations\Entities\CoefficientTemplate;
use Modules\NotesEvaluations\Entities\GradeConfig;

class GradeConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default grade config if not exists
        if (! GradeConfig::exists()) {
            GradeConfig::create([
                'min_module_average' => 10.00,
                'min_semester_average' => 10.00,
                'compensation_enabled' => true,
                'eliminatory_threshold' => 10.00,
                'allow_eliminatory_compensation' => false,
                'year_progression_threshold' => 80,
            ]);
        }

        // Create default coefficient templates
        $templates = CoefficientTemplate::getDefaultTemplates();

        foreach ($templates as $template) {
            CoefficientTemplate::firstOrCreate(
                ['name' => $template['name'], 'is_system' => true],
                $template
            );
        }
    }
}
