<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiche d'Inscription - {{ $student->matricule }}</title>
    @php
        // Calcul dynamique de la taille de police selon le nombre de modules
        $moduleCount = $moduleEnrollments->count();

        if ($moduleCount <= 8) {
            $bodyFontSize = '11px';
            $tableFontSize = '10px';
            $headerFontSize = '16px';
            $sectionTitleSize = '11px';
            $cellPadding = '6px';
            $sectionMargin = '15px';
        } elseif ($moduleCount <= 12) {
            $bodyFontSize = '10px';
            $tableFontSize = '9px';
            $headerFontSize = '14px';
            $sectionTitleSize = '10px';
            $cellPadding = '4px';
            $sectionMargin = '10px';
        } elseif ($moduleCount <= 16) {
            $bodyFontSize = '9px';
            $tableFontSize = '8px';
            $headerFontSize = '13px';
            $sectionTitleSize = '9px';
            $cellPadding = '3px';
            $sectionMargin = '8px';
        } elseif ($moduleCount <= 20) {
            $bodyFontSize = '8px';
            $tableFontSize = '7px';
            $headerFontSize = '12px';
            $sectionTitleSize = '8px';
            $cellPadding = '2px';
            $sectionMargin = '6px';
        } else {
            // Plus de 20 modules - taille minimale
            $bodyFontSize = '7px';
            $tableFontSize = '6px';
            $headerFontSize = '11px';
            $sectionTitleSize = '7px';
            $cellPadding = '2px';
            $sectionMargin = '5px';
        }
    @endphp
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html, body {
            height: 100%;
            width: 100%;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: {{ $bodyFontSize }};
            line-height: 1.3;
            color: #333;
            padding: 5px;
            max-height: 277mm; /* A4 height minus margins */
            overflow: hidden;
        }
        .container {
            max-height: 100%;
            display: flex;
            flex-direction: column;
        }
        .header {
            text-align: center;
            margin-bottom: {{ $sectionMargin }};
            border-bottom: 2px solid #1a365d;
            padding-bottom: 8px;
        }
        .header h1 {
            font-size: {{ $headerFontSize }};
            color: #1a365d;
            margin-bottom: 3px;
        }
        .header h2 {
            font-size: calc({{ $headerFontSize }} - 2px);
            color: #4a5568;
            font-weight: normal;
        }
        .section {
            margin-bottom: {{ $sectionMargin }};
        }
        .section-title {
            font-size: {{ $sectionTitleSize }};
            font-weight: bold;
            color: #1a365d;
            background-color: #e2e8f0;
            padding: 5px 8px;
            margin-bottom: 5px;
            border-left: 3px solid #1a365d;
        }
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            width: 30%;
            padding: 2px 6px;
            font-weight: bold;
            background-color: #f7fafc;
            font-size: {{ $bodyFontSize }};
        }
        .info-value {
            display: table-cell;
            padding: 2px 6px;
            font-size: {{ $bodyFontSize }};
        }
        /* Two column layout for student info */
        .info-columns {
            display: table;
            width: 100%;
        }
        .info-column {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .info-column .info-grid {
            margin-bottom: 0;
        }
        table.modules {
            width: 100%;
            border-collapse: collapse;
            font-size: {{ $tableFontSize }};
        }
        table.modules th,
        table.modules td {
            border: 1px solid #cbd5e0;
            padding: {{ $cellPadding }};
            text-align: left;
        }
        table.modules th {
            background-color: #1a365d;
            color: white;
            font-weight: bold;
            font-size: {{ $tableFontSize }};
        }
        table.modules tr:nth-child(even) {
            background-color: #f7fafc;
        }
        .total-row {
            font-weight: bold;
            background-color: #e2e8f0 !important;
        }
        .signature-section {
            margin-top: auto;
            padding-top: 10px;
            display: table;
            width: 100%;
        }
        .signature-box {
            display: table-cell;
            width: 50%;
            padding: 5px;
            font-size: {{ $bodyFontSize }};
        }
        .signature-box h4 {
            font-size: {{ $bodyFontSize }};
            margin-bottom: 3px;
            color: #4a5568;
        }
        .signature-line {
            border-bottom: 1px solid #333;
            height: 25px;
            margin-top: 15px;
        }
        .footer {
            margin-top: 8px;
            padding-top: 5px;
            border-top: 1px solid #cbd5e0;
            font-size: calc({{ $bodyFontSize }} - 2px);
            color: #718096;
            text-align: center;
        }
        .status-badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 2px;
            font-size: calc({{ $bodyFontSize }} - 1px);
            font-weight: bold;
        }
        .status-actif {
            background-color: #c6f6d5;
            color: #276749;
        }
        .status-suspendu {
            background-color: #feebc8;
            color: #c05621;
        }
        .type-obligatoire {
            color: #1a365d;
            font-weight: bold;
        }
        .type-optionnel {
            color: #4a5568;
        }
        /* Compact summary */
        .summary-inline {
            display: table;
            width: 100%;
            font-size: {{ $bodyFontSize }};
        }
        .summary-item {
            display: table-cell;
            text-align: center;
            padding: 3px;
            border: 1px solid #cbd5e0;
        }
        .summary-item strong {
            display: block;
            color: #1a365d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>FICHE D'INSCRIPTION PÉDAGOGIQUE</h1>
            <h2>Année Académique {{ $academicYear->name ?? ($academicYear->year_start . '-' . $academicYear->year_end) }}</h2>
        </div>

        <div class="section">
            <div class="section-title">INFORMATIONS ÉTUDIANT</div>
            <div class="info-columns">
                <div class="info-column">
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
                            <div class="info-value">{{ $programme->libelle ?? $programme->code }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Niveau</div>
                            <div class="info-value">{{ $enrollment->level }}</div>
                        </div>
                    </div>
                </div>
                <div class="info-column">
                    <div class="info-grid">
                        <div class="info-row">
                            <div class="info-label">Semestre</div>
                            <div class="info-value">{{ $semester->name ?? 'N/A' }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Date d'inscription</div>
                            <div class="info-value">{{ $enrollment->enrollment_date?->format('d/m/Y') ?? $enrollment->created_at?->format('d/m/Y') }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Statut</div>
                            <div class="info-value">
                                <span class="status-badge status-{{ strtolower($enrollment->status) }}">{{ $enrollment->status }}</span>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">N° Inscription</div>
                            <div class="info-value">#{{ $enrollment->id }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">MODULES INSCRITS ({{ $moduleEnrollments->count() }} modules)</div>
            <table class="modules">
                <thead>
                    <tr>
                        <th style="width: 12%;">Code</th>
                        <th style="width: 48%;">Intitulé du Module</th>
                        <th style="width: 10%;">ECTS</th>
                        <th style="width: 15%;">Type</th>
                        <th style="width: 15%;">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @php $totalEcts = 0; @endphp
                    @forelse($moduleEnrollments as $moduleEnrollment)
                        @php
                            $module = $moduleEnrollment->module;
                            $credits = $module->credits_ects ?? 0;
                            $totalEcts += $credits;
                        @endphp
                        <tr>
                            <td>{{ $module->code ?? 'N/A' }}</td>
                            <td>{{ Str::limit($module->name ?? 'N/A', $moduleCount > 15 ? 40 : 60) }}</td>
                            <td style="text-align: center;">{{ $credits }}</td>
                            <td>
                                <span class="{{ $moduleEnrollment->is_optional ? 'type-optionnel' : 'type-obligatoire' }}">
                                    {{ $moduleEnrollment->is_optional ? 'Opt.' : 'Obl.' }}
                                </span>
                            </td>
                            <td>{{ $moduleEnrollment->status ?? 'Inscrit' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; color: #718096;">
                                Aucun module inscrit
                            </td>
                        </tr>
                    @endforelse
                    <tr class="total-row">
                        <td colspan="2" style="text-align: right;">TOTAL CRÉDITS ECTS</td>
                        <td style="text-align: center;">{{ $totalEcts }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="section">
            <div class="summary-inline">
                <div class="summary-item">
                    <strong>{{ $moduleEnrollments->count() }}</strong>
                    Modules
                </div>
                <div class="summary-item">
                    <strong>{{ $moduleEnrollments->where('is_optional', false)->count() }}</strong>
                    Obligatoires
                </div>
                <div class="summary-item">
                    <strong>{{ $moduleEnrollments->where('is_optional', true)->count() }}</strong>
                    Optionnels
                </div>
                <div class="summary-item">
                    <strong>{{ $totalEcts }} ECTS</strong>
                    Total Crédits
                </div>
            </div>
        </div>

        <div class="signature-section">
            <div class="signature-box">
                <h4>L'Étudiant</h4>
                <p>{{ $student->lastname }} {{ $student->firstname }}</p>
                <div class="signature-line"></div>
                <p style="font-size: 7px; margin-top: 3px;">Date et Signature</p>
            </div>
            <div class="signature-box">
                <h4>Le Responsable Pédagogique</h4>
                @if($enrolledBy)
                    <p>{{ $enrolledBy->firstname }} {{ $enrolledBy->lastname }}</p>
                @else
                    <p>&nbsp;</p>
                @endif
                <div class="signature-line"></div>
                <p style="font-size: 7px; margin-top: 3px;">Date et Signature</p>
            </div>
        </div>

        <div class="footer">
            Document généré le {{ $generatedAt->format('d/m/Y à H:i') }} | Fiche d'inscription pédagogique officielle
        </div>
    </div>
</body>
</html>
