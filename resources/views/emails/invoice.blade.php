<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rechnung {{ $invoice->invoice_number }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f0f2f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f0f2f5; padding: 40px 20px;">
        <tr>
            <td align="center">

                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">

                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #1e3a5f 0%, #2d5f8a 100%); padding: 32px 40px;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 26px; font-weight: 700; letter-spacing: -0.5px;">
                                Rechnung {{ $invoice->invoice_number }}
                            </h1>
                            <p style="margin: 8px 0 0; color: rgba(255,255,255,0.8); font-size: 14px;">
                                {{ $stationName }}@if($stationCity) &middot; {{ $stationCity }}@endif
                            </p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 36px 40px;">

                            <p style="margin: 0 0 20px; color: #1a1a1a; font-size: 15px; line-height: 1.6;">
                                Sehr geehrte Damen und Herren,
                            </p>

                            <p style="margin: 0 0 28px; color: #1a1a1a; font-size: 15px; line-height: 1.6;">
                                anbei erhalten Sie die Rechnung f&uuml;r die
                                <strong>{{ $stationName }}</strong>@if($stationAddress), {{ $stationAddress }}@endif.
                            </p>

                            <!-- Rechnungsdetails -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fb; border-radius: 10px; border: 1px solid #e8ebef; margin-bottom: 28px;">
                                <tr>
                                    <td style="padding: 24px 28px;">

                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 14px;">
                                            <tr>
                                                <td style="color: #6b7280; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; padding-bottom: 4px;" width="45%">Rechnungsnummer</td>
                                                <td style="color: #1a1a1a; font-size: 15px; font-weight: 600; padding-bottom: 4px;">{{ $invoice->invoice_number }}</td>
                                            </tr>
                                        </table>

                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 14px;">
                                            <tr><td style="border-bottom: 1px solid #e8ebef;"></td></tr>
                                        </table>

                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 14px;">
                                            <tr>
                                                <td style="color: #6b7280; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; padding-bottom: 4px;" width="45%">Rechnungsdatum</td>
                                                <td style="color: #1a1a1a; font-size: 15px; padding-bottom: 4px;">
                                                    @if($invoice->invoice_date instanceof \Carbon\Carbon && $invoice->invoice_date->year > 1971)
                                                        {{ $invoice->invoice_date->format('d.m.Y') }}
                                                    @else
                                                        &ndash;
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>

                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 14px;">
                                            <tr><td style="border-bottom: 1px solid #e8ebef;"></td></tr>
                                        </table>

                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 14px;">
                                            <tr>
                                                <td style="color: #6b7280; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; padding-bottom: 4px;" width="45%">Tankstelle</td>
                                                <td style="color: #1a1a1a; font-size: 15px; padding-bottom: 4px;">
                                                    {{ $stationName }}
                                                    @if($stationAddress)
                                                        <br><span style="color: #6b7280; font-size: 13px;">{{ $stationAddress }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>

                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 14px;">
                                            <tr><td style="border-bottom: 1px solid #e8ebef;"></td></tr>
                                        </table>

                                        @if($showAmount ?? true)
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="color: #6b7280; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;" width="45%">Gesamtbetrag</td>
                                                <td style="color: #1e3a5f; font-size: 22px; font-weight: 700;">{{ number_format($invoice->amount, 2, ',', '.') }} &euro;</td>
                                            </tr>
                                        </table>
                                        @endif

                                    </td>
                                </tr>
                            </table>

                            <!-- PDF Hinweis -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #eef6ff; border-radius: 8px; border-left: 4px solid #2d5f8a; margin-bottom: 28px;">
                                <tr>
                                    <td style="padding: 16px 20px;">
                                        <p style="margin: 0; color: #1e3a5f; font-size: 14px; line-height: 1.5;">
                                            &#x1F4CE; Die Rechnung finden Sie als <strong>PDF-Anhang</strong> zu dieser E-Mail.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0 0 24px; color: #4b5563; font-size: 14px; line-height: 1.6;">
                                Bei Fragen stehen wir Ihnen gerne zur Verf&uuml;gung.
                            </p>

                            <p style="margin: 0; color: #1a1a1a; font-size: 15px; line-height: 1.6;">
                                Mit freundlichen Gr&uuml;&szlig;en<br>
                                <strong>{{ $stationName }}</strong>
                            </p>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fb; padding: 20px 40px; border-top: 1px solid #e8ebef;">
                            <p style="margin: 0; color: #9ca3af; font-size: 12px; line-height: 1.5; text-align: center;">
                                Dies ist eine automatisch generierte E-Mail. Bitte antworten Sie nicht direkt auf diese E-Mail.
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>
