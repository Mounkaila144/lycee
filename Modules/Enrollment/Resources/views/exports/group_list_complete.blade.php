<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste Complète - {{ $group->code }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9px;
            line-height: 1.3;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #2563eb;
        }
        .header h1 {
            font-size: 14px;
            color: #1e40af;
            margin-bottom: 5px;
        }
        .header-info {
            display: table;
            width: 100%;
            margin-top: 10px;
        }
        .header-info-row {
            display: table-row;
        }
        .header-info-cell {
            display: table-cell;
            width: 50%;
            text-align: left;
            padding: 2px 0;
            font-size: 9px;
        }
        .header-info-cell:last-child {
            text-align: right;
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
            margin-top: 15px;
        }
        table th, table td {
            border: 1px solid #e2e8f0;
            padding: 5px 6px;
            text-align: left;
            font-size: 8px;
        }
        table th {
            background: #f1f5f9;
            font-weight: bold;
            color: #1e40af;
        }
        table tr:nth-child(even) {
            background: #f8fafc;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .footer {
            position: fixed;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }
        .effectif-badge {
            display: inline-block;
            background: #dbeafe;
            color: #1e40af;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Liste Complète des Étudiants</h1>

        <div class="header-info">
            <div class="header-info-row">
                <div class="header-info-cell">
                    <span class="header-info-label">Module:</span>
                    <span class="header-info-value">{{ $module?->code ?? '-' }} - {{ $module?->name ?? '-' }}</span>
                </div>
                <div class="header-info-cell">
                    <span class="header-info-label">Groupe:</span>
                    <span class="header-info-value">{{ $group->code }} ({{ $group->type }})</span>
                </div>
            </div>
            <div class="header-info-row">
                <div class="header-info-cell">
                    <span class="header-info-label">Enseignant:</span>
                    <span class="header-info-value">{{ $teacher?->name ?? 'Non assigné' }}</span>
                </div>
                <div class="header-info-cell">
                    <span class="header-info-label">Effectif:</span>
                    <span class="effectif-badge">{{ $students->count() }} étudiants</span>
                </div>
            </div>
            <div class="header-info-row">
                <div class="header-info-cell">
                    <span class="header-info-label">Année académique:</span>
                    <span class="header-info-value">{{ $group->academicYear?->name ?? '-' }}</span>
                </div>
                <div class="header-info-cell">
                    <span class="header-info-label">Niveau:</span>
                    <span class="header-info-value">{{ $group->level ?? '-' }}</span>
                </div>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="text-center" style="width: 25px;">N°</th>
                <th style="width: 70px;">Matricule</th>
                <th>Nom</th>
                <th>Prénom</th>
                <th>Email</th>
                <th style="width: 90px;">Téléphone</th>
                <th style="width: 70px;">Date Naiss.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($students as $index => $student)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $student->matricule ?? '-' }}</td>
                <td>{{ $student->lastname ?? '-' }}</td>
                <td>{{ $student->firstname ?? '-' }}</td>
                <td>{{ $student->email ?? '-' }}</td>
                <td>{{ $student->phone ?? '-' }}</td>
                <td>{{ $student->birthdate?->format('d/m/Y') ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Liste générée le {{ $generated_at->format('d/m/Y à H:i') }} | {{ $group->code }}
    </div>
</body>
</html>
