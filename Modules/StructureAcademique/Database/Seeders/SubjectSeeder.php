<?php

namespace Modules\StructureAcademique\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\StructureAcademique\Entities\Subject;

class SubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $subjects = [
            ['code' => 'MATH', 'name' => 'Mathématiques', 'short_name' => 'Maths', 'category' => 'sciences'],
            ['code' => 'FRAN', 'name' => 'Français', 'short_name' => 'Français', 'category' => 'lettres'],
            ['code' => 'PHYS', 'name' => 'Physique-Chimie', 'short_name' => 'PC', 'category' => 'sciences'],
            ['code' => 'SVT', 'name' => 'Sciences de la Vie et de la Terre', 'short_name' => 'SVT', 'category' => 'sciences'],
            ['code' => 'HG', 'name' => 'Histoire-Géographie', 'short_name' => 'HG', 'category' => 'sciences_humaines'],
            ['code' => 'ANG', 'name' => 'Anglais', 'short_name' => 'Anglais', 'category' => 'langues'],
            ['code' => 'PHIL', 'name' => 'Philosophie', 'short_name' => 'Philo', 'category' => 'lettres'],
            ['code' => 'EPS', 'name' => 'Éducation Physique et Sportive', 'short_name' => 'EPS', 'category' => 'education_physique'],
            ['code' => 'EC', 'name' => 'Éducation Civique', 'short_name' => 'EC', 'category' => 'sciences_humaines'],
            ['code' => 'INF', 'name' => 'Informatique', 'short_name' => 'Info', 'category' => 'sciences'],
            ['code' => 'ARAB', 'name' => 'Arabe', 'short_name' => 'Arabe', 'category' => 'langues'],
            ['code' => 'ALL', 'name' => 'Allemand', 'short_name' => 'Allemand', 'category' => 'langues'],
            ['code' => 'ESP', 'name' => 'Espagnol', 'short_name' => 'Espagnol', 'category' => 'langues'],
        ];

        foreach ($subjects as $subject) {
            Subject::on('tenant')->updateOrCreate(
                ['code' => $subject['code']],
                $subject
            );
        }
    }
}
