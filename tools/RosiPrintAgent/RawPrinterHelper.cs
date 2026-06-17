using System.Runtime.InteropServices;

namespace RosiPrintAgent;

/// <summary>
/// Schickt Rohdaten (z.B. TSPL/ZPL) direkt an einen Windows-Drucker, ohne
/// GDI-Rendering — fuer Thermo-Etikettendrucker wie den TSC DA210.
/// </summary>
public static class RawPrinterHelper
{
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    private struct DOCINFO
    {
        [MarshalAs(UnmanagedType.LPWStr)] public string pDocName;
        [MarshalAs(UnmanagedType.LPWStr)] public string? pOutputFile;
        [MarshalAs(UnmanagedType.LPWStr)] public string pDataType;
    }

    [DllImport("winspool.drv", CharSet = CharSet.Unicode, SetLastError = true)]
    private static extern bool OpenPrinter(string src, out IntPtr hPrinter, IntPtr pd);

    [DllImport("winspool.drv", SetLastError = true)]
    private static extern bool ClosePrinter(IntPtr hPrinter);

    [DllImport("winspool.drv", CharSet = CharSet.Unicode, SetLastError = true)]
    private static extern bool StartDocPrinter(IntPtr hPrinter, int level, ref DOCINFO di);

    [DllImport("winspool.drv", SetLastError = true)]
    private static extern bool EndDocPrinter(IntPtr hPrinter);

    [DllImport("winspool.drv", SetLastError = true)]
    private static extern bool StartPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.drv", SetLastError = true)]
    private static extern bool EndPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.drv", SetLastError = true)]
    private static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBytes, int dwCount, out int dwWritten);

    public static void SendBytesToPrinter(string printerName, byte[] bytes)
    {
        if (!OpenPrinter(printerName, out var hPrinter, IntPtr.Zero))
        {
            throw new Exception($"Drucker '{printerName}' konnte nicht geoeffnet werden.");
        }

        var unmanaged = IntPtr.Zero;
        try
        {
            var di = new DOCINFO
            {
                pDocName = "ROSI Print Label",
                pDataType = "RAW",
            };

            if (!StartDocPrinter(hPrinter, 1, ref di))
            {
                throw new Exception("StartDocPrinter fehlgeschlagen.");
            }

            StartPagePrinter(hPrinter);

            unmanaged = Marshal.AllocCoTaskMem(bytes.Length);
            Marshal.Copy(bytes, 0, unmanaged, bytes.Length);
            WritePrinter(hPrinter, unmanaged, bytes.Length, out _);

            EndPagePrinter(hPrinter);
            EndDocPrinter(hPrinter);
        }
        finally
        {
            if (unmanaged != IntPtr.Zero)
            {
                Marshal.FreeCoTaskMem(unmanaged);
            }
            ClosePrinter(hPrinter);
        }
    }
}
