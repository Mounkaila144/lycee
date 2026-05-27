<?php

namespace Modules\Documents\Http\Controllers\Admin;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\Documents\Entities\Document;
use Symfony\Component\HttpFoundation\Response;

/**
 * Secondary-school student ID cards (carte scolaire).
 * Self-contained: stored as a Document (no DocumentTemplate), PDF rendered
 * inline — mirrors SecondaryCertificateController so generation works out of
 * the box even though the legacy student_cards table/entity diverged.
 */
class SecondaryCardController extends Controller
{
    private const DOCUMENT_TYPE = 'student_card';

    public function index(Request $request): JsonResponse
    {
        $query = Document::on('tenant')
            ->with(['student.classe'])
            ->where('document_type', self::DOCUMENT_TYPE)
            ->orderByDesc('issue_date')
            ->orderByDesc('id');

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->input('student_id'));
        }

        $cards = $query->get()->map(fn (Document $d) => [
            'id' => $d->id,
            'card_number' => $d->document_number,
            'issue_date' => optional($d->issue_date)->format('Y-m-d'),
            'expiry_date' => optional($d->issue_date)->copy()->addYear()->format('Y-m-d'),
            'verification_code' => $d->verification_code,
            'status' => $d->status,
            'student' => $d->student ? [
                'id' => $d->student->id,
                'matricule' => $d->student->matricule,
                'name' => trim($d->student->lastname.' '.$d->student->firstname),
                'classe' => $d->student->classe?->name,
            ] : null,
        ]);

        return response()->json(['data' => $cards]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|integer|exists:tenant.students,id',
        ]);

        $document = Document::on('tenant')->create([
            'student_id' => $validated['student_id'],
            'document_type' => self::DOCUMENT_TYPE,
            'document_number' => 'TMP',
            'issue_date' => now(),
            'pdf_path' => '',
            'verification_code' => strtoupper(Str::random(12)),
            'status' => 'issued',
            'issued_by' => auth('tenant')->id(),
        ]);

        $document->update([
            'document_number' => sprintf('CS-%s-%05d', now()->format('Y'), $document->id),
        ]);

        return response()->json(['data' => $document->fresh()->load('student.classe')], 201);
    }

    public function download(int $id): Response
    {
        $document = Document::on('tenant')
            ->with('student.classe')
            ->where('document_type', self::DOCUMENT_TYPE)
            ->findOrFail($id);

        $pdf = Pdf::loadHTML($this->renderHtml($document))->setPaper('a6', 'landscape');

        return $pdf->download(($document->document_number ?: 'carte').'.pdf');
    }

    private function renderHtml(Document $document): string
    {
        $student = $document->student;
        $fullName = $student ? e(trim($student->lastname.' '.$student->firstname)) : '';
        $matricule = e($student->matricule ?? '');
        $classe = e($student->classe->name ?? '—');
        $birth = $student && $student->birthdate
            ? \Illuminate\Support\Carbon::parse($student->birthdate)->format('d/m/Y')
            : '—';
        $sex = e($student->sex ?? '—');
        $number = e($document->document_number);
        $code = e($document->verification_code ?? '');
        $issue = optional($document->issue_date)->format('d/m/Y') ?? now()->format('d/m/Y');
        $expiry = optional($document->issue_date)->copy()->addYear()->format('d/m/Y') ?? '—';

        return <<<HTML
<!DOCTYPE html><html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;color:#10243f;margin:0;padding:0;}
.card{border:2px solid #1565c0;border-radius:8px;padding:12px 16px;}
.head{text-align:center;border-bottom:2px solid #1565c0;padding-bottom:6px;margin-bottom:8px;}
.head .school{font-size:13px;font-weight:bold;color:#1565c0;}
.head .sub{font-size:9px;color:#555;}
.head .title{font-size:11px;font-weight:bold;letter-spacing:1px;margin-top:2px;}
.body{display:flex;}
.photo{width:70px;height:84px;border:1px solid #aaa;border-radius:4px;text-align:center;font-size:8px;color:#999;line-height:84px;margin-right:12px;}
.info{font-size:10px;line-height:1.6;}
.info .lbl{color:#555;}
.info .name{font-size:12px;font-weight:bold;}
.foot{margin-top:8px;display:flex;justify-content:space-between;font-size:8px;color:#444;border-top:1px solid #ccc;padding-top:4px;}
</style></head><body>
<div class="card">
  <div class="head">
    <div class="school">RÉPUBLIQUE DU NIGER</div>
    <div class="sub">Ministère de l'Éducation Nationale · Établissement scolaire</div>
    <div class="title">CARTE SCOLAIRE</div>
  </div>
  <div class="body">
    <div class="photo">PHOTO</div>
    <div class="info">
      <div class="name">{$fullName}</div>
      <div><span class="lbl">Matricule :</span> {$matricule}</div>
      <div><span class="lbl">Classe :</span> {$classe}</div>
      <div><span class="lbl">Né(e) le :</span> {$birth} · <span class="lbl">Sexe :</span> {$sex}</div>
      <div><span class="lbl">N° carte :</span> {$number}</div>
    </div>
  </div>
  <div class="foot">
    <div>Valable du {$issue} au {$expiry}</div>
    <div>Code : {$code}</div>
  </div>
</div>
</body></html>
HTML;
    }
}
