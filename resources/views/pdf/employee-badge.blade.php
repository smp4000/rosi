<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 10mm;
            size: A4;
        }

        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            color: #080000;
        }

        .page-hint {
            font-size: 9px;
            color: #aaa;
            text-align: center;
            margin-bottom: 8mm;
        }

        /* Durable 813907: 90mm x 60mm */
        .badge {
            width: 90mm;
            height: 60mm;
            border: 0.5pt solid #0c0303;
            overflow: hidden;
            display: inline-block;
            vertical-align: top;
            margin: 0 2mm 3mm 0;
            position: relative;
            box-sizing: border-box;
        }

        .badge-inner {
            padding: 3mm 4mm 2.5mm 4mm;
            width: 100%;
            height: 100%;
            box-sizing: border-box;
        }

        /* ── Header ── */
        .badge-top {
            width: 100%;
            height: 14mm;
            border-bottom: 1.2pt solid #1565C0;
            padding-bottom: 1.5mm;
            margin-bottom: 1mm;
            position: relative;
        }

        .badge-top-left {
            position: absolute;
            left: 0;
            top: 0;
            width: 60%;
        }

        .badge-top-right {
            position: absolute;
            right: 6mm;
            top: 0;
            width: 35%;
            text-align: right;
        }

        .tenant-name {
            font-size: 8px;
            color: #888;
            margin: 0;
            line-height: 1.2;
        }

        .station-name {
            font-size: 12px;
            font-weight: bold;
            color: #111;
            margin: 1mm 0 0 0;
            line-height: 1.2;
            letter-spacing: 0.3px;
        }

        .brand-logo {
            max-height: 13mm;
            max-width: 25mm;
            object-fit: contain;
        }

        .brand-text {
            font-size: 13px;
            font-weight: bold;
            color: #1565C0;
        }

        /* ── Body ── */
        .badge-body {
            width: 100%;
            height: 36mm;
            position: relative;
        }

        .badge-left {
            position: absolute;
            left: 0;
            top: 0;
            width: 52%;
        }

        .badge-right {
            position: absolute;
            right: 0;
            top: 0;
            width: 44%;
            text-align: center;
        }

        .employee-name {
            font-size: 16px;
            font-weight: bold;
            color: #000;
            margin: 1.5mm 0 0.5mm 0;
            line-height: 1.15;
            letter-spacing: 0.2px;
        }

        .employee-role {
            font-size: 8px;
            color: #1565C0;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0 0 2mm 0;
        }

        .station-address {
            font-size: 8px;
            color: #555;
            line-height: 1.4;
            margin: 0;
            font-weight: 600;
        }

        .extra-stations-label {
            font-size: 6.5px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 1.5mm;
        }

        .extra-stations {
            font-size: 7.5px;
            color: #555;
            line-height: 1.4;
            font-weight: 600;
        }

        .qr-image {
            width: 27mm;
            height: 27mm;
        }

        .scan-code {
            font-size: 8px;
            font-weight: bold;
            color: #333;
            letter-spacing: 2px;
            margin-top: 1mm;
        }

        .scan-hint {
            font-size: 6px;
            color: #999;
            margin-top: 0.3mm;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ── Footer ── */
        .badge-footer {
            position: absolute;
            bottom: 2mm;
            left: 4mm;
            right: 4mm;
            font-size: 5.5px;
            color: #0a0000;
            border-top: 0.4pt solid #413e3e;
            padding-top: 0.8mm;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="page-hint">
        Durable Namensschilder 813907 — 90 &times; 60 mm — Bitte an den Linien ausschneiden
    </div>

    @foreach($badges as $badge)
        <div class="badge">
            <div class="badge-inner">
                {{-- Header --}}
                <div class="badge-top">
                    <div class="badge-top-left">
                        <div class="tenant-name">{{ $badge['tenant_name'] }}</div>
                        <div class="station-name">{{ $badge['station_name'] }}</div>
                    </div>
                    <div class="badge-top-right">
                        @if($badge['brand_logo'])
                            <img src="{{ $badge['brand_logo'] }}" class="brand-logo" alt="{{ $badge['brand_name'] }}">
                        @elseif($badge['brand_name'])
                            <span class="brand-text">{{ $badge['brand_name'] }}</span>
                        @endif
                    </div>
                </div>

                {{-- Body --}}
                <div class="badge-body">
                    <div class="badge-left">
                        <div class="employee-name">{{ $badge['employee_name'] }}</div>
                        <div class="employee-role">Mitarbeiter</div>

                        @if($badge['station_address'])
                            <div class="station-address">{{ $badge['station_address'] }}</div>
                        @endif

                        @if(count($badge['all_stations']) > 1)
                            <div class="extra-stations-label">Weitere Stationen:</div>
                            <div class="extra-stations">
                                @foreach($badge['all_stations'] as $s)
                                    @if($s !== $badge['station_name'])
                                        {{ $s }}<br>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="badge-right">
                        <img src="data:image/svg+xml;base64,{{ $badge['qr_base64'] }}" class="qr-image" alt="QR">
                        <div class="scan-code">{{ $badge['scan_code'] }}</div>
                        <div class="scan-hint">POS Login-Code</div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="badge-footer">
                    ROSI POS &mdash; Bei Verlust bitte Vorgesetzten informieren &mdash; {{ now()->format('d.m.Y') }}
                </div>
            </div>
        </div>

        @if(!$loop->last && $loop->iteration % 2 == 0)
            <br>
        @endif
    @endforeach
</body>
</html>
