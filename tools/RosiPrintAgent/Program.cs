using System.Windows.Forms;

namespace RosiPrintAgent;

internal static class Program
{
    public const string Version = "1.0.0";

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
