<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture {{ $invoice->invoice_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; margin: 0; padding: 24px 28px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; border-bottom: 2px solid #1976d2; padding-bottom: 12px; }
        .header h1 { color: #1976d2; margin: 0 0 4px; font-size: 22px; }
        .header .meta { text-align: right; font-size: 11px; color: #555; }
        .header .meta strong { color: #1976d2; }
        .blocks { width: 100%; margin-bottom: 18px; }
        .blocks td { vertical-align: top; width: 50%; padding: 8px 12px; background: #f5f7fa; border: 1px solid #e1e5eb; }
        .blocks h3 { margin: 0 0 6px; font-size: 12px; color: #1976d2; text-transform: uppercase; letter-spacing: 0.5px; }
        .blocks p { margin: 2px 0; font-size: 11px; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.items th { background: #1976d2; color: #fff; text-align: left; padding: 8px 10px; font-size: 11px; }
        table.items td { padding: 8px 10px; border-bottom: 1px solid #e1e5eb; font-size: 11px; }
        table.items td.right { text-align: right; }
        .totals { width: 50%; margin-left: 50%; }
        .totals td { padding: 6px 10px; font-size: 12px; }
        .totals .label { color: #555; }
        .totals .value { text-align: right; font-weight: bold; }
        .totals .grand { background: #1976d2; color: #fff; }
        .totals .remaining { background: #fff3cd; color: #b58900; }
        .totals .paid { color: #2e7d32; }
        .payments { margin-top: 20px; }
        .payments h3 { color: #1976d2; margin: 0 0 8px; font-size: 13px; }
        .payments table { width: 100%; border-collapse: collapse; font-size: 11px; }
        .payments th { background: #f5f7fa; padding: 6px 8px; border: 1px solid #e1e5eb; text-align: left; }
        .payments td { padding: 6px 8px; border: 1px solid #e1e5eb; }
        .footer { margin-top: 30px; font-size: 10px; color: #999; text-align: center; border-top: 1px solid #e1e5eb; padding-top: 10px; }
        .status { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .status.paid { background: #c8e6c9; color: #2e7d32; }
        .status.pending { background: #bbdefb; color: #1565c0; }
        .status.overdue { background: #ffcdd2; color: #c62828; }
        .status.partial { background: #fff9c4; color: #b58900; }
        .status.cancelled { background: #eeeeee; color: #555; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>FACTURE</h1>
            <div><strong>N° :</strong> {{ $invoice->invoice_number }}</div>
            <div><strong>Date :</strong> {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y') }}</div>
            <div><strong>Échéance :</strong> {{ \Carbon\Carbon::parse($invoice->due_date)->format('d/m/Y') }}</div>
        </div>
        <div class="meta">
            <span class="status {{ $invoice->status }}">{{ strtoupper($invoice->status) }}</span>
            <br><br>
            <strong>Année académique</strong><br>
            {{ $invoice->academicYear?->name ?? '—' }}
        </div>
    </div>

    <table class="blocks">
        <tr>
            <td>
                <h3>Émetteur</h3>
                <p><strong>{{ config('app.name', 'Établissement') }}</strong></p>
                <p>Système de gestion scolaire</p>
            </td>
            <td>
                <h3>Étudiant</h3>
                <p><strong>{{ $invoice->student?->firstname }} {{ $invoice->student?->lastname }}</strong></p>
                <p>Matricule : {{ $invoice->student?->matricule ?? '—' }}</p>
                @if($invoice->student?->email)
                    <p>{{ $invoice->student->email }}</p>
                @endif
                @if($invoice->student?->mobile)
                    <p>{{ $invoice->student->mobile }}</p>
                @endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Description</th>
                <th class="right" style="width:80px">Qté</th>
                <th class="right" style="width:130px">Prix unitaire</th>
                <th class="right" style="width:130px">Montant</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ $item->quantity }}</td>
                    <td class="right">{{ number_format((float) $item->unit_price, 0, ',', ' ') }} XOF</td>
                    <td class="right">{{ number_format((float) $item->amount, 0, ',', ' ') }} XOF</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align:center;color:#999">Aucune ligne</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Montant total</td>
            <td class="value">{{ number_format((float) $invoice->total_amount, 0, ',', ' ') }} XOF</td>
        </tr>
        <tr class="paid">
            <td class="label">Total payé</td>
            <td class="value">{{ number_format($totalPaid, 0, ',', ' ') }} XOF</td>
        </tr>
        <tr class="remaining">
            <td class="label">Restant dû</td>
            <td class="value">{{ number_format($remaining, 0, ',', ' ') }} XOF</td>
        </tr>
    </table>

    @if($invoice->payments->isNotEmpty())
        <div class="payments">
            <h3>Historique des paiements</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Méthode</th>
                        <th>Référence</th>
                        <th>Montant</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->payments as $payment)
                        <tr>
                            <td>{{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y') : '—' }}</td>
                            <td>{{ $payment->payment_method ?? '—' }}</td>
                            <td>{{ $payment->reference_number ?? '—' }}</td>
                            <td>{{ number_format((float) $payment->amount, 0, ',', ' ') }} XOF</td>
                            <td>{{ $payment->status ?? 'completed' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($invoice->notes)
        <div style="margin-top:18px;padding:10px 12px;background:#f5f7fa;border-left:3px solid #1976d2;font-size:11px">
            <strong>Notes :</strong> {{ $invoice->notes }}
        </div>
    @endif

    <div class="footer">
        Facture générée le {{ $generatedAt->format('d/m/Y à H:i') }} — {{ config('app.name') }}
    </div>
</body>
</html>
