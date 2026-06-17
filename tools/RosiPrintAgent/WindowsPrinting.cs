using System.Drawing;
using System.Drawing.Printing;

namespace RosiPrintAgent;

/// <summary>
/// Druck ueber den normalen Windows-Spooler (fuer Nicht-DYMO-Drucker wie
/// Brother/TSC). Aktuell: einfache Testseite. Etiketten-/Dokumentdruck folgt.
/// </summary>
public static class WindowsPrinting
{
    public static void PrintTestPage(string printerName, IEnumerable<string> lines)
    {
        using var doc = new PrintDocument();
        doc.PrinterSettings.PrinterName = printerName;
        if (!doc.PrinterSettings.IsValid)
        {
            throw new Exception($"Drucker '{printerName}' nicht gefunden.");
        }

        doc.DocumentName = "ROSI Print Testseite";
        var text = string.Join(Environment.NewLine, lines);

        doc.PrintPage += (_, e) =>
        {
            using var font = new Font("Arial", 11);
            e.Graphics!.DrawString(text, font, Brushes.Black, 40, 40);
        };

        doc.Print();
    }
}
