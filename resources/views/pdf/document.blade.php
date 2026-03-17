<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $document->title }}</title>
    <style>
        @page {
            margin: 25mm 20mm 30mm 20mm;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #1e293b;
        }
        h1 { font-size: 18pt; margin-bottom: 10px; color: #0f172a; }
        h2 { font-size: 14pt; margin-top: 20px; margin-bottom: 8px; color: #1e293b; }
        h3 { font-size: 12pt; margin-top: 16px; margin-bottom: 6px; }
        p { margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        table th, table td {
            border: 1px solid #cbd5e1;
            padding: 6px 10px;
            text-align: left;
            font-size: 10pt;
        }
        table th {
            background-color: #f1f5f9;
            font-weight: 600;
        }
        .header {
            border-bottom: 2px solid #2563eb;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .header .company-name {
            font-size: 14pt;
            font-weight: 700;
            color: #2563eb;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8pt;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }
        .signature-block {
            margin-top: 60px;
            page-break-inside: avoid;
        }
        .signature-line {
            border-bottom: 1px solid #1e293b;
            width: 250px;
            display: inline-block;
            margin-top: 40px;
        }
        .signature-label {
            font-size: 9pt;
            color: #64748b;
            margin-top: 4px;
        }
        .signature-image {
            max-height: 60px;
            margin-bottom: -10px;
        }
        .meta-info {
            font-size: 9pt;
            color: #64748b;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    {{-- Dokumenten-Inhalt (aus Template generiert) --}}
    {!! $content !!}

    {{-- Signatur-Block --}}
    @if($document->requires_signature)
        <div class="signature-block">
            <table style="border: none; width: 100%;">
                <tr>
                    <td style="border: none; width: 50%; vertical-align: bottom;">
                        @if($document->signature_data)
                            <img src="{{ $document->signature_data }}" class="signature-image" alt="Unterschrift">
                            <br>
                        @endif
                        <div class="signature-line"></div>
                        <div class="signature-label">
                            {{ $document->signed_by_name ?? 'Arbeitnehmer/in' }}
                            @if($document->signed_at)
                                <br>{{ $document->signed_at->format('d.m.Y') }}
                            @endif
                        </div>
                    </td>
                    <td style="border: none; width: 50%; vertical-align: bottom;">
                        @if($document->counter_signature_data)
                            <img src="{{ $document->counter_signature_data }}" class="signature-image" alt="Gegenzeichnung">
                            <br>
                        @endif
                        <div class="signature-line"></div>
                        <div class="signature-label">
                            Arbeitgeber
                            @if($document->counter_signed_at)
                                <br>{{ $document->counter_signed_at->format('d.m.Y') }}
                            @endif
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    @endif

    {{-- Footer --}}
    <div class="footer">
        Erstellt mit ROSI &middot; {{ now()->format('d.m.Y H:i') }} &middot; Seite <span class="page-number"></span>
    </div>
</body>
</html>
