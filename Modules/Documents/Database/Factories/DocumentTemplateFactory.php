<?php

namespace Modules\Documents\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Documents\Entities\DocumentTemplate;

class DocumentTemplateFactory extends Factory
{
    protected $model = DocumentTemplate::class;

    public function definition(): array
    {
        return [
            'type' => $this->faker->randomElement([
                'transcript_semester',
                'transcript_global',
                'diploma',
                'certificate_enrollment',
                'certificate_status',
                'student_card',
            ]),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'content_template' => '<html><body><h1>{{student_name}}</h1><p>{{document_number}}</p></body></html>',
            'header_html' => '<div class="header">{{institution_name}}</div>',
            'footer_html' => '<div class="footer">Page {{page_number}}</div>',
            'watermark' => null,
            'variables' => ['student_name', 'document_number', 'issue_date'],
            'settings' => [
                'paper_size' => 'a4',
                'orientation' => 'portrait',
                'margin_top' => 10,
                'margin_bottom' => 10,
                'margin_left' => 10,
                'margin_right' => 10,
            ],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function ofType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
        ]);
    }
}
