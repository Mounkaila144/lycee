<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrat Pédagogique - {{ $student->matricule }}</title>
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
        table.modules {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table.modules th,
        table.modules td {
            border: 1px solid #cbd5e0;
            padding: 8px;
            text-align: left;
        }
        table.modules th {
            background-color: #1a365d;
            color: white;
            font-weight: bold;
        }
        table.modules tr:nth-child(even) {
            background-color: #f7fafc;
        }
        .total-row {
            font-weight: bold;
            background-color: #e2e8f0 !important;
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
    </style>
</head>
<body>
    <div class="header">
        <h1>CONTRAT PÉDAGOGIQUE</h1>
        <h2>Année Académique {{ $academicYear->year ?? $academicYear->label }}</h2>
    </div>

    <div class="section">
        <div class="section-title">INFORMATIONS ÉTUDIANT</div>
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
                <div class="info-label">Programme</div>
                <div class="info-value">{{ $program->name ?? $program->code }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Niveau</div>
                <div class="info-value">{{ $enrollment->level }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Statut</div>
                <div class="info-value">
                    <span class="status-badge status-validated">VALIDÉ</span>
                </div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">MODULES INSCRITS</div>
        <table class="modules">
            <thead>
                <tr>
                    <th style="width: 15%;">Code</th>
                    <th style="width: 50%;">Intitulé du Module</th>
                    <th style="width: 15%;">Semestre</th>
                    <th style="width: 10%;">ECTS</th>
                    <th style="width: 10%;">Type</th>
                </tr>
            </thead>
            <tbody>
                @php $totalEcts = 0; @endphp
                @forelse($modules as $moduleEnrollment)
                    @php
                        $module = $moduleEnrollment->module;
                        $totalEcts += $module->ects ?? 0;
                    @endphp
                    <tr>
                        <td>{{ $module->code ?? 'N/A' }}</td>
                        <td>{{ $module->name ?? 'N/A' }}</td>
                        <td>{{ $module->semester->name ?? 'N/A' }}</td>
                        <td style="text-align: center;">{{ $module->ects ?? 0 }}</td>
                        <td>{{ $module->is_mandatory ? 'Obligatoire' : 'Optionnel' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align: center; color: #718096;">
                            Aucun module inscrit
                        </td>
                    </tr>
                @endforelse
                <tr class="total-row">
                    <td colspan="3" style="text-align: right;">TOTAL CRÉDITS ECTS</td>
                    <td style="text-align: center;">{{ $totalEcts }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">RÉCAPITULATIF</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Nombre de modules</div>
                <div class="info-value">{{ $enrollment->total_modules }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Total crédits ECTS</div>
                <div class="info-value">{{ $enrollment->total_ects }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Date de validation</div>
                <div class="info-value">{{ $enrollment->validated_at?->format('d/m/Y à H:i') }}</div>
            </div>
        </div>
    </div>

    <div class="signature-section">
        <div class="signature-box">
            <h4>L'Étudiant</h4>
            <p>{{ $student->lastname }} {{ $student->firstname }}</p>
            <div class="signature-line"></div>
            <p style="margin-top: 5px; font-size: 9px;">Date et Signature</p>
        </div>
        <div class="signature-box">
            <h4>Le Responsable Pédagogique</h4>
            @if($validator)
                <p>{{ $validator->name }}</p>
            @endif
            <div class="signature-line"></div>
            <p style="margin-top: 5px; font-size: 9px;">Date et Signature</p>
        </div>
    </div>

    <div class="footer">
        <p>Document généré le {{ $generatedAt->format('d/m/Y à H:i') }}</p>
        <p>Ce document est un contrat pédagogique officiel. Toute modification doit être validée par le responsable pédagogique.</p>
    </div>
</body>
</html>
