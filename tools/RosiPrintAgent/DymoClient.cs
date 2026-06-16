using System.Net;
using System.Net.Http;
using System.Text.RegularExpressions;

namespace RosiPrintAgent;

/// <summary>
/// Spricht den lokalen DYMO-Connect/DLS-Webservice (https://localhost:41951-41955).
/// Weil lokal: kein Firewall-/Netz-Thema, nur das selbstsignierte Zertifikat
/// muss ignoriert werden.
/// </summary>
public class DymoClient
{
    private static readonly int[] Ports = { 41951, 41952, 41953, 41954, 41955 };
    private int? _activePort;
    private readonly HttpClient _http;

    public DymoClient()
    {
        var handler = new HttpClientHandler
        {
            // DYMO nutzt ein selbstsigniertes Zertifikat auf localhost
            ServerCertificateCustomValidationCallback = (_, _, _, _) => true,
        };
        _http = new HttpClient(handler) { Timeout = TimeSpan.FromSeconds(8) };
    }

    public async Task<bool> IsAvailableAsync()
    {
        try
        {
            await FindPortAsync();
            return true;
        }
        catch
        {
            return false;
        }
    }

    /// <summary>Ein DYMO-Drucker mit Verbindungsstatus.</summary>
    private sealed record DymoPrinter(string Name, bool Connected);

    /// <summary>Drucker inkl. IsConnected aus GetPrinters parsen.</summary>
    private async Task<List<DymoPrinter>> GetPrintersAsync()
    {
        var list = new List<DymoPrinter>();
        try
        {
            var port = await FindPortAsync();
            var xml = await _http.GetStringAsync($"{Base(port)}/GetPrinters");

            // Pro <LabelWriterPrinter>-Block Name + IsConnected lesen
            foreach (Match block in Regex.Matches(xml, "<LabelWriterPrinter>(.*?)</LabelWriterPrinter>", RegexOptions.Singleline))
            {
                var inner = block.Groups[1].Value;
                var name = WebUtility.HtmlDecode(Match(inner, "<Name>(.*?)</Name>")).Trim();
                var connected = Match(inner, "<IsConnected>(.*?)</IsConnected>")
                    .Trim().Equals("True", StringComparison.OrdinalIgnoreCase);
                if (name.Length > 0)
                {
                    list.Add(new DymoPrinter(name, connected));
                }
            }
        }
        catch
        {
            // Service nicht erreichbar -> leere Liste
        }

        return list;
    }

    /// <summary>
    /// Namen der nutzbaren Drucker fuer die Meldung ans Dashboard.
    /// Bevorzugt VERBUNDENE Drucker (sonst meldet DYMO denselben Drucker mehrfach
    /// als "Name", "Name on HOST", "HOST"). Nur wenn keiner verbunden ist, werden
    /// alle gemeldet, damit ueberhaupt etwas auswaehlbar ist.
    /// </summary>
    public async Task<List<string>> GetPrinterNamesAsync()
    {
        var printers = await GetPrintersAsync();
        var connected = printers.Where(p => p.Connected).Select(p => p.Name).Distinct().ToList();
        return connected.Count > 0
            ? connected
            : printers.Select(p => p.Name).Distinct().ToList();
    }

    /// <summary>
    /// Ein Label drucken. Bevorzugt einen VERBUNDENEN Drucker; ist der gewuenschte
    /// nicht verbunden, wird auf einen verbundenen ausgewichen (sonst nimmt der
    /// DYMO-Service den Auftrag an, druckt aber nichts).
    /// </summary>
    public async Task PrintAsync(string? printerName, string labelXml)
    {
        var port = await FindPortAsync();
        var printers = await GetPrintersAsync();

        if (printers.Count == 0)
        {
            throw new Exception("Kein DYMO-Drucker gefunden.");
        }

        var connected = printers.Where(p => p.Connected).ToList();
        string printer;

        if (! string.IsNullOrWhiteSpace(printerName))
        {
            var requested = printers.FirstOrDefault(p =>
                p.Name.Equals(printerName, StringComparison.OrdinalIgnoreCase));
            // Gewuenschter Drucker verbunden? -> nehmen. Sonst verbundenen Ersatz.
            printer = (requested is { Connected: true })
                ? requested.Name
                : (connected.FirstOrDefault()?.Name ?? printerName);
        }
        else
        {
            printer = (connected.FirstOrDefault() ?? printers[0]).Name;
        }

        var form = new Dictionary<string, string>
        {
            ["printerName"] = printer,
            ["printParamsXml"] = "<LabelWriterPrintParams><Copies>1</Copies></LabelWriterPrintParams>",
            ["labelXml"] = labelXml,
            ["labelSetXml"] = "",
        };

        var resp = await _http.PostAsync($"{Base(port)}/PrintLabel2", new FormUrlEncodedContent(form));
        var body = (await resp.Content.ReadAsStringAsync()).Trim();

        if (!resp.IsSuccessStatusCode)
        {
            throw new Exception($"DYMO HTTP {(int)resp.StatusCode} ({printer})");
        }

        // DYMO meldet Fehler teils mit HTTP 200 im Body -> auswerten
        if (body.Equals("false", StringComparison.OrdinalIgnoreCase)
            || body.Contains("error", StringComparison.OrdinalIgnoreCase)
            || body.Contains("exception", StringComparison.OrdinalIgnoreCase))
        {
            var msg = body.Length > 180 ? body[..180] : body;
            throw new Exception($"DYMO ({printer}): {msg}");
        }
    }

    private static string Match(string input, string pattern)
    {
        var m = Regex.Match(input, pattern, RegexOptions.Singleline);
        return m.Success ? m.Groups[1].Value : string.Empty;
    }

    // ── intern ───────────────────────────────────────

    private static string Base(int port) => $"https://localhost:{port}/DYMO/DLS/Printing";

    private async Task<int> FindPortAsync()
    {
        if (_activePort is int p && await ConnectedAsync(p))
        {
            return p;
        }

        foreach (var port in Ports)
        {
            if (await ConnectedAsync(port))
            {
                _activePort = port;
                return port;
            }
        }

        _activePort = null;
        throw new Exception("DYMO-Service nicht erreichbar (Ports 41951-41955).");
    }

    private async Task<bool> ConnectedAsync(int port)
    {
        try
        {
            var result = await _http.GetStringAsync($"{Base(port)}/StatusConnected");
            return result.Trim().Equals("true", StringComparison.OrdinalIgnoreCase);
        }
        catch
        {
            return false;
        }
    }
}
