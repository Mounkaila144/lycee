@php
    $formatNumber = fn ($value, int $decimals = 2) => $value === null ? '—' : number_format((float) $value, $decimals, ',', ' ');
    $formatCoefficient = fn ($value) => $value === null ? '—' : rtrim(rtrim(number_format((float) $value, 2, ',', ' '), '0'), ',');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bulletin — {{ $classe->name }} — {{ $semester->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; margin: 0; padding: 18px 22px; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        table { border-collapse: collapse; width: 100%; }
        .header td { vertical-align: top; }
        .header .school { font-size: 15px; font-weight: bold; color: #1a3d7c; text-transform: uppercase; }
        .header .title { text-align: right; }
        .header .title h1 { margin: 0; font-size: 16px; color: #1a3d7c; }
        .header .title p { margin: 2px 0; font-size: 10px; color: #555; }
        .provisional { margin-top: 4px; color: #b71c1c; font-weight: bold; font-size: 10px; }
        .identity { margin: 10px 0; }
        .identity td { padding: 5px 8px; background: #f3f6fb; border: 1px solid #d9e1ee; width: 33%; }
        .identity strong { color: #1a3d7c; }
        table.grades th { background: #1a3d7c; color: #fff; padding: 5px 4px; font-size: 9px; border: 1px solid #1a3d7c; }
        table.grades td { padding: 4px; border: 1px solid #c9d3e3; font-size: 9.5px; }
        table.grades td.num { text-align: center; }
        table.grades tr.totals td { background: #eef2f8; font-weight: bold; }
        .teacher { color: #666; font-size: 8.5px; }
        .summary { margin-top: 10px; }
        .summary td { vertical-align: top; padding: 6px 8px; border: 1px solid #d9e1ee; }
        .summary .big { font-size: 15px; font-weight: bold; color: #1a3d7c; }
        .label { color: #555; }
        .appreciation { margin-top: 10px; border: 1px solid #d9e1ee; padding: 8px; min-height: 50px; }
        .appreciation h3 { margin: 0 0 4px; font-size: 10px; color: #1a3d7c; text-transform: uppercase; }
        .signatures { margin-top: 18px; }
        .signatures td { width: 50%; text-align: center; padding-top: 4px; height: 60px; vertical-align: top; color: #555; }
        .footer { margin-top: 12px; font-size: 8px; color: #999; text-align: center; }
    </style>
</head>
<body>
@foreach ($reports as $report)
    <div class="page">
        <table class="header">
            <tr>
                <td>
                    <div class="school">{{ $establishment['name'] }}</div>
                    <div>Année scolaire {{ $academicYear?->name ?? '' }}</div>
                </td>
                <td class="title">
                    <h1>BULLETIN DE NOTES</h1>
                    <p>{{ $semester->name === 'S1' ? '1er semestre' : ($semester->name === 'S2' ? '2e semestre' : $semester->name) }}</p>
                    @if ($isProvisional)
                        <div class="provisional">Document provisoire (évaluations non publiées incluses)</div>
                    @endif
                </td>
            </tr>
        </table>

        <table class="identity">
            <tr>
                <td><span class="label">Élève :</span> <strong>{{ $report['student']['full_name'] }}</strong></td>
                <td><span class="label">Matricule :</span> {{ $report['student']['matricule'] ?? '—' }}</td>
                <td><span class="label">Classe :</span> <strong>{{ $classe->name }}</strong> ({{ $report['class_size'] }} élèves)</td>
            </tr>
            <tr>
                <td colspan="3"><span class="label">Professeur principal :</span> {{ $report['classe']['head_teacher']['full_name'] ?? '—' }}</td>
            </tr>
        </table>

        <table class="grades">
            <thead>
                <tr>
                    <th style="text-align:left;">Matière</th>
                    <th>Moy. /20</th>
                    <th>Coef.</th>
                    <th>Moy. × Coef.</th>
                    <th>Rang</th>
                    <th>Moy. classe</th>
                    <th style="text-align:left;">Appréciation</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['subjects'] as $row)
                    <tr>
                        <td>
                            {{ $row['subject']['name'] }}
                            @if ($row['teacher'])
                                <div class="teacher">{{ $row['teacher']['full_name'] }}</div>
                            @endif
                        </td>
                        <td class="num">{{ $formatNumber($row['average']) }}</td>
                        <td class="num">{{ $formatCoefficient($row['coefficient']) }}</td>
                        <td class="num">{{ $formatNumber($row['weighted_average']) }}</td>
                        <td class="num">{{ $row['rank'] ?? '—' }}</td>
                        <td class="num">{{ $formatNumber($row['class_average']) }}</td>
                        <td>{{ $row['appreciation'] ?? '' }}</td>
                    </tr>
                @endforeach
                <tr class="totals">
                    <td>Totaux</td>
                    <td class="num"></td>
                    <td class="num">{{ $formatCoefficient($report['totals']['coefficients']) }}</td>
                    <td class="num">{{ $formatNumber($report['totals']['weighted_points']) }}</td>
                    <td class="num"></td>
                    <td class="num"></td>
                    <td></td>
                </tr>
            </tbody>
        </table>

        <table class="summary">
            <tr>
                <td>
                    <div class="label">Moyenne générale</div>
                    <div class="big">{{ $formatNumber($report['general_average']) }} / 20</div>
                </td>
                <td>
                    <div class="label">Rang</div>
                    <div class="big">{{ $report['rank'] ? $report['rank'].($report['rank'] === 1 ? 'er' : 'e') : '—' }} / {{ $report['class_size'] }}</div>
                </td>
                <td>
                    <div class="label">Mention</div>
                    <div class="big">{{ $report['mention'] ?? '—' }}</div>
                </td>
                <td>
                    <div class="label">Distinction</div>
                    <div><strong>{{ $report['distinction'] ?? '—' }}</strong></div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="label">Moyenne de la classe :</span> {{ $formatNumber($report['class_stats']['class_average']) }}
                    &nbsp;·&nbsp; <span class="label">Plus forte :</span> {{ $formatNumber($report['class_stats']['highest']) }}
                    &nbsp;·&nbsp; <span class="label">Plus faible :</span> {{ $formatNumber($report['class_stats']['lowest']) }}
                </td>
                <td colspan="2">
                    @if ($report['annual'])
                        <span class="label">Moyenne annuelle :</span> <strong>{{ $formatNumber($report['annual']['average']) }}</strong>
                        &nbsp;·&nbsp; <span class="label">Rang annuel :</span> {{ $report['annual']['rank'] ?? '—' }} / {{ $report['class_size'] }}
                    @endif
                </td>
            </tr>
        </table>

        <div class="appreciation">
            <h3>Appréciation générale du conseil de classe</h3>
            {{ $report['general_appreciation'] ?? '' }}
        </div>

        <table class="signatures">
            <tr>
                <td>Le professeur principal</td>
                <td>Le chef d'établissement</td>
            </tr>
        </table>

        <div class="footer">Bulletin édité le {{ $generatedAt->format('d/m/Y à H:i') }}</div>
    </div>
@endforeach
</body>
</html>
