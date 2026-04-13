<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Comparaison des Coefficients - {{ $level->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1 { text-align: center; font-size: 16px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #333; padding: 6px 8px; text-align: center; }
        th { background-color: #f0f0f0; font-weight: bold; }
        .subject-name { text-align: left; }
        .total-row { font-weight: bold; background-color: #e8e8e8; }
        .empty { color: #999; }
    </style>
</head>
<body>
    <h1>Comparaison des Coefficients - {{ $level->name }}</h1>

    <table>
        <thead>
            <tr>
                <th class="subject-name">Code</th>
                <th class="subject-name">Matière</th>
                @foreach($series as $seriesCode)
                    <th>Série {{ $seriesCode }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($subjects as $subject)
                <tr>
                    <td>{{ $subject['code'] }}</td>
                    <td class="subject-name">{{ $subject['name'] }}</td>
                    @foreach($series as $seriesCode)
                        <td @if($subject['coefficients'][$seriesCode] === null) class="empty" @endif>
                            {{ $subject['coefficients'][$seriesCode] ?? '-' }}
                        </td>
                    @endforeach
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="2">Total</td>
                @foreach($series as $seriesCode)
                    <td>{{ $totals[$seriesCode] }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>
</body>
</html>
