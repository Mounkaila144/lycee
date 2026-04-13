<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Carte Étudiant - {{ $student->matricule }}</title>
    <style>
        @page {
            size: 85.6mm 53.98mm;
            margin: 0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 8pt;
            line-height: 1.3;
            margin: 0;
            padding: 0;
        }

        .card {
            width: 85.6mm;
            height: 53.98mm;
            background-color: #1e3a5f;
            position: relative;
            overflow: hidden;
        }

        /* Header */
        .card-header {
            background-color: #0d2137;
            padding: 2.5mm 3mm;
            border-bottom: 0.8mm solid #ffc107;
        }

        .institution-name {
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5pt;
            color: #ffffff;
            margin: 0;
        }

        .card-title {
            font-size: 9pt;
            font-weight: bold;
            color: #ffc107;
            margin-top: 1mm;
            letter-spacing: 1pt;
        }

        /* Body */
        .card-body {
            padding: 2.5mm 3mm;
        }

        .main-table {
            width: 100%;
            border-collapse: collapse;
        }

        .main-table td {
            vertical-align: top;
            padding: 0;
        }

        /* Photo */
        .photo-cell {
            width: 18mm;
            padding-right: 2.5mm;
        }

        .photo-container {
            width: 18mm;
            height: 22mm;
            border: 0.5mm solid #ffffff;
            background-color: #e8e8e8;
            text-align: center;
            overflow: hidden;
        }

        .photo-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-placeholder {
            color: #666666;
            font-size: 6pt;
            padding-top: 8mm;
        }

        /* Info */
        .info-cell {
            padding-right: 2mm;
        }

        .student-name {
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #ffffff;
            margin-bottom: 1.5mm;
            letter-spacing: 0.3pt;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 0.3mm 0;
            vertical-align: top;
        }

        .info-label {
            font-size: 6pt;
            color: #a0c4e8;
            width: 16mm;
            text-transform: uppercase;
        }

        .info-value {
            font-size: 7pt;
            font-weight: bold;
            color: #ffffff;
        }

        /* QR Code */
        .qr-cell {
            width: 20mm;
            text-align: center;
            vertical-align: middle;
        }

        .qr-container {
            width: 18mm;
            height: 18mm;
            background-color: #ffffff;
            padding: 1mm;
            margin: 0 auto;
        }

        .qr-container img {
            width: 100%;
            height: 100%;
        }

        .scan-text {
            font-size: 5pt;
            color: #a0c4e8;
            margin-top: 1mm;
            text-align: center;
        }

        /* Footer */
        .card-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #0d2137;
            padding: 1.5mm 3mm;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .footer-table td {
            padding: 0;
            vertical-align: middle;
        }

        .card-number {
            font-size: 6pt;
            color: #a0c4e8;
            font-family: 'DejaVu Sans Mono', monospace;
        }

        .validity {
            font-size: 6pt;
            color: #ffc107;
            text-align: right;
            font-weight: bold;
        }

        /* Barcode */
        .barcode-container {
            margin-top: 1.5mm;
            text-align: left;
        }

        .barcode-container img {
            height: 5mm;
        }

        /* Decorative elements */
        .accent-line {
            position: absolute;
            top: 0;
            right: 0;
            width: 3mm;
            height: 100%;
            background-color: #ffc107;
        }

        .corner-decoration {
            position: absolute;
            bottom: 8mm;
            right: 3mm;
            width: 8mm;
            height: 8mm;
            border: 0.3mm solid rgba(255, 193, 7, 0.3);
            border-radius: 50%;
        }
    </style>
</head>
<body>
    <div class="card">
        {{-- Decorative accent line --}}
        <div class="accent-line"></div>

        {{-- Header --}}
        <div class="card-header">
            <div class="institution-name">{{ $institutionName ?? 'Université / Institut' }}</div>
            <div class="card-title">CARTE D'ÉTUDIANT</div>
        </div>

        {{-- Body --}}
        <div class="card-body">
            <table class="main-table">
                <tr>
                    {{-- Photo Section --}}
                    <td class="photo-cell">
                        <div class="photo-container">
                            @if($student->photo_url)
                                <img src="{{ $student->photo_url }}" alt="Photo">
                            @else
                                <div class="photo-placeholder">PHOTO</div>
                            @endif
                        </div>
                    </td>

                    {{-- Info Section --}}
                    <td class="info-cell">
                        <div class="student-name">{{ strtoupper($student->lastname) }} {{ $student->firstname }}</div>

                        <table class="info-table">
                            <tr>
                                <td class="info-label">Matricule</td>
                                <td class="info-value">{{ $student->matricule }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Formation</td>
                                <td class="info-value">{{ $student->program?->code ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Niveau</td>
                                <td class="info-value">{{ $student->level ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td class="info-label">Année</td>
                                <td class="info-value">{{ $academicYear?->name ?? date('Y') }}</td>
                            </tr>
                        </table>

                        {{-- Barcode --}}
                        @if($barcode)
                        <div class="barcode-container">
                            <img src="data:image/png;base64,{{ $barcode }}" alt="Barcode">
                        </div>
                        @endif
                    </td>

                    {{-- QR Code Section --}}
                    <td class="qr-cell">
                        <div class="qr-container">
                            @if($qrCode)
                                <img src="data:image/png;base64,{{ $qrCode }}" alt="QR Code">
                            @else
                                <div style="padding-top: 6mm; font-size: 6pt; color: #666;">QR</div>
                            @endif
                        </div>
                        <div class="scan-text">Scanner pour vérifier</div>
                    </td>
                </tr>
            </table>
        </div>

        {{-- Footer --}}
        <div class="card-footer">
            <table class="footer-table">
                <tr>
                    <td class="card-number">N° {{ $card->card_number }}</td>
                    <td class="validity">Valide jusqu'au {{ $card->valid_until->format('d/m/Y') }}</td>
                </tr>
            </table>
        </div>

        {{-- Corner decoration --}}
        <div class="corner-decoration"></div>
    </div>
</body>
</html>
