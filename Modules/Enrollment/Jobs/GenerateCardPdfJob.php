<?php

namespace Modules\Enrollment\Jobs;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Modules\Enrollment\Entities\StudentCard;
use Modules\Enrollment\Services\QRCodeService;

class GenerateCardPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public StudentCard $card
    ) {}

    public function handle(QRCodeService $qrService): void
    {
        $student = $this->card->student;
        $qrData = $this->card->getQrDataArray();

        // Generate QR code
        $qrCode = $qrService->generate($qrData, $this->card->qr_signature, 150);

        // Generate barcode
        $barcode = $qrService->generateBarcode($student->matricule);

        // Get institution name from tenant
        $tenant = tenant();
        $institutionName = $tenant?->company_name ?? config('app.name', 'Institution');

        $pdf = Pdf::loadView('enrollment::cards.template', [
            'card' => $this->card,
            'student' => $student,
            'qrCode' => $qrCode,
            'barcode' => $barcode,
            'academicYear' => $this->card->academicYear,
            'institutionName' => $institutionName,
        ]);

        // Credit card size: 85.6 x 53.98 mm = 242.64 x 153 points
        $pdf->setPaper([0, 0, 243, 153], 'landscape');

        $fileName = "card_{$student->matricule}_{$this->card->academic_year_id}.pdf";
        $path = "student_cards/{$this->card->academic_year_id}/{$fileName}";

        Storage::disk('tenant')->put($path, $pdf->output());

        $this->card->update(['pdf_path' => $path]);
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error('Failed to generate student card PDF', [
            'card_id' => $this->card->id,
            'student_id' => $this->card->student_id,
            'error' => $exception->getMessage(),
        ]);
    }
}
