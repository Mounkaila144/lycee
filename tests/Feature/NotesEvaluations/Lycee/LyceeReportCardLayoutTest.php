<?php

namespace Tests\Feature\NotesEvaluations\Lycee;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Attendance\Entities\AttendanceRecord;
use Modules\Attendance\Entities\AttendanceSession;
use Modules\Enrollment\Entities\ClassEnrollment;
use Modules\NotesEvaluations\Entities\ReportCardComment;
use Modules\NotesEvaluations\Services\Lycee\LyceeReportService;
use Modules\Settings\Services\SchoolProfileService;
use Modules\StructureAcademique\Entities\Subject;
use Modules\StructureAcademique\Entities\SubjectClassCoefficient;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bulletin lycée : en-tête de l'établissement, absences, décision du conseil, une page A4 par élève.
 */
class LyceeReportCardLayoutTest extends TestCase
{
    use InteractsWithLyceeGrades;

    private function configureSchool(): void
    {
        Storage::fake('local');
        $profile = app(SchoolProfileService::class);
        $profile->update([
            'name' => 'Lycée Issa Korombé',
            'short_name' => 'LIK',
            'motto' => 'Travail – Discipline – Réussite',
            'region' => 'Direction Régionale de l\'Éducation de Niamey',
            'inspection' => 'Inspection de l\'enseignement secondaire Niamey II',
            'address' => 'Quartier Plateau',
            'city' => 'Niamey',
            'po_box' => '1234',
            'phone' => '+227 20 73 00 00',
            'email' => 'contact@lik.ne',
            'authorization_number' => 'Arrêté n° 0123/MEN/SG',
            'head_name' => 'M. Moussa Ali',
            'censor_name' => 'Mme Aïcha Hamani',
        ]);
        $profile->storeImage('logo', UploadedFile::fake()->image('logo.png', 300, 300));
    }

    private function absence(string $key, string $date, string $status, string $start = '08:00:00', string $end = '10:00:00'): void
    {
        $session = AttendanceSession::query()->create([
            'classe_id' => $this->classe->id,
            'subject_id' => $this->maths->id,
            'session_date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'completed',
            'created_by' => $this->admin->id,
        ]);

        AttendanceRecord::query()->create([
            'attendance_session_id' => $session->id,
            'student_id' => $this->students[$key]->user_id,
            'status' => $status,
            'recorded_by' => $this->admin->id,
        ]);
    }

    /**
     * Classe réaliste : 12 matières notées avec appréciations longues.
     */
    private function seedFullTimetable(string $comment = 'Travail sérieux et régulier, doit maintenir ses efforts en classe.'): void
    {
        $this->seedReferenceGrades();

        $names = ['Physique-Chimie', 'Histoire-Géographie', 'Anglais', 'Philosophie', 'EPS', 'Arabe', 'Éducation civique', 'Informatique', 'Allemand'];
        foreach ($names as $index => $name) {
            $subject = Subject::factory()->create(['name' => $name, 'code' => 'S'.$index]);
            SubjectClassCoefficient::factory()->create([
                'subject_id' => $subject->id, 'level_id' => $this->classe->level_id, 'series_id' => null, 'coefficient' => ($index % 3) + 1,
            ]);
            $this->assign($this->mathTeacher, $subject);
            $this->evaluation($subject, ['A' => 9 + $index, 'B' => 14 - $index / 2, 'C' => 11, 'D' => 7 + $index]);

            foreach (array_keys($this->students) as $key) {
                ReportCardComment::query()->create([
                    'student_id' => $this->students[$key]->id,
                    'semester_id' => $this->s1->id,
                    'subject_id' => $subject->id,
                    'comment' => $comment,
                    'author_id' => $this->mathTeacher->id,
                ]);
            }
        }

        foreach (array_keys($this->students) as $key) {
            ReportCardComment::query()->create([
                'student_id' => $this->students[$key]->id,
                'semester_id' => $this->s1->id,
                'subject_id' => null,
                'comment' => 'Bon semestre dans l\'ensemble. Le conseil encourage l\'élève à poursuivre ses efforts.',
                'author_id' => $this->headTeacher->id,
            ]);
        }
    }

    private function pageCount(string $pdf): int
    {
        return preg_match_all('#/Type\s*/Page[^s]#', $pdf);
    }

    #[Test]
    public function report_shows_school_profile_absences_and_signatories(): void
    {
        $this->configureSchool();
        $this->seedReferenceGrades();
        $this->absence('A', '2025-11-03', 'absent');
        $this->absence('A', '2025-11-04', 'excused', '08:00:00', '09:00:00');
        $this->absence('A', '2025-11-05', 'late');
        $this->absence('A', '2026-04-01', 'absent');

        $service = app(LyceeReportService::class);
        $report = $service->studentReport($this->students['A'], $this->classe->load(['headTeacher', 'academicYear']), $this->s1, true);

        $this->assertSame(120, $report['absences']['unjustified_minutes']);
        $this->assertSame(60, $report['absences']['justified_minutes']);
        $this->assertSame(1, $report['absences']['late_count']);
        $this->assertNull($report['annual']);

        $html = view('notesevaluations::lycee.report-card', [
            'reports' => [$report],
            'classe' => $this->classe,
            'semester' => $this->s1,
            'academicYear' => $this->year,
            'establishment' => $service->establishment(),
            'isProvisional' => false,
            'generatedAt' => now(),
        ])->render();

        foreach (['RÉPUBLIQUE DU NIGER', 'Lycée Issa Korombé', 'Inspection de l&#039;enseignement secondaire Niamey II', 'BP 1234', 'BULLETIN DE NOTES — 1ER SEMESTRE',
            'Fait à Niamey', 'Le Proviseur', 'M. Moussa Ali', 'Fati Principale', '3 h', '2 h', '1 h', 'data:image/png;base64,'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
        $this->assertStringNotContainsString('Décision du conseil', $html);
    }

    #[Test]
    public function last_semester_report_shows_annual_rank_and_council_decision(): void
    {
        $this->seedReferenceGrades();
        $this->evaluation($this->maths, ['A' => 15, 'B' => 9, 'C' => 12, 'D' => 10], ['evaluation_date' => '2026-04-10'], $this->s2);
        ClassEnrollment::query()->where('student_id', $this->students['A']->id)->update(['final_decision' => 'Passage']);

        $service = app(LyceeReportService::class);
        $report = $service->studentReport($this->students['A'], $this->classe->load(['headTeacher', 'academicYear']), $this->s2, true);

        $this->assertNotNull($report['annual']);
        $this->assertSame('Admis(e) en classe supérieure', $report['council_decision']);

        $html = view('notesevaluations::lycee.report-card', [
            'reports' => [$report],
            'classe' => $this->classe,
            'semester' => $this->s2,
            'academicYear' => $this->year,
            'establishment' => $service->establishment(),
            'isProvisional' => false,
            'generatedAt' => now(),
        ])->render();

        $this->assertStringContainsString('Rang annuel', $html);
        $this->assertStringContainsString('Admis(e) en classe supérieure', $html);
        $this->assertStringContainsString('2E SEMESTRE', $html);
    }

    #[Test]
    public function class_pdf_fits_one_a4_page_per_student(): void
    {
        $this->configureSchool();
        $this->seedFullTimetable();
        $this->absence('A', '2025-11-03', 'absent');

        $response = $this->downloadAs($this->admin, "/api/admin/lycee-grades/report-cards/classes/{$this->classe->id}/pdf?semester_id={$this->s1->id}")
            ->assertOk();
        $pdf = (string) $response->getContent();

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame(count($this->students), $this->pageCount($pdf));

        if ($dumpPath = getenv('REPORT_CARD_PDF_DUMP')) {
            file_put_contents($dumpPath, $pdf);
        }
    }

    #[Test]
    public function long_appreciations_on_the_last_semester_still_fit_one_page(): void
    {
        $this->configureSchool();
        $this->seedFullTimetable(str_repeat('Élève sérieux mais irrégulier, doit davantage participer. ', 5));
        $this->evaluation($this->maths, ['A' => 15, 'B' => 9, 'C' => 12, 'D' => 10], ['evaluation_date' => '2026-04-10'], $this->s2);
        foreach (ReportCardComment::query()->where('semester_id', $this->s1->id)->get() as $comment) {
            $comment->replicate()->fill(['semester_id' => $this->s2->id])->save();
        }
        ClassEnrollment::query()->update(['final_decision' => 'Redoublement']);

        $response = $this->downloadAs($this->admin, "/api/admin/lycee-grades/report-cards/students/{$this->students['A']->id}/pdf?semester_id={$this->s2->id}")
            ->assertOk();
        $pdf = (string) $response->getContent();

        $this->assertSame(1, $this->pageCount($pdf));

        if ($dumpPath = getenv('REPORT_CARD_PDF_DUMP_DENSE')) {
            file_put_contents($dumpPath, $pdf);
        }
    }
}
