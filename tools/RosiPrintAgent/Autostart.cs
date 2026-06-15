using Microsoft.Win32;

namespace RosiPrintAgent;

/// <summary>Autostart ueber den HKCU\...\Run-Registry-Schluessel (kein Adminrecht noetig).</summary>
public static class Autostart
{
    private const string RunKey = @"Software\Microsoft\Windows\CurrentVersion\Run";
    private const string ValueName = "RosiPrintAgent";

    public static bool IsEnabled()
    {
        using var key = Registry.CurrentUser.OpenSubKey(RunKey, false);
        return key?.GetValue(ValueName) != null;
    }

    public static void SetEnabled(bool enabled)
    {
        using var key = Registry.CurrentUser.OpenSubKey(RunKey, true)
            ?? Registry.CurrentUser.CreateSubKey(RunKey);

        if (enabled)
        {
            var exe = Environment.ProcessPath ?? Application.ExecutablePath;
            key.SetValue(ValueName, $"\"{exe}\"");
        }
        else if (key.GetValue(ValueName) != null)
        {
            key.DeleteValue(ValueName, false);
        }
    }
}
