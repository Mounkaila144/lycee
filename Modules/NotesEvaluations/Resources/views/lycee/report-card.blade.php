@php
    $formatNumber = fn ($value, int $decimals = 2) => $value === null ? '—' : number_format((float) $value, $decimals, ',', ' ');
    $formatCoefficient = fn ($value) => $value === null ? '—' : rtrim(rtrim(number_format((float) $value, 2, ',', ' '), '0'), ',');
    $formatRank = fn ($rank) => $rank ? $rank.((int) $rank === 1 ? 'er' : 'e') : '—';
    $formatDuration = function (int $minutes): string {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $rest === 0 ? $hours.' h' : $hours.' h '.str_pad((string) $rest, 2, '0', STR_PAD_LEFT);
    };
    $school = array_merge([
        'display_name' => $establishment['name'] ?? '',
        'header_lines' => [],
        'logo_data_uri' => null,
        'stamp_data_uri' => null,
        'city' => null,
        'head_title' => 'Le Proviseur',
        'head_name' => null,
        'contact_line' => '',
    ], $establishment);
    $semesterLabel = match ($semester->name) {
        'S1' => '1er semestre',
        'S2' => '2e semestre',
        'S3' => '3e semestre',
        default => $semester->name,
    };
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bulletin — {{ $classe->name }} — {{ $semester->name }}</title>
    <style>
        @page { margin: 10mm 11mm 9mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #222; margin: 0; padding: 0; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        table { border-collapse: collapse; width: 100%; }
        .title { margin: 4px 0 5px; text-align: center; }
        .title h1 { margin: 0; font-size: 13px; letter-spacing: 1px; color: #1a3d7c; }
        .title .year { font-size: 9px; color: #333; margin-top: 1px; }
        .provisional { color: #b71c1c; font-weight: bold; font-size: 8.5px; margin-top: 2px; }
        .identity td { padding: 3px 6px; background: #f3f6fb; border: 1px solid #d9e1ee; }
        .identity strong { color: #1a3d7c; }
        .label { color: #555; }
        table.grades { margin-top: 5px; }
        table.grades th { background: #1a3d7c; color: #fff; padding: 3px 2px; font-size: 7.5px; border: 1px solid #1a3d7c; }
        table.grades td { padding: 2px 3px; border: 1px solid #c9d3e3; font-size: 8px; }
        table.grades td.num { text-align: center; }
        table.grades td.strong { font-weight: bold; }
        table.grades tr.totals td { background: #eef2f8; font-weight: bold; }
        .teacher { color: #666; font-size: 7px; }
        .summary { margin-top: 5px; }
        .summary td { vertical-align: top; padding: 3px 6px; border: 1px solid #d9e1ee; }
        .summary .big { font-size: 12px; font-weight: bold; color: #1a3d7c; }
        .box { margin-top: 5px; border: 1px solid #d9e1ee; padding: 4px 6px; }
        .box h3 { margin: 0 0 2px; font-size: 8px; color: #1a3d7c; text-transform: uppercase; }
        .decision { font-weight: bold; font-size: 10px; color: #1a3d7c; }
        .place { margin-top: 6px; text-align: right; font-size: 8.5px; }
        .signatures td { width: 50%; text-align: center; vertical-align: top; padding-top: 2px; height: 70px; }
        .signatures .role { font-weight: bold; color: #333; }
        .signatures .name { margin-top: 2px; font-size: 8.5px; }
        .dense table.grades th { font-size: 6.5px; padding: 2px 1px; }
        .dense table.grades td { font-size: 7px; padding: 1px 2px; }
        .dense table.grades td.appreciation { font-size: 6.3px; line-height: 1.15; }
        .dense .teacher { font-size: 6px; }
        .dense .identity td, .dense .summary td { padding: 2px 5px; }
        .dense .signatures td { height: 60px; }
        .generated { font-size: 7px; color: #999; text-align: right; margin-top: 2px; }
    </style>
</head>
<body>
@foreach ($reports as $report)
    @php
        $student = $report['student'];
        $absences = $report['absences'] ?? null;
        $headTeacherName = $report['classe']['head_teacher']['full_name'] ?? null;
        $appreciationLength = collect($report['subjects'])->sum(fn (array $row): int => mb_strlen((string) ($row['appreciation'] ?? '')));
        $isDense = count($report['subjects']) > 14 || $appreciationLength > 1100;
    @endphp
    <div class="page{{ $isDense ? ' dense' : '' }}">
        @include('settings::pdf.header', ['school' => $school, 'accent' => '#1a3d7c'])

        <div class="title">
            <h1>BULLETIN DE NOTES — {{ mb_strtoupper($semesterLabel) }}</h1>
            <div class="year">Année scolaire {{ $academicYear?->name ?? '' }}</div>
            @if ($isProvisional)
                <div class="provisional">Document provisoire (évaluations non publiées incluses)</div>
            @endif
        </div>

        <table class="identity">
            <tr>
                <td style="width:42%;"><span class="label">Nom et prénom(s) :</span> <strong>{{ $student['full_name'] }}</strong></td>
                <td style="width:26%;"><span class="label">Matricule :</span> {{ $student['matricule'] ?? '—' }}</td>
                <td style="width:32%;"><span class="label">Classe :</span> <strong>{{ $classe->name }}</strong></td>
            </tr>
            <tr>
                <td>
                    <span class="label">Né(e) le :</span>
                    {{ ! empty($student['birthdate']) ? \Illuminate\Support\Carbon::parse($student['birthdate'])->format('d/m/Y') : '—' }}
                    @if (! empty($student['birthplace']))
                        à {{ $student['birthplace'] }}
                    @endif
                </td>
                <td><span class="label">Sexe :</span> {{ $student['sex'] ?? '—' }}</td>
                <td><span class="label">Effectif :</span> {{ $report['class_size'] }} élèves</td>
            </tr>
            <tr>
                <td colspan="3"><span class="label">Professeur principal :</span> {{ $headTeacherName ?? '—' }}</td>
            </tr>
        </table>

        <table class="grades">
            <thead>
                <tr>
                    <th style="text-align:left;width:22%;">Matières / Professeurs</th>
                    <th style="width:6.5%;">Moy. /20</th>
                    <th style="width:5%;">Coef.</th>
                    <th style="width:7.5%;">Moy. × Coef.</th>
                    <th style="width:5.5%;">Rang</th>
                    <th style="width:6.5%;">Moy. classe</th>
                    <th style="width:6%;">+ forte</th>
                    <th style="width:6%;">+ faible</th>
                    <th style="text-align:left;">Appréciations</th>
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
                        <td class="num strong">{{ $formatNumber($row['average']) }}</td>
                        <td class="num">{{ $formatCoefficient($row['coefficient']) }}</td>
                        <td class="num">{{ $formatNumber($row['weighted_average']) }}</td>
                        <td class="num">{{ $formatRank($row['rank']) }}</td>
                        <td class="num">{{ $formatNumber($row['class_average']) }}</td>
                        <td class="num">{{ $formatNumber($row['highest'] ?? null) }}</td>
                        <td class="num">{{ $formatNumber($row['lowest'] ?? null) }}</td>
                        <td class="appreciation">{{ $row['appreciation'] ?? '' }}</td>
                    </tr>
                @endforeach
                <tr class="totals">
                    <td>Totaux</td>
                    <td class="num"></td>
                    <td class="num">{{ $formatCoefficient($report['totals']['coefficients']) }}</td>
                    <td class="num">{{ $formatNumber($report['totals']['weighted_points']) }}</td>
                    <td colspan="5"></td>
                </tr>
            </tbody>
        </table>

        <table class="summary">
            <tr>
                <td style="width:25%;">
                    <div class="label">Moyenne du semestre</div>
                    <div class="big">{{ $formatNumber($report['general_average']) }} / 20</div>
                </td>
                <td style="width:20%;">
                    <div class="label">Rang</div>
                    <div class="big">{{ $formatRank($report['rank']) }} / {{ $report['class_size'] }}</div>
                </td>
                <td style="width:25%;">
                    <div class="label">Mention</div>
                    <div class="big">{{ $report['mention'] ?? '—' }}</div>
                </td>
                <td style="width:30%;">
                    <div class="label">Distinction</div>
                    <div><strong>{{ $report['distinction'] ?? '—' }}</strong></div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="label">Moyenne de la classe :</span> <strong>{{ $formatNumber($report['class_stats']['class_average']) }}</strong><br>
                    <span class="label">Plus forte :</span> {{ $formatNumber($report['class_stats']['highest']) }}
                    &nbsp;·&nbsp; <span class="label">Plus faible :</span> {{ $formatNumber($report['class_stats']['lowest']) }}
                </td>
                <td colspan="2">
                    <span class="label">Absences du semestre :</span>
                    @if ($absences !== null)
                        <strong>{{ $formatDuration($absences['justified_minutes'] + $absences['unjustified_minutes']) }}</strong><br>
                        <span class="label">Justifiées :</span> {{ $formatDuration($absences['justified_minutes']) }}
                        &nbsp;·&nbsp; <span class="label">Non justifiées :</span> {{ $formatDuration($absences['unjustified_minutes']) }}
                        &nbsp;·&nbsp; <span class="label">Retards :</span> {{ $absences['late_count'] }}
                    @else
                        —
                    @endif
                </td>
            </tr>
            @if ($report['annual'])
                <tr>
                    <td colspan="2">
                        <span class="label">Moyenne annuelle :</span>
                        <span class="big">{{ $formatNumber($report['annual']['average']) }} / 20</span>
                    </td>
                    <td colspan="2">
                        <span class="label">Rang annuel :</span>
                        <span class="big">{{ $formatRank($report['annual']['rank']) }} / {{ $report['class_size'] }}</span>
                    </td>
                </tr>
            @endif
        </table>

        <div class="box">
            <h3>Appréciation du conseil de classe</h3>
            <div style="min-height:22px;">{{ $report['general_appreciation'] ?? '' }}</div>
            @if ($report['annual'])
                <div style="margin-top:3px;">
                    <span class="label">Décision du conseil de fin d'année :</span>
                    <span class="decision">{{ $report['council_decision'] ?? '……………………………………' }}</span>
                </div>
            @endif
        </div>

        <div class="place">
            Fait à {{ $school['city'] ?: '……………………' }}, le {{ $generatedAt->format('d/m/Y') }}
        </div>

        <table class="signatures">
            <tr>
                <td>
                    <div class="role">Le Professeur principal</div>
                    <div style="height:42px;"></div>
                    <div class="name">{{ $headTeacherName ?? '' }}</div>
                </td>
                <td>
                    <div class="role">{{ $school['head_title'] ?: 'Le Proviseur' }}</div>
                    <div style="height:42px;">
                        @if (! empty($school['stamp_data_uri']))
                            <img src="{{ $school['stamp_data_uri'] }}" alt="" style="max-height:42px;max-width:120px;">
                        @endif
                    </div>
                    <div class="name">{{ $school['head_name'] ?? '' }}</div>
                </td>
            </tr>
        </table>

        @include('settings::pdf.footer', ['school' => $school, 'extra' => 'Il ne sera délivré aucun duplicata de ce bulletin.'])
        <div class="generated">Édité le {{ $generatedAt->format('d/m/Y à H:i') }}</div>
    </div>
@endforeach
</body>
</html>
