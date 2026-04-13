<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attestation d'Equivalences - {{ $transfer->transfer_number }}</title>
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
        .transfer-number {
            text-align: center;
            background-color: #e2e8f0;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .transfer-number strong {
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
        .equivalences-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 10px;
        }
        .equivalences-table th,
        .equivalences-table td {
            border: 1px solid #cbd5e0;
            padding: 6px;
            text-align: left;
        }
        .equivalences-table th {
            background-color: #1a365d;
            color: white;
            font-weight: bold;
        }
        .equivalences-table tr:nth-child(even) {
            background-color: #f7fafc;
        }
        .equivalence-full {
            background-color: #c6f6d5 !important;
        }
        .equivalence-partial {
            background-color: #fef3c7 !important;
        }
        .equivalence-none {
            background-color: #fed7d7 !important;
        }
        .equivalence-exemption {
            background-color: #e9d8fd !important;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
        }
        .badge-full {
            background-color: #276749;
            color: white;
        }
        .badge-partial {
            background-color: #92400e;
            color: white;
        }
        .badge-none {
            background-color: #c53030;
            color: white;
        }
        .badge-exemption {
            background-color: #6b46c1;
            color: white;
        }
        .summary-box {
            background-color: #f0fff4;
            border: 1px solid #9ae6b4;
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
        }
        .summary-grid {
            display: table;
            width: 100%;
        }
        .summary-item {
            display: table-cell;
            text-align: center;
            padding: 10px;
        }
        .summary-number {
            font-size: 24px;
            font-weight: bold;
            color: #1a365d;
        }
        .summary-label {
            font-size: 10px;
            color: #4a5568;
        }
        .origin-box {
            background-color: #fff5f5;
            border: 1px solid #feb2b2;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .origin-title {
            font-weight: bold;
            color: #c53030;
            margin-bottom: 5px;
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
        .legend {
            margin-top: 15px;
            padding: 10px;
            background-color: #f7fafc;
            border-radius: 5px;
            font-size: 9px;
        }
        .legend-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .legend-item {
            display: inline-block;
            margin-right: 15px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>ATTESTATION D'EQUIVALENCES</h1>
        <h2>Annee Academique {{ $academicYear->label ?? $academicYear->year }}</h2>
    </div>

    <div class="transfer-number">
        <strong>Dossier N&deg; {{ $transfer->transfer_number }}</strong>
    </div>

    <div class="section">
        <div class="section-title">INFORMATIONS CANDIDAT</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Nom complet</div>
                <div class="info-value">{{ $transfer->last_name }} {{ $transfer->first_name }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Date de naissance</div>
                <div class="info-value">{{ $transfer->birth_date?->format('d/m/Y') ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Email</div>
                <div class="info-value">{{ $transfer->email }}</div>
            </div>
            @if($student)
            <div class="info-row">
                <div class="info-label">Matricule attribue</div>
                <div class="info-value">{{ $student->matricule }}</div>
            </div>
            @endif
        </div>
    </div>

    <div class="section">
        <div class="section-title">ETABLISSEMENT D'ORIGINE</div>
        <div class="origin-box">
            <div class="origin-title">{{ $transfer->origin_institution }}</div>
            <p><strong>Programme:</strong> {{ $transfer->origin_program }}</p>
            <p><strong>Niveau atteint:</strong> {{ $transfer->origin_level }}</p>
            <p><strong>Credits valides:</strong> {{ $transfer->total_credits_validated ?? 0 }} ECTS</p>
        </div>
    </div>

    <div class="section">
        <div class="section-title">PROGRAMME CIBLE</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Programme</div>
                <div class="info-value">{{ $targetProgram->name }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Code</div>
                <div class="info-value">{{ $targetProgram->code }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Niveau d'admission</div>
                <div class="info-value">{{ $transfer->admission_level ?? 'A determiner' }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">TABLEAU DES EQUIVALENCES</div>
        <table class="equivalences-table">
            <thead>
                <tr>
                    <th style="width: 25%;">Module Origine</th>
                    <th style="width: 8%;">ECTS</th>
                    <th style="width: 25%;">Module Equivalent</th>
                    <th style="width: 8%;">ECTS</th>
                    <th style="width: 12%;">Type</th>
                    <th style="width: 10%;">Score</th>
                    <th style="width: 12%;">Note</th>
                </tr>
            </thead>
            <tbody>
                @forelse($equivalences as $eq)
                    @php
                        $rowClass = match($eq->equivalence_type) {
                            'Full' => 'equivalence-full',
                            'Partial' => 'equivalence-partial',
                            'None' => 'equivalence-none',
                            'Exemption' => 'equivalence-exemption',
                            default => ''
                        };
                        $badgeClass = match($eq->equivalence_type) {
                            'Full' => 'badge-full',
                            'Partial' => 'badge-partial',
                            'None' => 'badge-none',
                            'Exemption' => 'badge-exemption',
                            default => ''
                        };
                    @endphp
                    <tr class="{{ $rowClass }}">
                        <td>{{ $eq->origin_module_name }}</td>
                        <td style="text-align: center;">{{ $eq->origin_credits }}</td>
                        <td>{{ $eq->targetModule->name ?? 'N/A' }}</td>
                        <td style="text-align: center;">{{ $eq->targetModule->credits_ects ?? '-' }}</td>
                        <td>
                            <span class="status-badge {{ $badgeClass }}">
                                {{ $eq->equivalence_type }}
                            </span>
                        </td>
                        <td style="text-align: center;">{{ $eq->similarity_score ? number_format($eq->similarity_score, 0) . '%' : '-' }}</td>
                        <td style="text-align: center;">{{ $eq->grade ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center; color: #718096;">
                            Aucune equivalence enregistree
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="legend">
            <div class="legend-title">Legende:</div>
            <span class="legend-item"><span class="status-badge badge-full">Full</span> Equivalence totale</span>
            <span class="legend-item"><span class="status-badge badge-partial">Partial</span> Equivalence partielle</span>
            <span class="legend-item"><span class="status-badge badge-exemption">Exemption</span> Dispense</span>
            <span class="legend-item"><span class="status-badge badge-none">None</span> Pas d'equivalence</span>
        </div>
    </div>

    <div class="section">
        <div class="section-title">RESUME</div>
        <div class="summary-box">
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-number">{{ $stats['total'] ?? 0 }}</div>
                    <div class="summary-label">Modules analyses</div>
                </div>
                <div class="summary-item">
                    <div class="summary-number">{{ $stats['full'] ?? 0 }}</div>
                    <div class="summary-label">Equivalences totales</div>
                </div>
                <div class="summary-item">
                    <div class="summary-number">{{ $stats['partial'] ?? 0 }}</div>
                    <div class="summary-label">Equivalences partielles</div>
                </div>
                <div class="summary-item">
                    <div class="summary-number">{{ $stats['credits_validated'] ?? 0 }}</div>
                    <div class="summary-label">Credits valides</div>
                </div>
            </div>
        </div>
    </div>

    <div class="signature-section">
        <div class="signature-box">
            <h4>Le Responsable Pedagogique</h4>
            @if($validator)
                <p>{{ $validator->name }}</p>
            @endif
            <div class="signature-line"></div>
            <p style="margin-top: 5px; font-size: 9px;">Date, Signature et Cachet</p>
        </div>
        <div class="signature-box">
            <h4>Le Directeur des Etudes</h4>
            <div class="signature-line"></div>
            <p style="margin-top: 5px; font-size: 9px;">Date, Signature et Cachet</p>
        </div>
    </div>

    <div class="footer">
        <p>Document genere le {{ $generatedAt->format('d/m/Y a H:i') }}</p>
        <p>Numero de dossier: {{ $transfer->transfer_number }}</p>
        <p>Cette attestation est un document officiel. Toute falsification est passible de poursuites.</p>
    </div>
</body>
</html>
