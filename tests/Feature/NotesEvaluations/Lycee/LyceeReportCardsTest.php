<?php

namespace Tests\Feature\NotesEvaluations\Lycee;

use Modules\NotesEvaluations\Entities\ReportCardComment;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Notes lycée — bulletins PDF (élève, classe) et appréciations.
 */
class LyceeReportCardsTest extends TestCase
{
    use InteractsWithLyceeGrades;

    private function studentPdfUri(string $key): string
    {
        return "/api/admin/lycee-grades/report-cards/students/{$this->students[$key]->id}/pdf?semester_id={$this->s1->id}";
    }

    private function classPdfUri(): string
    {
        return "/api/admin/lycee-grades/report-cards/classes/{$this->classe->id}/pdf?semester_id={$this->s1->id}";
    }

    #[Test]
    public function admin_downloads_a_student_report_card(): void
    {
        $this->seedReferenceGrades();

        $response = $this->downloadAs($this->admin, $this->studentPdfUri('A'))->assertOk();

        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('bulletin_eleve-alpha_S1.pdf', (string) $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    #[Test]
    public function report_card_view_renders_the_key_figures(): void
    {
        $this->seedReferenceGrades();
        $report = app(\Modules\NotesEvaluations\Services\Lycee\LyceeReportService::class)
            ->studentReport($this->students['A'], $this->classe->load(['headTeacher', 'academicYear']), $this->s1, true);

        $html = view('notesevaluations::lycee.report-card', [
            'reports' => [$report],
            'classe' => $this->classe,
            'semester' => $this->s1,
            'academicYear' => $this->year,
            'establishment' => ['name' => 'Lycée Test'],
            'isProvisional' => false,
            'generatedAt' => now(),
        ])->render();

        $this->assertStringContainsString('Lycée Test', $html);
        $this->assertStringContainsString('Élève Alpha', $html);
        $this->assertStringContainsString('12,47', $html);
        $this->assertStringContainsString('14,33', $html);
        $this->assertStringContainsString('57,32', $html);
        $this->assertStringContainsString('Assez bien', $html);
        $this->assertStringContainsString('2e / 4', $html);
    }

    #[Test]
    public function student_and_parent_get_the_pdf_only_once_something_is_published(): void
    {
        $parent = $this->parentOf($this->students['A']);
        $this->evaluation($this->maths, ['A' => 12], ['status' => 'draft', 'published_at' => null]);

        $this->downloadAs($this->studentUser('A'), $this->studentPdfUri('A'))->assertForbidden();
        $this->downloadAs($parent, $this->studentPdfUri('A'))->assertForbidden();

        $this->evaluation($this->french, ['A' => 8], ['max_score' => 10]);

        $this->downloadAs($this->studentUser('A'), $this->studentPdfUri('A'))->assertOk();
        $this->downloadAs($parent, $this->studentPdfUri('A'))->assertOk();
    }

    #[Test]
    public function student_parent_and_plain_teacher_cannot_download_other_report_cards(): void
    {
        $this->seedReferenceGrades();

        $this->downloadAs($this->studentUser('A'), $this->studentPdfUri('B'))->assertForbidden();
        $this->downloadAs($this->parentOf($this->students['A']), $this->studentPdfUri('B'))->assertForbidden();
        $this->downloadAs($this->mathTeacher, $this->studentPdfUri('B'))->assertForbidden();
        $this->downloadAs($this->headTeacher, $this->studentPdfUri('B'))->assertOk();
    }

    #[Test]
    public function class_pdf_is_reserved_to_admin_manager_and_head_teacher(): void
    {
        $this->seedReferenceGrades();

        $response = $this->downloadAs($this->admin, $this->classPdfUri())->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));

        $this->downloadAs($this->userWithRole('Manager'), $this->classPdfUri())->assertOk();
        $this->downloadAs($this->headTeacher, $this->classPdfUri())->assertOk();
        $this->downloadAs($this->mathTeacher, $this->classPdfUri())->assertForbidden();
        $this->downloadAs($this->studentUser('A'), $this->classPdfUri())->assertForbidden();
    }

    #[Test]
    public function subject_teacher_writes_subject_appreciation_only(): void
    {
        $payload = ['student_id' => $this->students['A']->id, 'semester_id' => $this->s1->id, 'subject_id' => $this->maths->id, 'comment' => 'Élève sérieux'];

        $this->putAs($this->mathTeacher, '/api/admin/lycee-grades/report-cards/comments', $payload)
            ->assertOk()
            ->assertJsonPath('data.comment', 'Élève sérieux');

        $this->putAs($this->mathTeacher, '/api/admin/lycee-grades/report-cards/comments', ['comment' => 'Peut mieux faire'] + $payload)
            ->assertOk();
        $this->assertSame(1, ReportCardComment::query()->where('student_id', $this->students['A']->id)->count());

        $this->putAs($this->frenchTeacher, '/api/admin/lycee-grades/report-cards/comments', $payload)->assertForbidden();
        $this->putAs($this->mathTeacher, '/api/admin/lycee-grades/report-cards/comments', ['subject_id' => null] + $payload)->assertForbidden();
        $this->putAs($this->studentUser('A'), '/api/admin/lycee-grades/report-cards/comments', $payload)->assertForbidden();
    }

    #[Test]
    public function head_teacher_writes_and_clears_the_general_appreciation(): void
    {
        $payload = ['student_id' => $this->students['A']->id, 'semester_id' => $this->s1->id, 'subject_id' => null, 'comment' => 'Bon semestre'];

        $this->putAs($this->headTeacher, '/api/admin/lycee-grades/report-cards/comments', $payload)->assertOk();

        $this->getAs($this->admin, "/api/admin/lycee-grades/results/students/{$this->students['A']->id}?semester_id={$this->s1->id}")
            ->assertJsonPath('data.general_appreciation', 'Bon semestre');

        $this->putAs($this->admin, '/api/admin/lycee-grades/report-cards/comments', ['comment' => ''] + $payload)
            ->assertOk()
            ->assertJsonPath('data', null);
        $this->assertSame(0, ReportCardComment::query()->count());

        $this->putAs($this->headTeacher, '/api/admin/lycee-grades/report-cards/comments', ['comment' => str_repeat('a', 301)] + $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['comment']);
    }
}
