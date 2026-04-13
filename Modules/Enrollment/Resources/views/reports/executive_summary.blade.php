<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapport Exécutif des Inscriptions - {{ $year->name }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #2563eb;
        }
        .header h1 {
            font-size: 18px;
            color: #1e40af;
            margin-bottom: 5px;
        }
        .header h2 {
            font-size: 14px;
            color: #64748b;
            font-weight: normal;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 13px;
            color: #1e40af;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .kpi-grid {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }
        .kpi-row {
            display: table-row;
        }
        .kpi-box {
            display: table-cell;
            width: 25%;
            padding: 10px;
            text-align: center;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .kpi-value {
            font-size: 20px;
            font-weight: bold;
            color: #1e40af;
        }
        .kpi-label {
            font-size: 9px;
            color: #64748b;
            text-transform: uppercase;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table th, table td {
            border: 1px solid #e2e8f0;
            padding: 6px 8px;
            text-align: left;
            font-size: 10px;
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
        .positive {
            color: #16a34a;
        }
        .negative {
            color: #dc2626;
        }
        .alert-box {
            padding: 8px 12px;
            margin-bottom: 8px;
            border-radius: 4px;
            font-size: 10px;
        }
        .alert-warning {
            background: #fef3c7;
            border-left: 3px solid #f59e0b;
            color: #92400e;
        }
        .alert-danger {
            background: #fee2e2;
            border-left: 3px solid #ef4444;
            color: #991b1b;
        }
        .footer {
            position: fixed;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Rapport Exécutif des Inscriptions</h1>
        <h2>Année Académique {{ $year->name }}</h2>
    </div>

    <div class="section">
        <h3 class="section-title">Indicateurs Clés (KPIs)</h3>
        <div class="kpi-grid">
            <div class="kpi-row">
                <div class="kpi-box">
                    <div class="kpi-value">{{ number_format($kpis['total_students']) }}</div>
                    <div class="kpi-label">Total Étudiants</div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-value">{{ number_format($kpis['active_students']) }}</div>
                    <div class="kpi-label">Étudiants Actifs</div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-value">{{ number_format($kpis['new_students']) }}</div>
                    <div class="kpi-label">Nouveaux</div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-value">{{ number_format($kpis['reenrollments']) }}</div>
                    <div class="kpi-label">Réinscriptions</div>
                </div>
            </div>
        </div>
        <div class="kpi-grid">
            <div class="kpi-row">
                <div class="kpi-box">
                    <div class="kpi-value">{{ number_format($kpis['pedagogical_validated']) }}</div>
                    <div class="kpi-label">Inscriptions Péda. Validées</div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-value">{{ number_format($kpis['pedagogical_pending']) }}</div>
                    <div class="kpi-label">En Attente</div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-value">{{ $kpis['conversion_rate'] }}%</div>
                    <div class="kpi-label">Taux Conversion</div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-value">{{ $kpis['validation_rate'] }}%</div>
                    <div class="kpi-label">Taux Validation</div>
                </div>
            </div>
        </div>
    </div>

    <div class="section">
        <h3 class="section-title">Inscriptions par Programme</h3>
        <table>
            <thead>
                <tr>
                    <th>Programme</th>
                    <th class="text-center">Total</th>
                    <th class="text-center">H</th>
                    <th class="text-center">F</th>
                    <th class="text-center">Âge Moy.</th>
                    <th class="text-center">N-1</th>
                    <th class="text-center">Évolution</th>
                </tr>
            </thead>
            <tbody>
                @foreach($programs->take(15) as $program)
                <tr>
                    <td>{{ $program['program']['name'] }}</td>
                    <td class="text-center">{{ $program['total'] }}</td>
                    <td class="text-center">{{ $program['male'] }}</td>
                    <td class="text-center">{{ $program['female'] }}</td>
                    <td class="text-center">{{ $program['average_age'] ? number_format($program['average_age'], 1) : '-' }}</td>
                    <td class="text-center">{{ $program['previous_year_count'] }}</td>
                    <td class="text-center {{ $program['growth_rate'] >= 0 ? 'positive' : 'negative' }}">
                        {{ $program['growth_rate'] >= 0 ? '+' : '' }}{{ number_format($program['growth_rate'], 1) }}%
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if(count($alerts) > 0)
    <div class="section">
        <h3 class="section-title">Alertes</h3>
        @foreach($alerts as $alert)
        <div class="alert-box alert-{{ $alert['type'] }}">
            {{ $alert['message'] }}
        </div>
        @endforeach
    </div>
    @endif

    <div class="section">
        <h3 class="section-title">Tendances sur 5 ans</h3>
        <table>
            <thead>
                <tr>
                    @foreach($trends as $trend)
                    <th class="text-center">{{ $trend['year'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr>
                    @foreach($trends as $trend)
                    <td class="text-center">{{ number_format($trend['count']) }}</td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3 class="section-title">Répartition Démographique</h3>
        <table>
            <thead>
                <tr>
                    <th colspan="2">Distribution par Âge</th>
                    <th colspan="2">Distribution par Genre</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $ageData = collect($demographics['age_distribution']);
                    $genderData = collect($demographics['gender_distribution']);
                    $maxRows = max($ageData->count(), $genderData->count());
                    $ageKeys = $ageData->keys()->toArray();
                    $genderKeys = $genderData->keys()->toArray();
                @endphp
                @for($i = 0; $i < $maxRows; $i++)
                <tr>
                    <td>{{ $ageKeys[$i] ?? '' }}</td>
                    <td class="text-right">{{ isset($ageKeys[$i]) ? $ageData[$ageKeys[$i]] : '' }}</td>
                    <td>{{ isset($genderKeys[$i]) ? ucfirst($genderKeys[$i]) : '' }}</td>
                    <td class="text-right">{{ isset($genderKeys[$i]) ? $genderData[$genderKeys[$i]] : '' }}</td>
                </tr>
                @endfor
            </tbody>
        </table>
    </div>

    <div class="footer">
        Rapport généré le {{ $generated_at->format('d/m/Y à H:i') }} | Année Académique {{ $year->name }}
    </div>
</body>
</html>
