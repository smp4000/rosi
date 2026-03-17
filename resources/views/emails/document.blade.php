<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dokument: {{ $document->title }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; color: #333;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08);">
        {{-- Header --}}
        <tr>
            <td style="background: linear-gradient(135deg, #2563eb, #7c3aed); padding: 32px 40px; border-radius: 12px 12px 0 0; text-align: center;">
                <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700;">ROSI</h1>
                <p style="color: rgba(255,255,255,0.85); margin: 4px 0 0; font-size: 14px;">Dokument</p>
            </td>
        </tr>

        {{-- Body --}}
        <tr>
            <td style="padding: 40px;">
                <h2 style="margin: 0 0 16px; font-size: 20px; color: #1e293b;">{{ $document->title }}</h2>

                <p style="margin: 0 0 16px; line-height: 1.6; color: #475569;">
                    Sehr geehrte/r {{ $employeeName }},
                </p>

                <p style="margin: 0 0 24px; line-height: 1.6; color: #475569;">
                    im Anhang finden Sie ein Dokument. Bitte lesen Sie es sorgfaeltig durch.
                    @if($document->requires_signature)
                        <strong>Dieses Dokument erfordert Ihre Unterschrift.</strong>
                    @endif
                </p>

                {{-- Signatur-Button --}}
                @if($document->requires_signature && $document->signature_token)
                    <div style="text-align: center; margin: 32px 0;">
                        <a href="{{ url('/dokument/unterschreiben/' . $document->signature_token) }}"
                           style="display: inline-block; background: linear-gradient(135deg, #2563eb, #7c3aed); color: #ffffff; padding: 16px 40px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 16px; letter-spacing: 0.5px;">
                            ✍️ Jetzt unterschreiben
                        </a>
                    </div>
                    <p style="margin: 0 0 24px; line-height: 1.6; color: #94a3b8; font-size: 13px; text-align: center;">
                        Oder kopieren Sie diesen Link in Ihren Browser:<br>
                        <span style="color: #2563eb; word-break: break-all;">{{ url('/dokument/unterschreiben/' . $document->signature_token) }}</span>
                    </p>
                    @if($document->signature_token_expires_at)
                        <p style="margin: 0 0 24px; line-height: 1.6; color: #f59e0b; font-size: 13px; text-align: center;">
                            ⏰ Gueltig bis: {{ $document->signature_token_expires_at->format('d.m.Y') }}
                        </p>
                    @endif
                @endif

                <div style="background: #f8fafc; padding: 16px 20px; border-radius: 8px; margin: 0 0 24px;">
                    <table style="width: 100%; font-size: 14px; color: #475569;">
                        <tr>
                            <td style="padding: 4px 0; font-weight: 600;">Dokumenttyp:</td>
                            <td style="padding: 4px 0;">{{ $document->type }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 4px 0; font-weight: 600;">Erstellt am:</td>
                            <td style="padding: 4px 0;">{{ $document->created_at->format('d.m.Y') }}</td>
                        </tr>
                        @if($document->requires_signature)
                        <tr>
                            <td style="padding: 4px 0; font-weight: 600;">Unterschrift:</td>
                            <td style="padding: 4px 0; color: #f59e0b; font-weight: 600;">Erforderlich</td>
                        </tr>
                        @endif
                    </table>
                </div>

                <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;">

                <p style="margin: 0; font-size: 12px; color: #94a3b8;">
                    Bei Fragen wenden Sie sich bitte an Ihren Arbeitgeber.
                </p>
            </td>
        </tr>

        {{-- Footer --}}
        <tr>
            <td style="padding: 20px 40px; background: #f8fafc; border-radius: 0 0 12px 12px; text-align: center;">
                <p style="margin: 0; font-size: 12px; color: #94a3b8;">
                    &copy; {{ date('Y') }} ROSI &middot; Tankstellen-Partner-Plattform
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
