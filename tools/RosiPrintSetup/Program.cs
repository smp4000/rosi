using System.Diagnostics;
using System.IO;
using System.Reflection;
using System.Windows.Forms;
using Microsoft.Win32;

namespace RosiPrintSetup;

/// <summary>
/// Installer fuer den ROSI Print Agent: legt RosiPrintAgent.exe + ANLEITUNG.txt
/// nach %LOCALAPPDATA%\RosiPrint, richtet den Autostart ein, erstellt eine
/// Startmenue-Verknuepfung und startet den Agent. Kein Adminrecht noetig.
/// </summary>
internal static class Program
{
    private const string AppName = "RosiPrintAgent";

    [STAThread]
    private static void Main()
    {
        ApplicationConfiguration.Initialize();

        try
        {
            var targetDir = Path.Combine(
                Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                "RosiPrint");
            Directory.CreateDirectory(targetDir);

            // Evtl. laufenden Agent beenden (damit die exe ueberschrieben werden kann -> auch Update)
            foreach (var p in Process.GetProcessesByName(AppName))
            {
                try { p.Kill(); p.WaitForExit(3000); } catch { /* egal */ }
            }

            var exePath = Path.Combine(targetDir, "RosiPrintAgent.exe");
            ExtractResource("RosiPrintAgent.exe", exePath);
            ExtractResource("ANLEITUNG.txt", Path.Combine(targetDir, "ANLEITUNG.txt"));

            // Autostart (HKCU\...\Run, kein Adminrecht noetig)
            using (var key = Registry.CurrentUser.CreateSubKey(
                @"Software\Microsoft\Windows\CurrentVersion\Run"))
            {
                key?.SetValue(AppName, $"\"{exePath}\"");
            }

            TryCreateStartMenuShortcut(exePath);

            // Agent starten (oeffnet den Einstellungen-Dialog fuer Token-Eingabe)
            Process.Start(new ProcessStartInfo(exePath) { UseShellExecute = true });

            MessageBox.Show(
                "ROSI Print wurde installiert und startet ab jetzt automatisch mit Windows.\n\n"
                + "Ordner:\n" + targetDir + "\n\n"
                + "Bitte im gerade geoeffneten Fenster die Server-URL\n"
                + "(https://rosi.aral-welle.com) und den Agent-Token eintragen.\n\n"
                + "Den Token gibt es im ROSI-Dashboard unter\n"
                + "Drucken -> Druck-Agenten -> \"Neuer Agent\".",
                "ROSI Print - Installation abgeschlossen",
                MessageBoxButtons.OK, MessageBoxIcon.Information);
        }
        catch (Exception ex)
        {
            MessageBox.Show(
                "Installation fehlgeschlagen:\n\n" + ex.Message,
                "ROSI Print", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
    }

    private static void ExtractResource(string endsWith, string targetPath)
    {
        var asm = Assembly.GetExecutingAssembly();
        var name = asm.GetManifestResourceNames()
            .FirstOrDefault(n => n.EndsWith(endsWith, StringComparison.OrdinalIgnoreCase))
            ?? throw new FileNotFoundException("Eingebettete Datei fehlt: " + endsWith);

        using var rs = asm.GetManifestResourceStream(name)
            ?? throw new FileNotFoundException("Ressource nicht lesbar: " + name);
        using var fs = File.Create(targetPath);
        rs.CopyTo(fs);
    }

    private static void TryCreateStartMenuShortcut(string exePath)
    {
        try
        {
            var programs = Environment.GetFolderPath(Environment.SpecialFolder.Programs);
            var lnkPath = Path.Combine(programs, "ROSI Print.lnk");

            var shellType = Type.GetTypeFromProgID("WScript.Shell");
            if (shellType is null)
            {
                return;
            }

            dynamic shell = Activator.CreateInstance(shellType)!;
            var shortcut = shell.CreateShortcut(lnkPath);
            shortcut.TargetPath = exePath;
            shortcut.WorkingDirectory = Path.GetDirectoryName(exePath);
            shortcut.Description = "ROSI Print Agent";
            shortcut.Save();
        }
        catch
        {
            // Verknuepfung ist optional
        }
    }
}
