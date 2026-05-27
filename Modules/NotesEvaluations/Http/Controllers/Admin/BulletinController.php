<?php

namespace Modules\NotesEvaluations\Http\Controllers\Admin;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\NotesEvaluations\Services\BulletinService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Secondary-school report cards (bulletins trimestriels).
 * Self-contained: PDF rendered inline, no DocumentTemplate dependency.
 */
class BulletinController extends Controller
{
    public function __construct(private readonly BulletinService $bulletins) {}

    /**
     * Class summary: per-student averages, ranks and class statistics.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_id' => 'required|integer|exists:tenant.classes,id',
            'semester_id' => 'nullable|integer|exists:tenant.semesters,id',
        ]);

        $bulletin = $this->bulletins->buildForClass(
            (int) $validated['class_id'],
            isset($validated['semester_id']) ? (int) $validated['semester_id'] : null,
        );

        return response()->json([
            'data' => [
                'class' => [
                    'id' => $bulletin['class']->id,
                    'name' => $bulletin['class']->name,
                ],
                'semester_id' => $bulletin['semester_id'],
                'subjects' => $bulletin['subjects'],
                'students' => $bulletin['students'],
                'stats' => $bulletin['stats'],
            ],
        ]);
    }

    /**
     * Render a single student's report card as a PDF.
     */
    public function studentPdf(Request $request, int $studentId): Response
    {
        $validated = $request->validate([
            'class_id' => 'required|integer|exists:tenant.classes,id',
            'semester_id' => 'nullable|integer|exists:tenant.semesters,id',
        ]);

        $bulletin = $this->bulletins->buildForClass(
            (int) $validated['class_id'],
            isset($validated['semester_id']) ? (int) $validated['semester_id'] : null,
        );

        $student = collect($bulletin['students'])->firstWhere('student_id', $studentId);
        abort_if($student === null, 404, 'Élève introuvable dans cette classe.');

        $pdf = Pdf::loadHTML($this->renderHtml($bulletin, $student));
        $filename = sprintf('bulletin-%s.pdf', $student['matricule'] ?: $studentId);

        return $pdf->download($filename);
    }

    /**
     * @param  array<string, mixed>  $bulletin
     * @param  array<string, mixed>  $student
     */
    private function renderHtml(array $bulletin, array $student): string
    {
        $subjectsById = collect($bulletin['subjects'])->keyBy('subject_id');
        $className = e($bulletin['class']->name);
        $period = $this->periodLabel($bulletin);
        $fullName = e(trim($student['lastname'].' '.$student['firstname']));
        $matricule = e($student['matricule'] ?? '');
        $rank = $student['rank'] ? $student['rank'].' / '.$bulletin['stats']['students'] : '—';
        $general = $student['general_average'] !== null ? number_format((float) $student['general_average'], 2, ',', ' ') : '—';
        $classAvg = $bulletin['stats']['class_average'] !== null ? number_format((float) $bulletin['stats']['class_average'], 2, ',', ' ') : '—';
        $mention = $this->mention($student['general_average']);
        $date = now()->format('d/m/Y');

        $rows = '';
        foreach ($student['subjects'] as $row) {
            $subject = $subjectsById->get($row['subject_id']);
            $name = e($subject['name'] ?? 'Matière');
            $coef = number_format((float) $row['coefficient'], 1, ',', ' ');
            $avg = $row['average'] !== null ? number_format((float) $row['average'], 2, ',', ' ') : '—';
            $weighted = $row['weighted'] !== null ? number_format((float) $row['weighted'], 2, ',', ' ') : '—';
            $appr = $this->appreciation($row['average']);
            $rows .= "<tr><td class=\"subj\">{$name}</td><td class=\"c\">{$coef}</td><td class=\"c\">{$avg}</td><td class=\"c\">{$weighted}</td><td>{$appr}</td></tr>";
        }

        return <<<HTML
<!DOCTYPE html><html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;color:#1a1a1a;padding:32px;font-size:12px;}
.header{text-align:center;margin-bottom:18px;}
.header h2{margin:2px;font-size:14px;}
.header .sub{font-size:11px;color:#555;}
h1{text-align:center;font-size:17px;margin:18px 0 6px;}
.meta{display:flex;justify-content:space-between;margin:10px 0 14px;font-size:12px;}
.meta .box{border:1px solid #ccc;padding:6px 10px;border-radius:4px;}
table{width:100%;border-collapse:collapse;margin-top:8px;}
th,td{border:1px solid #bbb;padding:5px 7px;}
th{background:#f0f3f7;text-align:left;font-size:11px;}
td.c{text-align:center;}
td.subj{font-weight:bold;}
.totals{margin-top:16px;display:flex;justify-content:space-between;font-size:13px;}
.totals .big{font-size:15px;font-weight:bold;}
.footer{margin-top:40px;display:flex;justify-content:space-between;}
.mention{font-weight:bold;}
</style></head><body>
<div class="header">
  <h2>RÉPUBLIQUE DU NIGER</h2>
  <div class="sub">Ministère de l'Éducation Nationale</div>
  <div class="sub">Établissement scolaire</div>
</div>
<h1>Bulletin de notes — {$period}</h1>
<div class="meta">
  <div class="box"><strong>Élève :</strong> {$fullName}<br><strong>Matricule :</strong> {$matricule}</div>
  <div class="box"><strong>Classe :</strong> {$className}<br><strong>Rang :</strong> {$rank}</div>
</div>
<table>
  <thead><tr><th>Matière</th><th>Coef.</th><th>Moyenne /20</th><th>Moy. × Coef.</th><th>Appréciation</th></tr></thead>
  <tbody>{$rows}</tbody>
</table>
<div class="totals">
  <div>Moyenne de la classe : <strong>{$classAvg}/20</strong></div>
  <div class="big">Moyenne générale : {$general}/20</div>
</div>
<div class="totals">
  <div>Mention : <span class="mention">{$mention}</span></div>
</div>
<div class="footer">
  <div>Fait le {$date}</div>
  <div>Le Chef d'établissement</div>
</div>
</body></html>
HTML;
    }

    /**
     * @param  array<string, mixed>  $bulletin
     */
    private function periodLabel(array $bulletin): string
    {
        if ($bulletin['semester_id'] === null) {
            return 'Année';
        }

        $semester = \Modules\StructureAcademique\Entities\Semester::on('tenant')->find($bulletin['semester_id']);

        return $semester ? e($semester->name) : 'Période';
    }

    private function mention(?float $average): string
    {
        if ($average === null) {
            return '—';
        }

        return match (true) {
            $average >= 16 => 'Très Bien',
            $average >= 14 => 'Bien',
            $average >= 12 => 'Assez Bien',
            $average >= 10 => 'Passable',
            default => 'Insuffisant',
        };
    }

    private function appreciation(?float $average): string
    {
        if ($average === null) {
            return '—';
        }

        return match (true) {
            $average >= 16 => 'Excellent',
            $average >= 14 => 'Très bon travail',
            $average >= 12 => 'Bon travail',
            $average >= 10 => 'Travail satisfaisant',
            $average >= 8 => 'Doit progresser',
            default => 'Insuffisant',
        };
    }
}
