<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} — Vorschau</title>
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
        ul, ol { margin: 0 0 8px; padding-left: 20px; }
        li { margin-bottom: 4px; }
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
        strong { font-weight: 700; }
        em { font-style: italic; }
        .header {
            border-bottom: 2px solid #2563eb;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .watermark {
            position: fixed;
            top: 45%;
            left: 15%;
            font-size: 60pt;
            color: rgba(220, 38, 38, 0.08);
            transform: rotate(-35deg);
            font-weight: 900;
            letter-spacing: 8px;
            z-index: -1;
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
    </style>
</head>
<body>
    <div class="watermark">VORSCHAU</div>

    @if($headerHtml)
        <div class="header">{!! $headerHtml !!}</div>
    @endif

    {!! $content !!}

    <div class="footer">
        VORSCHAU — {{ $title }} &middot; Erstellt mit ROSI &middot; {{ now()->format('d.m.Y H:i') }}
    </div>
</body>
</html>
