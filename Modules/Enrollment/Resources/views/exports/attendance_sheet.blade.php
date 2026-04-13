<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feuille d'Émargement - {{ $group->code }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9px;
            line-height: 1.2;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid #2563eb;
        }
        .header h1 {
            font-size: 13px;
            color: #1e40af;
            margin-bottom: 3px;
        }
        .header-info {
            display: table;
            width: 100%;
            margin-top: 8px;
        }
        .header-info-row {
            display: table-row;
        }
        .header-info-cell {
            display: table-cell;
            width: 33%;
            text-align: left;
            padding: 2px 0;
            font-size: 8px;
        }
        .header-info-label {
            color: #64748b;
        }
        .header-info-value {
            font-weight: bold;
            color: #1e40af;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table th, table td {
            border: 1px solid #333;
            padding: 4px;
            text-align: center;
            font-size: 8px;
        }
        table th {
            background: #f1f5f9;
            font-weight: bold;
            color: #1e40af;
        }
        table td.name {
            text-align: left;
            padding-left: 6px;
        }
        table tr:nth-child(even) {
            background: #fafafa;
        }
        .session-col {
            width: 25px;
            min-width: 25px;
        }
        .signature-cell {
            height: 25px;
        }
        .footer {
            position: fixed;
            bottom: 15px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 7px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
        }
        .observations {
            margin-top: 15px;
            border: 1px solid #e2e8f0;
            padding: 10px;
            min-height: 60px;
        }
        .observations-title {
            font-size: 9px;
            font-weight: bold;
            color: #64748b;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Feuille d'Émargement</h1>

        <div class="header-info">
            <div class="header-info-row">
                <div class="header-info-cell">
                    <span class="header-info-label">Module:</span>
                    <span class="header-info-value">{{ $module?->code ?? '-' }}</span>
                </div>
                <div class="header-info-cell">
                    <span class="header-info-label">Groupe:</span>
                    <span class="header-info-value">{{ $group->code }} ({{ $group->type }})</span>
                </div>
                <div class="header-info-cell">
                    <span class="header-info-label">Effectif:</span>
                    <span class="header-info-value">{{ $students->count() }} étudiants</span>
                </div>
            </div>
            <div class="header-info-row">
                <div class="header-info-cell">
                    <span class="header-info-label">Enseignant:</span>
                    <span class="header-info-value">{{ $teacher?->name ?? 'Non assigné' }}</span>
                </div>
                <div class="header-info-cell">
                    <span class="header-info-label">Salle:</span>
                    <span class="header-info-value">_____________</span>
                </div>
                <div class="header-info-cell">
                    <span class="header-info-label">Horaire:</span>
                    <span class="header-info-value">_____________</span>
                </div>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 25px;">N°</th>
                <th style="width: 70px;">Matricule</th>
                <th style="width: 150px;" class="name">Nom et Prénom</th>
                @for($i = 1; $i <= $session_count; $i++)
                <th class="session-col">S{{ $i }}</th>
                @endfor
            </tr>
        </thead>
        <tbody>
            @foreach($students as $index => $student)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $student->matricule ?? '-' }}</td>
                <td class="name">{{ $student->lastname ?? '' }} {{ $student->firstname ?? '' }}</td>
                @for($i = 1; $i <= $session_count; $i++)
                <td class="signature-cell"></td>
                @endfor
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="observations">
        <div class="observations-title">Observations de l'enseignant:</div>
    </div>

    <div class="footer">
        Feuille d'émargement générée le {{ $generated_at->format('d/m/Y à H:i') }} | {{ $group->code }} | {{ $module?->code ?? '-' }}
    </div>
</body>
</html>
