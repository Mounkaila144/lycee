<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attestation de Dispense - {{ $exemption->exemption_number }}</title>
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
        .exemption-number {
            text-align: center;
            background-color: #e2e8f0;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .exemption-number strong {
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
        .status-approved {
            background-color: #c6f6d5;
            color: #276749;
        }
        .status-partial {
            background-color: #fef3c7;
            color: #92400e;
        }
        .module-box {
            background-color: #f0fff4;
            border: 1px solid #9ae6b4;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .module-title {
            font-size: 14px;
            font-weight: bold;
            color: #276749;
            margin-bottom: 10px;
        }
        .module-details {
            display: table;
            width: 100%;
        }
        .module-detail-item {
            display: table-cell;
            padding: 5px 15px;
            border-right: 1px solid #9ae6b4;
        }
        .module-detail-item:last-child {
            border-right: none;
        }
        .module-detail-label {
            font-size: 9px;
            color: #4a5568;
        }
        .module-detail-value {
            font-size: 12px;
            font-weight: bold;
            color: #1a365d;
        }
        .reason-box {
            background-color: #ebf8ff;
            border: 1px solid #90cdf4;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
        }
        .reason-category {
            font-weight: bold;
            color: #2b6cb0;
            margin-bottom: 5px;
        }
        .certification-box {
            background-color: #f0fff4;
            border: 2px solid #276749;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            text-align: center;
        }
        .certification-title {
            font-size: 14px;
            font-weight: bold;
            color: #276749;
            margin-bottom: 10px;
        }
        .certification-text {
            font-size: 11px;
            line-height: 1.6;
        }
        .grade-box {
            display: inline-block;
            background-color: #1a365d;
            color: white;
            padding: 10px 25px;
            border-radius: 5px;
            margin-top: 10px;
        }
        .grade-label {
            font-size: 10px;
        }
        .grade-value {
            font-size: 18px;
            font-weight: bold;
        }
        .signature-section {
            margin-top: 40px;
            display: table;
            width: 100%;
        }
        .signature-box {
            display: table-cell;
            width: 33%;
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
        .validity-notice {
            background-color: #fef3c7;
            border: 1px solid #f6e05e;
            padding: 10px;
            border-radius: 5px;
            margin-top: 15px;
            font-size: 10px;
        }
        .validity-title {
            font-weight: bold;
            color: #92400e;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>ATTESTATION DE DISPENSE DE MODULE</h1>
        <h2>Annee Academique {{ $academicYear->label ?? $academicYear->year }}</h2>
    </div>

    <div class="exemption-number">
        <strong>N&deg; {{ $exemption->exemption_number }}</strong>
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
                <div class="info-label">Programme</div>
                <div class="info-value">{{ $program->name ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Niveau</div>
                <div class="info-value">{{ $exemption->level ?? 'N/A' }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">MODULE CONCERNE</div>
        <div class="module-box">
            <div class="module-title">{{ $module->name }}</div>
            <div class="module-details">
                <div class="module-detail-item">
                    <div class="module-detail-label">Code</div>
                    <div class="module-detail-value">{{ $module->code }}</div>
                </div>
                <div class="module-detail-item">
                    <div class="module-detail-label">Credits ECTS</div>
                    <div class="module-detail-value">{{ $module->credits_ects ?? $module->ects ?? 0 }}</div>
                </div>
                <div class="module-detail-item">
                    <div class="module-detail-label">Semestre</div>
                    <div class="module-detail-value">{{ $module->semester->name ?? 'N/A' }}</div>
                </div>
                <div class="module-detail-item">
                    <div class="module-detail-label">Type</div>
                    <div class="module-detail-value">{{ $module->type ?? 'Standard' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">MOTIF DE LA DISPENSE</div>
        <div class="reason-box">
            <div class="reason-category">
                @php
                    $categoryLabels = [
                        'VAE' => 'Validation des Acquis de l\'Experience (VAE)',
                        'Prior_Training' => 'Formation anterieure equivalente',
                        'Professional_Certification' => 'Certification professionnelle',
                        'Special_Situation' => 'Situation particuliere',
                        'Double_Degree' => 'Double diplome',
                        'Other' => 'Autre motif'
                    ];
                @endphp
                {{ $categoryLabels[$exemption->reason_category] ?? $exemption->reason_category }}
            </div>
            <p>{{ $exemption->reason_details }}</p>
        </div>
    </div>

    <div class="certification-box">
        <div class="certification-title">DECISION</div>
        <div class="certification-text">
            @if($exemption->status === 'Approved')
                <p>Il est certifie que l'etudiant(e) <strong>{{ $student->lastname }} {{ $student->firstname }}</strong>,
                matricule <strong>{{ $student->matricule }}</strong>, est dispense(e) du module
                <strong>{{ $module->name }}</strong> ({{ $module->code }}).</p>

                <p style="margin-top: 10px;">
                    <span class="status-badge status-approved">DISPENSE TOTALE ACCORDEE</span>
                </p>

                @if($exemption->grade)
                <div class="grade-box">
                    <div class="grade-label">Note attribuee</div>
                    <div class="grade-value">{{ $exemption->grade }}/20</div>
                </div>
                @endif

                <p style="margin-top: 15px; font-size: 10px;">
                    Les <strong>{{ $module->credits_ects ?? $module->ects ?? 0 }} credits ECTS</strong> correspondants sont valides.
                </p>
            @elseif($exemption->status === 'Partially_Approved')
                <p>Il est certifie que l'etudiant(e) <strong>{{ $student->lastname }} {{ $student->firstname }}</strong>,
                matricule <strong>{{ $student->matricule }}</strong>, beneficie d'une dispense partielle pour le module
                <strong>{{ $module->name }}</strong> ({{ $module->code }}).</p>

                <p style="margin-top: 10px;">
                    <span class="status-badge status-partial">DISPENSE PARTIELLE ACCORDEE</span>
                </p>

                @if($exemption->notes)
                <p style="margin-top: 15px; font-size: 10px;">
                    <strong>Conditions:</strong> {{ $exemption->notes }}
                </p>
                @endif
            @endif
        </div>
    </div>

    <div class="section">
        <div class="section-title">VALIDATION</div>
        <div class="info-grid">
            @if($teacherReviewer)
            <div class="info-row">
                <div class="info-label">Avis enseignant</div>
                <div class="info-value">{{ $teacherReviewer->name }} - {{ $exemption->teacher_opinion ?? 'Favorable' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Date avis enseignant</div>
                <div class="info-value">{{ $exemption->teacher_reviewed_at?->format('d/m/Y') ?? 'N/A' }}</div>
            </div>
            @endif
            <div class="info-row">
                <div class="info-label">Valide par</div>
                <div class="info-value">{{ $validator->name ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Date de validation</div>
                <div class="info-value">{{ $exemption->validated_at?->format('d/m/Y a H:i') ?? 'N/A' }}</div>
            </div>
        </div>
    </div>

    <div class="validity-notice">
        <div class="validity-title">VALIDITE</div>
        <p>Cette dispense est valable uniquement pour l'annee academique {{ $academicYear->label ?? $academicYear->year }}
        et pour le programme {{ $program->name ?? 'specifie' }}.</p>
        <p>En cas de changement de programme ou de redoublement, une nouvelle demande devra etre soumise.</p>
    </div>

    <div class="signature-section">
        <div class="signature-box">
            <h4>L'Enseignant Responsable</h4>
            @if($teacherReviewer)
                <p>{{ $teacherReviewer->name }}</p>
            @endif
            <div class="signature-line"></div>
            <p style="margin-top: 5px; font-size: 9px;">Date et Signature</p>
        </div>
        <div class="signature-box">
            <h4>Le Responsable Pedagogique</h4>
            @if($validator)
                <p>{{ $validator->name }}</p>
            @endif
            <div class="signature-line"></div>
            <p style="margin-top: 5px; font-size: 9px;">Date et Signature</p>
        </div>
        <div class="signature-box">
            <h4>Le Directeur des Etudes</h4>
            <div class="signature-line"></div>
            <p style="margin-top: 5px; font-size: 9px;">Date, Signature et Cachet</p>
        </div>
    </div>

    <div class="footer">
        <p>Document genere le {{ $generatedAt->format('d/m/Y a H:i') }}</p>
        <p>Numero d'attestation: {{ $exemption->exemption_number }}</p>
        <p>Cette attestation est un document officiel. Toute falsification est passible de poursuites.</p>
    </div>
</body>
</html>
