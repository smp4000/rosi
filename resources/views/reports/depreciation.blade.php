<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 100px 35px 60px 35px; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #222; }

        header { position: fixed; top: -80px; left: 0; right: 0; height: 70px; }
        footer {
            position: fixed; bottom: -40px; left: 0; right: 0; height: 30px;
            font-size: 8px; color: #888; border-top: 1px solid #ddd; padding-top: 4px;
        }
        .page-num:after { content: counter(page) " / " counter(pages); }

        .head-title { font-size: 18px; font-weight: bold; color: #1565C0; }
        .head-sub { font-size: 10px; color: #555; margin-top: 2px; }
        .station-box { font-size: 9px; color: #444; text-align: right; }

        .group { margin-bottom: 14px; page-break-inside: avoid; }
        .group-title {
            background: #1565C0; color: #fff; padding: 5px 8px;
            font-size: 11px; font-weight: bold; border-radius: 3px 3px 0 0;
        }

        table.items { width: 100%; border-collapse: collapse; }
        table.items th {
            background: #ECEFF1; text-align: left; padding: 4px 6px;
            font-size: 8px; text-transform: uppercase; color: #555;
            border-bottom: 1px solid #ccc;
        }
        table.items td { padding: 4px 6px; border-bottom: 1px solid #eee; }
        table.items tr:nth-child(even) td { background: #FAFAFA; }
        .num { text-align: right; }
        .group-sum td { font-weight: bold; background: #E3F2FD !important; border-top: 2px solid #1565C0; }

        .summary { margin-top: 24px; page-break-inside: avoid; }
        .summary-title { font-size: 13px; font-weight: bold; color: #1565C0; margin-bottom: 6px; }
        table.summary-table { width: 100%; border-collapse: collapse; }
        table.summary-table td { padding: 5px 8px; border-bottom: 1px solid #eee; }
        .total-row td { font-size: 12px; font-weight: bold; background: #1565C0; color: #fff; }
    </style>
</head>
<body>
    <header>
        <table style="width:100%;">
            <tr>
                <td style="vertical-align:top;">
                    <div class="head-title">Abschriften-Tagesbericht</div>
                    <div class="head-sub">
                        Zeitraum:
                        {{ $from->format('d.m.Y') }}@if(! $from->isSameDay($to)) – {{ $to->format('d.m.Y') }}@endif
                    </div>
                </td>
                <td class="station-box" style="vertical-align:top;">
                    <strong>{{ $stationName }}</strong><br>
                    @if($station)
                        @if($station->contact_first_name || $station->contact_last_name)
                            {{ trim(($station->contact_first_name ?? '') . ' ' . ($station->contact_last_name ?? '')) }}<br>
                        @endif
                        {{ trim(($station->street ?? '') . ' ' . ($station->house_number ?? '')) }}<br>
                        {{ trim(($station->zip ?? '') . ' ' . ($station->city ?? '')) }}
                    @endif
                </td>
            </tr>
        </table>
    </header>

    <footer>
        <table style="width:100%;">
            <tr>
                <td>ROSI – Abschriften-Tagesbericht</td>
                <td style="text-align:center;">Erstellt am {{ $generatedAt->format('d.m.Y H:i') }} Uhr</td>
                <td style="text-align:right;">Seite <span class="page-num"></span></td>
            </tr>
        </table>
    </footer>

    <main>
        @forelse($groups as $group)
            <div class="group">
                <div class="group-title">{{ $group['name'] }} ({{ $group['qty'] }} Stück)</div>
                <table class="items">
                    <thead>
                        <tr>
                            <th style="width:18%;">EAN</th>
                            <th style="width:10%;">Art-Nr</th>
                            <th>Artikel</th>
                            <th class="num" style="width:7%;">Menge</th>
                            <th class="num" style="width:9%;">EK</th>
                            <th class="num" style="width:9%;">VK</th>
                            <th class="num" style="width:11%;">Gesamt-EK</th>
                            <th class="num" style="width:11%;">Gesamt-VK</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($group['items'] as $item)
                            <tr>
                                <td>{{ $item['ean'] ?? '—' }}</td>
                                <td>{{ $item['tms_no'] ?? '—' }}</td>
                                <td>{{ $item['description'] }}</td>
                                <td class="num">{{ $item['quantity'] }}</td>
                                <td class="num">{{ number_format($item['ek'], 3, ',', '.') }} €</td>
                                <td class="num">{{ number_format($item['vk'], 2, ',', '.') }} €</td>
                                <td class="num">{{ number_format($item['sum_ek'], 2, ',', '.') }} €</td>
                                <td class="num">{{ number_format($item['sum_vk'], 2, ',', '.') }} €</td>
                            </tr>
                        @endforeach
                        <tr class="group-sum">
                            <td colspan="6">Summe „{{ $group['name'] }}"</td>
                            <td class="num">{{ number_format($group['sum_ek'], 2, ',', '.') }} €</td>
                            <td class="num">{{ number_format($group['sum_vk'], 2, ',', '.') }} €</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @empty
            <p style="text-align:center; color:#888; margin-top:40px;">
                Keine Abschriften im gewählten Zeitraum.
            </p>
        @endforelse

        @if(count($groups) > 0)
            <div class="summary">
                <div class="summary-title">Zusammenfassung</div>
                <table class="summary-table">
                    @foreach($groups as $group)
                        <tr>
                            <td>{{ $group['name'] }}</td>
                            <td class="num">{{ $group['qty'] }} Stück</td>
                            <td class="num">EK {{ number_format($group['sum_ek'], 2, ',', '.') }} €</td>
                            <td class="num">VK {{ number_format($group['sum_vk'], 2, ',', '.') }} €</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td>GESAMT</td>
                        <td class="num">{{ $totalQty }} Stück</td>
                        <td class="num">EK {{ number_format($totalEk, 2, ',', '.') }} €</td>
                        <td class="num">VK {{ number_format($totalVk, 2, ',', '.') }} €</td>
                    </tr>
                </table>
            </div>
        @endif
    </main>
</body>
</html>
