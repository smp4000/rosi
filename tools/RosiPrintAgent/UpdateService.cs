using System.Diagnostics;
using System.IO;
using System.Net.Http;

namespace RosiPrintAgent;

/// <summary>
/// Stilles Auto-Update: laedt die neue EXE herunter und ersetzt die laufende
/// per Helfer-Batch (wartet aufs Beenden, kopiert, startet neu).
/// </summary>
public static class UpdateService
{
    private static bool _running;

    /// <summary>
    /// Prueft die Update-Info und fuehrt das Update aus, wenn eine hoehere
    /// Version verfuegbar ist. Beendet danach die App (Neustart via Batch).
    /// </summary>
    public static async Task<bool> ApplyIfNewerAsync(UpdateInfo? update)
    {
        if (_running || update is null || string.IsNullOrWhiteSpace(update.DownloadUrl))
        {
            return false;
        }

        if (update.VersionCode <= Program.VersionCode)
        {
            return false;
        }

        _running = true;
        try
        {
            var exePath = Environment.ProcessPath
                ?? Process.GetCurrentProcess().MainModule?.FileName;
            if (string.IsNullOrWhiteSpace(exePath))
            {
                return false;
            }

            var tempExe = Path.Combine(Path.GetTempPath(), $"RosiPrintAgent_{update.VersionCode}.exe");

            using (var http = new HttpClient { Timeout = TimeSpan.FromMinutes(5) })
            await using (var stream = await http.GetStreamAsync(update.DownloadUrl))
            await using (var file = File.Create(tempExe))
            {
                await stream.CopyToAsync(file);
            }

            if (new FileInfo(tempExe).Length < 1_000_000)
            {
                // Offensichtlich keine gueltige EXE (z.B. Fehlerseite) -> abbrechen
                File.Delete(tempExe);
                return false;
            }

            WriteAndRunUpdaterBatch(tempExe, exePath);

            Application.Exit();
            return true;
        }
        catch
        {
            _running = false;
            return false;
        }
    }

    /// <summary>
    /// Schreibt eine Batch, die auf das Beenden dieses Prozesses wartet, die neue
    /// EXE ueberkopiert und den Agent neu startet. Startet sie versteckt.
    /// </summary>
    private static void WriteAndRunUpdaterBatch(string newExe, string targetExe)
    {
        var pid = Environment.ProcessId;
        var bat = Path.Combine(Path.GetTempPath(), "rosi_print_update.bat");

        var script =
            "@echo off\r\n" +
            ":waitloop\r\n" +
            $"tasklist /fi \"PID eq {pid}\" | find \"{pid}\" >nul\r\n" +
            "if not errorlevel 1 (\r\n" +
            "  timeout /t 1 /nobreak >nul\r\n" +
            "  goto waitloop\r\n" +
            ")\r\n" +
            "timeout /t 1 /nobreak >nul\r\n" +
            $"copy /y \"{newExe}\" \"{targetExe}\" >nul\r\n" +
            $"start \"\" \"{targetExe}\"\r\n" +
            $"del \"{newExe}\" >nul 2>&1\r\n" +
            "del \"%~f0\" >nul 2>&1\r\n";

        File.WriteAllText(bat, script);

        Process.Start(new ProcessStartInfo
        {
            FileName = "cmd.exe",
            Arguments = $"/c \"{bat}\"",
            CreateNoWindow = true,
            UseShellExecute = false,
            WindowStyle = ProcessWindowStyle.Hidden,
        });
    }
}
