using System.Drawing.Printing;

namespace RosiPrintAgent;

/// <summary>
/// Alle real nutzbaren Drucker des PCs: DYMO (ueber DYMO-Connect) + die in
/// Windows installierten physischen Drucker (z.B. Brother, TSC). Virtuelle
/// Drucker (PDF/XPS/OneNote/Fax) werden ausgeblendet.
/// </summary>
public static class PrinterInventory
{
    public static async Task<List<string>> AllAsync(DymoClient dymo)
    {
        var names = new List<string>();

        // DYMO-Drucker ueber den DYMO-Connect-Dienst (inkl. Standby/Wireless).
        try { names.AddRange(await dymo.GetPrinterNamesAsync()); }
        catch { /* DYMO-Dienst nicht erreichbar */ }

        // In Windows installierte Drucker (Brother, TSC, ...).
        try
        {
            foreach (string p in PrinterSettings.InstalledPrinters)
            {
                if (!IsVirtual(p))
                {
                    names.Add(p);
                }
            }
        }
        catch { /* Spooler nicht verfuegbar */ }

        return names
            .Where(n => !string.IsNullOrWhiteSpace(n))
            .Distinct(StringComparer.OrdinalIgnoreCase)
            .ToList();
    }

    /// <summary>Virtuelle/„Datei"-Drucker, die niemand auswaehlen will.</summary>
    private static bool IsVirtual(string name)
    {
        var n = name.ToLowerInvariant();
        return n.Contains("pdf")
            || n.Contains("xps")
            || n.Contains("onenote")
            || n.Contains("fax")
            || n.Contains("microsoft print")
            || n.Contains("send to")
            || n.Contains("adobe");
    }
}
