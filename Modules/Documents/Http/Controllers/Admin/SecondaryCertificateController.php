<?php

namespace Modules\Documents\Http\Controllers\Admin;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Documents\Entities\Document;
use Symfony\Component\HttpFoundation\Response;

/**
 * Secondary-school certificates (certificat de scolarité / d'inscription).
 * Self-contained: no pre-seeded DocumentTemplate, PDF rendered inline.
 */
class SecondaryCertificateController extends Controller
{
    private const TYPES = [
        'certificate_schooling' => 'Certificat de scolarité',
        'certificate_enrollment' => "Attestation d'inscription",
    ];

    public function index(Request $request): JsonResponse
    {
        $query = Document::on('tenant')
            ->with(['student.classe'])
            ->whereIn('document_type', array_keys(self::TYPES))
            ->orderByDesc('issue_date')
            ->orderByDesc('id');

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->input('student_id'));
        }

        $docs = $query->get()->map(fn (Document $d) => [
            'id' => $d->id,
            'document_number' => $d->document_number,
            'document_type' => $d->document_type,
            'type_label' => self::TYPES[$d->document_type] ?? $d->document_type,
            'issue_date' => optional($d->issue_date)->format('Y-m-d'),
            'verification_code' => $d->verification_code,
            'status' => $d->status,
            'student' => $d->student ? [
                'id' => $d->student->id,
                'matricule' => $d->student->matricule,
                'name' => trim($d->student->lastname.' '.$d->student->firstname),
                'classe' => $d->student->classe?->name,
            ] : null,
        ]);

        return response()->json(['data' => $docs]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|integer|exists:tenant.students,id',
            'document_type' => ['required', Rule::in(array_keys(self::TYPES))],
        ]);

        $document = Document::create([
            'student_id' => $validated['student_id'],
            'document_type' => $validated['document_type'],
            'document_number' => 'TMP',
            'issue_date' => now(),
            'pdf_path' => '',
            'verification_code' => strtoupper(Str::random(10)),
            'status' => 'issued',
            'issued_by' => auth('tenant')->id(),
        ]);

        $prefix = $validated['document_type'] === 'certificate_schooling' ? 'CS' : 'AI';
        $document->update([
            'document_number' => sprintf('%s-%s-%05d', $prefix, now()->format('Y'), $document->id),
        ]);

        return response()->json(['data' => $document->fresh()->load('student.classe')], 201);
    }

    public function download(int $id): Response
    {
        $document = Document::on('tenant')->with('student.classe')->findOrFail($id);
        $pdf = Pdf::loadHTML($this->renderHtml($document));

        return $pdf->download(($document->document_number ?: 'certificat').'.pdf');
    }

    private function renderHtml(Document $document): string
    {
        $student = $document->student;
        $typeLabel = self::TYPES[$document->document_type] ?? 'Certificat';
        $fullName = $student ? e(trim($student->lastname.' '.$student->firstname)) : '';
        $matricule = e($student->matricule ?? '');
        $classe = e($student->classe->name ?? '—');
        $birth = $student && $student->birthdate ? \Illuminate\Support\Carbon::parse($student->birthdate)->format('d/m/Y') : '—';
        $birthplace = e($student->birthplace ?? '');
        $number = e($document->document_number);
        $code = e($document->verification_code);
        $date = optional($document->issue_date)->format('d/m/Y') ?? now()->format('d/m/Y');
        $header = \Modules\Settings\Entities\TenantSetting::schoolHeaderHtml();

        return <<<HTML
<!DOCTYPE html><html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;color:#1a1a1a;padding:40px;font-size:14px;}
h1{text-align:center;font-size:20px;text-decoration:underline;margin:30px 0;}
.body p{line-height:1.9;text-align:justify;}
.row{margin:6px 0;}
.label{font-weight:bold;}
.footer{margin-top:60px;display:flex;justify-content:space-between;}
.meta{margin-top:50px;font-size:11px;color:#666;border-top:1px solid #ccc;padding-top:8px;}
</style></head><body>
{$header}
<h1>{$typeLabel}</h1>
<div class="body">
  <p>Le Chef d'établissement soussigné certifie que l'élève dont les informations suivent est régulièrement inscrit(e) dans l'établissement pour l'année scolaire en cours :</p>
  <div class="row"><span class="label">Nom et prénom :</span> {$fullName}</div>
  <div class="row"><span class="label">Matricule :</span> {$matricule}</div>
  <div class="row"><span class="label">Né(e) le :</span> {$birth} à {$birthplace}</div>
  <div class="row"><span class="label">Classe :</span> {$classe}</div>
  <p>En foi de quoi, le présent {$typeLabel} lui est délivré pour servir et valoir ce que de droit.</p>
</div>
<div class="footer">
  <div>N° {$number}</div>
  <div>Fait le {$date}<br>Le Chef d'établissement</div>
</div>
<div class="meta">Code de vérification : {$code}</div>
</body></html>
HTML;
    }
}
