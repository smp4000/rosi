using System.Drawing;
using System.Drawing.Drawing2D;

namespace RosiPrintAgent;

/// <summary>
/// Erzeugt die Tray-Icons zur Laufzeit (ein weisser Drucker auf farbiger
/// Scheibe). Farbe = Status. Kein externes .ico noetig -> Single-File bleibt clean.
/// </summary>
public static class IconFactory
{
    public static readonly Icon Ready = Build(Color.FromArgb(0x2E, 0x7D, 0x32));    // gruen
    public static readonly Icon Printing = Build(Color.FromArgb(0x15, 0x65, 0xC0)); // blau
    public static readonly Icon Warning = Build(Color.FromArgb(0xE6, 0x51, 0x00));  // orange
    public static readonly Icon Error = Build(Color.FromArgb(0xC6, 0x28, 0x28));    // rot
    public static readonly Icon Idle = Build(Color.FromArgb(0x75, 0x75, 0x75));     // grau

    private static Icon Build(Color disc)
    {
        using var bmp = new Bitmap(32, 32);
        using (var g = Graphics.FromImage(bmp))
        {
            g.SmoothingMode = SmoothingMode.AntiAlias;
            g.Clear(Color.Transparent);

            using var discBrush = new SolidBrush(disc);
            g.FillEllipse(discBrush, 1, 1, 30, 30);

            using var white = new SolidBrush(Color.White);
            // oberes Blatt
            g.FillRectangle(white, 11, 7, 10, 6);
            // Druckerkorpus (abgerundet)
            FillRounded(g, white, 8, 12, 16, 9, 2);
            // Ausgabe-Blatt unten
            g.FillRectangle(white, 11, 19, 10, 6);

            // Ausgabe-Schlitz in Scheibenfarbe
            using var slit = new SolidBrush(disc);
            g.FillRectangle(slit, 11, 18, 10, 2);
            // kleine Status-LED
            g.FillEllipse(slit, 20, 14, 2, 2);
        }

        return Icon.FromHandle(bmp.GetHicon());
    }

    private static void FillRounded(Graphics g, Brush brush, int x, int y, int w, int h, int r)
    {
        using var path = new GraphicsPath();
        path.AddArc(x, y, r * 2, r * 2, 180, 90);
        path.AddArc(x + w - r * 2, y, r * 2, r * 2, 270, 90);
        path.AddArc(x + w - r * 2, y + h - r * 2, r * 2, r * 2, 0, 90);
        path.AddArc(x, y + h - r * 2, r * 2, r * 2, 90, 90);
        path.CloseFigure();
        g.FillPath(brush, path);
    }
}
