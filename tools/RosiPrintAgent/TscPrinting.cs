using System.Text;

namespace RosiPrintAgent;

/// <summary>
/// TSPL-Druck fuer TSC-Thermo-Etikettendrucker (z.B. TSC DA210, 203 dpi = 8 dots/mm).
/// </summary>
public static class TscPrinting
{
    /// <summary>Erkennt einen TSC-Drucker am Namen.</summary>
    public static bool IsTsc(string? name)
    {
        if (string.IsNullOrWhiteSpace(name)) return false;
        var n = name.ToLowerInvariant();
        return n.Contains("tsc") || n.Contains("da210") || n.Contains("ttp") || n.Contains("tdp");
    }

    /// <summary>
    /// Test-Etikett mit Datum + Uhrzeit. Groesse wie DYMO-Versandetikett
    /// (54 x 101 mm). Bei abweichender Rolle hier SIZE anpassen.
    /// </summary>
    public static void PrintTestLabel(string printerName)
    {
        var now = DateTime.Now;

        var t = new StringBuilder();
        t.Append("SIZE 54 mm,101 mm\r\n");
        t.Append("GAP 2 mm,0 mm\r\n");
        t.Append("DIRECTION 1\r\n");
        t.Append("CLS\r\n");
        t.Append("TEXT 40,50,\"4\",0,1,1,\"ROSI Print\"\r\n");
        t.Append("TEXT 40,140,\"3\",0,1,1,\"Test-Etikett\"\r\n");
        t.Append($"TEXT 40,260,\"3\",0,1,1,\"Datum:   {now:dd.MM.yyyy}\"\r\n");
        t.Append($"TEXT 40,330,\"3\",0,1,1,\"Uhrzeit: {now:HH:mm:ss}\"\r\n");
        t.Append("PRINT 1,1\r\n");

        RawPrinterHelper.SendBytesToPrinter(printerName, Encoding.ASCII.GetBytes(t.ToString()));
    }
}
