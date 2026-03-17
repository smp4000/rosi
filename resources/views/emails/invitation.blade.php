<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Einladung zur Mitarbeiter-Registrierung</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; color: #333;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08);">
        {{-- Header --}}
        <tr>
            <td style="background: linear-gradient(135deg, #2563eb, #7c3aed); padding: 32px 40px; border-radius: 12px 12px 0 0; text-align: center;">
                <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700;">ROSI</h1>
                <p style="color: rgba(255,255,255,0.85); margin: 4px 0 0; font-size: 14px;">Tankstellen-Partner-Plattform</p>
            </td>
        </tr>

        {{-- Body --}}
        <tr>
            <td style="padding: 40px;">
                <h2 style="margin: 0 0 16px; font-size: 20px; color: #1e293b;">Willkommen bei {{ $companyName }}!</h2>

                <p style="margin: 0 0 16px; line-height: 1.6; color: #475569;">
                    {{ $inviterName }} hat Sie eingeladen, sich als Mitarbeiter zu registrieren.
                    Bitte fuellen Sie den Personalbogen aus, um Ihren Account zu erstellen.
                </p>

                @if($personalMessage)
                    <div style="background: #f8fafc; border-left: 4px solid #2563eb; padding: 16px 20px; margin: 0 0 24px; border-radius: 0 8px 8px 0;">
                        <p style="margin: 0; font-style: italic; color: #64748b;">{{ $personalMessage }}</p>
                    </div>
                @endif

                {{-- CTA Button --}}
                <div style="text-align: center; margin: 32px 0;">
                    <a href="{{ $onboardingUrl }}"
                       style="display: inline-block; background: linear-gradient(135deg, #2563eb, #7c3aed); color: #ffffff; padding: 14px 36px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 16px; box-shadow: 0 4px 12px rgba(37,99,235,0.3);">
                        Jetzt registrieren
                    </a>
                </div>

                <p style="margin: 0 0 8px; font-size: 13px; color: #94a3b8;">
                    Dieser Link ist gueltig bis <strong>{{ $expiresAt }} Uhr</strong>.
                </p>

                <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;">

                <p style="margin: 0; font-size: 12px; color: #94a3b8;">
                    Falls der Button nicht funktioniert, kopieren Sie diesen Link in Ihren Browser:<br>
                    <a href="{{ $onboardingUrl }}" style="color: #2563eb; word-break: break-all;">{{ $onboardingUrl }}</a>
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
