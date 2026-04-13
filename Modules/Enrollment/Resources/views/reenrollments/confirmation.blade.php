<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de Reinscription - {{ $student->matricule }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #1a365d;
            padding-bottom: 15px;
        }
        .header h1 {
            font-size: 18px;
            color: #1a365d;
            margin-bottom: 5px;
        }
        .header h2 {
            font-size: 14px;
            color: #4a5568;
            font-weight: normal;
        }
        .confirmation-number {
            text-align: center;
            background-color: #e2e8f0;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .confirmation-number strong {
            font-size: 16px;
            color: #1a365d;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #1a365d;
            background-color: #e2e8f0;
            padding: 8px 12px;
            margin-bottom: 10px;
            border-left: 4px solid #1a365d;
        }
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            width: 35%;
            padding: 5px 10px;
            font-weight: bold;
            background-color: #f7fafc;
        }
        .info-value {
            display: table-cell;
            padding: 5px 10px;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
        }
        .status-validated {
            background-color: #c6f6d5;
            color: #276749;
        }
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        .progression-box {
            background-color: #f0fff4;
            border: 1px solid #9ae6b4;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
        }
        .progression-box h4 {
            color: #276749;
            margin-bottom: 10px;
        }
        .arrow {
            font-size: 16px;
            color: #276749;
            margin: 0 10px;
        }
        .fees-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .fees-table th,
        .fees-table td {
            border: 1px solid #cbd5e0;
            padding: 8px;
            text-align: left;
        }
        .fees-table th {
            background-color: #1a365d;
            color: white;
            font-weight: bold;
        }
        .fees-table .total-row {
            font-weight: bold;
            background-color: #e2e8f0;
        }
        .signature-section {
            margin-top: 40px;
            display: table;
            width: 100%;
        }
        .signature-box {
            display: table-cell;
            width: 50%;
            padding: 10px;
        }
        .signature-box h4 {
            font-size: 11px;
            margin-bottom: 5px;
            color: #4a5568;
        }
        .signature-line {
            border-bottom: 1px solid #333;
            height: 50px;
            margin-top: 30px;
        }
        .footer {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #cbd5e0;
            font-size: 9px;
            color: #718096;
            text-align: center;
        }
        .notice {
            background-color: #ebf8ff;
            border: 1px solid #90cdf4;
            padding: 10px;
            border-radius: 5px;
            margin-top: 15px;
            font-size: 10px;
        }
        .notice-title {
            font-weight: bold;
            color: #2b6cb0;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>CONFIRMATION DE REINSCRIPTION</h1>
        <h2>Annee Academique {{ $toAcademicYear->label ?? $toAcademicYear->year }}</h2>
    </div>

    <div class="confirmation-number">
        <strong>N&deg; {{ $reenrollment->reenrollment_number }}</strong>
    </div>

    <div class="section">
        <div class="section-title">INFORMATIONS ETUDIANT</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Matricule</div>
                <div class="info-value">{{ $student->matricule }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Nom complet</div>
                <div class="info-value">{{ $student->lastname }} {{ $student->firstname }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Date de naissance</div>
                <div class="info-value">{{ $student->birth_date?->format('d/m/Y') ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Email</div>
                <div class="info-value">{{ $student->email }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">PROGRESSION ACADEMIQUE</div>
        <div class="progression-box">
            <h4>Passage de niveau</h4>
            <p style="font-size: 14px; text-align: center;">
                {{ $reenrollment->from_level }}
                <span class="arrow">&rarr;</span>
                {{ $reenrollment->to_level }}
            </p>
        </div>
        <div class="info-grid" style="margin-top: 15px;">
            <div class="info-row">
                <div class="info-label">Programme</div>
                <div class="info-value">{{ $program->name }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Annee precedente</div>
                <div class="info-value">{{ $fromAcademicYear->label ?? $fromAcademicYear->year }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Nouvelle annee</div>
                <div class="info-value">{{ $toAcademicYear->label ?? $toAcademicYear->year }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Redoublement</div>
                <div class="info-value">{{ $reenrollment->is_redoing ? 'Oui' : 'Non' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Statut</div>
                <div class="info-value">
                    <span class="status-badge status-validated">VALIDE</span>
                </div>
            </div>
        </div>
    </div>

    @if($fees && count($fees) > 0)
    <div class="section">
        <div class="section-title">FRAIS DE SCOLARITE</div>
        <table class="fees-table">
            <thead>
                <tr>
                    <th style="width: 60%;">Description</th>
                    <th style="width: 20%;">Montant</th>
                    <th style="width: 20%;">Statut</th>
                </tr>
            </thead>
            <tbody>
                @php $totalFees = 0; @endphp
                @foreach($fees as $fee)
                    @php $totalFees += $fee['amount'] ?? 0; @endphp
                    <tr>
                        <td>{{ $fee['description'] ?? 'Frais de scolarite' }}</td>
                        <td style="text-align: right;">{{ number_format($fee['amount'] ?? 0, 0, ',', ' ') }} FCFA</td>
                        <td>{{ $fee['status'] ?? 'En attente' }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td>TOTAL</td>
                    <td style="text-align: right;">{{ number_format($totalFees, 0, ',', ' ') }} FCFA</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>
    @endif

    <div class="section">
        <div class="section-title">VALIDATION</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Date de soumission</div>
                <div class="info-value">{{ $reenrollment->submitted_at?->format('d/m/Y a H:i') ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Date de validation</div>
                <div class="info-value">{{ $reenrollment->validated_at?->format('d/m/Y a H:i') ?? 'N/A' }}</div>
            </div>
            @if($validator)
            <div class="info-row">
                <div class="info-label">Valide par</div>
                <div class="info-value">{{ $validator->name }}</div>
            </div>
            @endif
        </div>
    </div>

    <div class="notice">
        <div class="notice-title">IMPORTANT</div>
        <p>Cette confirmation de reinscription est un document officiel. Conservez-la precieusement.</p>
        <p>Elle atteste de votre inscription pour l'annee academique {{ $toAcademicYear->label ?? $toAcademicYear->year }}.</p>
    </div>

    <div class="signature-section">
        <div class="signature-box">
            <h4>L'Etudiant</h4>
            <p>{{ $student->lastname }} {{ $student->firstname }}</p>
            <div class="signature-line"></div>
            <p style="margin-top: 5px; font-size: 9px;">Date et Signature</p>
        </div>
        <div class="signature-box">
            <h4>Le Service Scolarite</h4>
            @if($validator)
                <p>{{ $validator->name }}</p>
            @endif
            <div class="signature-line"></div>
            <p style="margin-top: 5px; font-size: 9px;">Date, Signature et Cachet</p>
        </div>
    </div>

    <div class="footer">
        <p>Document genere le {{ $generatedAt->format('d/m/Y a H:i') }}</p>
        <p>Numero de confirmation: {{ $reenrollment->reenrollment_number }}</p>
    </div>
</body>
</html>
