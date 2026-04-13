<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord des Inscriptions - {{ $year->name }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
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
            font-size: 16px;
            color: #1e40af;
            margin-bottom: 3px;
        }
        .header h2 {
            font-size: 12px;
            color: #64748b;
            font-weight: normal;
        }
        .section {
            margin-bottom: 15px;
        }
        .section-title {
            font-size: 11px;
            color: #1e40af;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 3px;
            margin-bottom: 8px;
        }
        .grid-2 {
            display: table;
            width: 100%;
        }
        .grid-2-col {
            display: table-cell;
            width: 50%;
            padding-right: 10px;
            vertical-align: top;
        }
        .grid-2-col:last-child {
            padding-right: 0;
            padding-left: 10px;
        }
        .kpi-grid {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }
        .kpi-row {
            display: table-row;
        }
        .kpi-box {
            display: table-cell;
            width: 20%;
            padding: 8px;
            text-align: center;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .kpi-value {
            font-size: 16px;
            font-weight: bold;
            color: #1e40af;
        }
        .kpi-label {
            font-size: 8px;
            color: #64748b;
            text-transform: uppercase;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table th, table td {
            border: 1px solid #e2e8f0;
            padding: 4px 6px;
            text-align: left;
            font-size: 9px;
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
        .stat-bar {
            height: 12px;
            background: #e2e8f0;
            border-radius: 6px;
            overflow: hidden;
            margin-top: 2px;
        }
        .stat-bar-fill {
            height: 100%;
            background: #2563eb;
            border-radius: 6px;
        }
        .footer {
            position: fixed;
            bottom: 15px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }
        .page-break {
            page-break-after: always;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 8px;
            font-weight: bold;
        }
        .status-active {
            background: #dcfce7;
            color: #166534;
        }
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Tableau de Bord des Inscriptions</h1>
        <h2>Année Académique {{ $year->name }}</h2>
    </div>

    <div class="section">
        <h3 class="section-title">Indicateurs Clés</h3>
        <div class="kpi-grid">
            <div class="kpi-row">
                <div class="kpi-box">
                    <div class="kpi-value">{{ number_format($kpis['total_students']) }}</div>
                    <div class="kpi-label">Total</div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-value">{{ number_format($kpis['active_students']) }}</div>
                    <div class="kpi-label">Actifs</div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-value">{{ number_format($kpis['pedagogical_validated']) }}</div>
                    <div class="kpi-label">Péda. Validés</div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-value">{{ $kpis['conversion_rate'] }}%</div>
                    <div class="kpi-label">Conversion</div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-value">{{ $kpis['validation_rate'] }}%</div>
                    <div class="kpi-label">Validation</div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid-2">
        <div class="grid-2-col">
            <div class="section">
                <h3 class="section-title">Statut des Étudiants</h3>
                <table>
                    <tbody>
                        <tr>
                            <td><span class="status-badge status-active">Actif</span></td>
                            <td class="text-right">{{ number_format($statusStats['active'] ?? 0) }}</td>
                        </tr>
                        <tr>
                            <td><span class="status-badge status-pending">Suspendu</span></td>
                            <td class="text-right">{{ number_format($statusStats['suspended'] ?? 0) }}</td>
                        </tr>
                        <tr>
                            <td>Exclu</td>
                            <td class="text-right">{{ number_format($statusStats['excluded'] ?? 0) }}</td>
                        </tr>
                        <tr>
                            <td>Diplômé</td>
                            <td class="text-right">{{ number_format($statusStats['graduated'] ?? 0) }}</td>
                        </tr>
                        <tr>
                            <td>Archivé</td>
                            <td class="text-right">{{ number_format($statusStats['archived'] ?? 0) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="section">
                <h3 class="section-title">Inscription Pédagogique</h3>
                <table>
                    <tbody>
                        @foreach($pedagogical['status_distribution'] ?? [] as $status => $count)
                        <tr>
                            <td>{{ $status }}</td>
                            <td class="text-right">{{ number_format($count) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                <h4 style="font-size: 9px; margin: 8px 0 5px; color: #64748b;">Taux de Validation</h4>
                <table>
                    <tbody>
                        <tr>
                            <td>Modules</td>
                            <td class="text-right">{{ number_format($pedagogical['modules_check_rate'] ?? 0, 1) }}%</td>
                        </tr>
                        <tr>
                            <td>Groupes</td>
                            <td class="text-right">{{ number_format($pedagogical['groups_check_rate'] ?? 0, 1) }}%</td>
                        </tr>
                        <tr>
                            <td>Options</td>
                            <td class="text-right">{{ number_format($pedagogical['options_check_rate'] ?? 0, 1) }}%</td>
                        </tr>
                        <tr>
                            <td>Prérequis</td>
                            <td class="text-right">{{ number_format($pedagogical['prerequisites_check_rate'] ?? 0, 1) }}%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid-2-col">
            <div class="section">
                <h3 class="section-title">Distribution par Âge</h3>
                <table>
                    <tbody>
                        @foreach($demographics['age_distribution'] ?? [] as $range => $count)
                        <tr>
                            <td>{{ $range }}</td>
                            <td class="text-right">{{ number_format($count) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="section">
                <h3 class="section-title">Distribution par Genre</h3>
                <table>
                    <tbody>
                        @foreach($demographics['gender_distribution'] ?? [] as $gender => $count)
                        <tr>
                            <td>{{ ucfirst($gender) }}</td>
                            <td class="text-right">{{ number_format($count) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="section">
                <h3 class="section-title">Top 5 Villes</h3>
                <table>
                    <tbody>
                        @foreach(collect($demographics['geographic_distribution'] ?? [])->take(5) as $city => $count)
                        <tr>
                            <td>{{ $city ?: 'Non renseigné' }}</td>
                            <td class="text-right">{{ number_format($count) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="section">
        <h3 class="section-title">Top 10 Programmes par Effectif</h3>
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
                @foreach($programs->take(10) as $program)
                <tr>
                    <td>{{ $program['program']['code'] }} - {{ Str::limit($program['program']['name'], 30) }}</td>
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

    @if(count($monthlyTrends) > 0)
    <div class="section">
        <h3 class="section-title">Inscriptions Mensuelles</h3>
        <table>
            <thead>
                <tr>
                    @foreach($monthlyTrends as $trend)
                    <th class="text-center">{{ $trend['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr>
                    @foreach($monthlyTrends as $trend)
                    <td class="text-center">{{ number_format($trend['count']) }}</td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>
    @endif

    <div class="footer">
        Tableau de bord généré le {{ $generated_at->format('d/m/Y à H:i') }} | Année Académique {{ $year->name }}
    </div>
</body>
</html>
