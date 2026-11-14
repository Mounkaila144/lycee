{{--
    En-tête officiel commun des documents PDF (bulletins, attestations, factures…).
    Variables : $school (SchoolProfileService::forDocuments()), $accent (couleur, optionnelle),
    $size ('normal' pour les bulletins, 'large' pour les attestations).
--}}
@php
    $accent = $accent ?? '#1a3d7c';
    $isLarge = ($size ?? 'normal') === 'large';
    $baseFont = $isLarge ? '10.5px' : '8.5px';
    $firstLineFont = $isLarge ? '12px' : '9.5px';
    $nameFont = $isLarge ? '16px' : '12px';
    $logoHeight = $isLarge ? '80px' : '62px';
    $headerLines = $school['header_lines'] ?? [];
@endphp
<table style="width:100%;border-collapse:collapse;border-bottom:2px solid {{ $accent }};margin-bottom:6px;">
    <tr>
        <td style="width:37%;vertical-align:top;text-align:center;font-size:{{ $baseFont }};line-height:1.35;color:#222;padding:0 4px 5px 0;">
            @foreach ($headerLines as $index => $line)
                <div style="{{ $index === 0 ? 'font-weight:bold;font-size:'.$firstLineFont.';' : '' }}{{ $index === 1 ? 'font-style:italic;' : '' }}">{{ $line }}</div>
                @if ($index === 1 || ($index === 0 && count($headerLines) === 1))
                    <div style="font-size:7px;letter-spacing:1px;">——————</div>
                @endif
            @endforeach
            @if (! empty($school['region']))
                <div>{{ $school['region'] }}</div>
            @endif
            @if (! empty($school['inspection']))
                <div>{{ $school['inspection'] }}</div>
            @endif
        </td>
        <td style="width:20%;vertical-align:middle;text-align:center;padding:0 2px 5px;">
            @if (! empty($school['logo_data_uri']))
                <img src="{{ $school['logo_data_uri'] }}" alt="" style="max-height:{{ $logoHeight }};max-width:110px;">
            @endif
        </td>
        <td style="width:43%;vertical-align:top;text-align:center;font-size:{{ $baseFont }};line-height:1.35;color:#222;padding:0 0 5px 4px;">
            <div style="font-size:{{ $nameFont }};font-weight:bold;color:{{ $accent }};text-transform:uppercase;">{{ $school['display_name'] }}</div>
            @if (! empty($school['short_name']))
                <div style="font-weight:bold;">({{ $school['short_name'] }})</div>
            @endif
            @if (! empty($school['motto']))
                <div style="font-style:italic;">« {{ $school['motto'] }} »</div>
            @endif
            @if (! empty($school['po_box_label']) || ! empty($school['city']))
                <div>{{ implode(' – ', array_filter([$school['po_box_label'] ?? null, $school['city'] ?? null])) }}</div>
            @endif
            @if (! empty($school['phones']))
                <div>Tél. : {{ $school['phones'] }}</div>
            @endif
            @if (! empty($school['email']))
                <div>{{ $school['email'] }}</div>
            @endif
            @if (! empty($school['authorization_number']))
                <div style="font-size:7.5px;color:#555;">Autorisation : {{ $school['authorization_number'] }}</div>
            @endif
        </td>
    </tr>
</table>
