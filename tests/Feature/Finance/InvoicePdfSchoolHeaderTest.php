<?php

namespace Tests\Feature\Finance;

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Finance\Entities\Invoice;
use Modules\Settings\Services\SchoolProfileService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Facture PDF : en-tête et devise issus du profil de l'établissement.
 */
class InvoicePdfSchoolHeaderTest extends TestCase
{
    use InteractsWithTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function invoice(): Invoice
    {
        $invoice = new Invoice;
        $invoice->forceFill([
            'invoice_number' => 'FAC-0001',
            'invoice_date' => '2026-10-01',
            'due_date' => '2026-10-31',
            'status' => 'pending',
            'total_amount' => 75000,
        ]);
        $invoice->setRelation('items', collect());
        $invoice->setRelation('payments', collect());
        $invoice->setRelation('student', null);
        $invoice->setRelation('academicYear', null);

        return $invoice;
    }

    #[Test]
    public function invoice_pdf_shows_school_header_and_currency(): void
    {
        app(SchoolProfileService::class)->update(['name' => 'Complexe scolaire Al Nour', 'city' => 'Tahoua', 'currency' => 'FCFA']);

        $data = ['invoice' => $this->invoice(), 'totalPaid' => 0.0, 'remaining' => 75000.0, 'generatedAt' => now()];
        $html = view('finance::invoices.pdf', $data)->render();

        $this->assertStringContainsString('Complexe scolaire Al Nour', $html);
        $this->assertStringContainsString('RÉPUBLIQUE DU NIGER', $html);
        $this->assertStringContainsString('75 000 FCFA', $html);
        $this->assertStringNotContainsString('XOF', $html);

        $this->assertStringStartsWith('%PDF', Pdf::loadView('finance::invoices.pdf', $data)->output());
    }
}
