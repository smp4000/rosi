using System.Windows.Forms;

namespace RosiPrintAgent;

internal static class Program
{
    /// <summary>Anzeige-Version.</summary>
    public const string Version = "1.1.8";

    /// <summary>Technische Versionsnummer fuer das Auto-Update (immer hochzaehlen).</summary>
    public const int VersionCode = 10;

    /// <summary>Fest eingebaute Server-URL (ueberschreibbar via config/enroll.json).</summary>
    public const string DefaultServerUrl = "https://rosi.aral-welle.com";

    [STAThread]
    private static void Main()
    {
        // Nur eine Instanz zulassen
        using var mutex = new Mutex(true, "RosiPrintAgent_SingleInstance", out bool isNew);
        if (!isNew)
        {
            MessageBox.Show("ROSI Print laeuft bereits (siehe Infobereich/Tray).",
                "ROSI Print", MessageBoxButtons.OK, MessageBoxIcon.Information);
            return;
        }

        ApplicationConfiguration.Initialize();
        Application.Run(new TrayApplicationContext());
    }
}
