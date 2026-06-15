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

    /// <summary>Namen aller lokal sichtbaren DYMO-Drucker.</summary>
    public async Task<List<string>> GetPrinterNamesAsync()
    {
        var names = new List<string>();
        try
        {
            var port = await FindPortAsync();
            var xml = await _http.GetStringAsync($"{Base(port)}/GetPrinters");
            foreach (Match m in Regex.Matches(xml, "<Name>(.*?)</Name>", RegexOptions.Singleline))
            {
                var name = WebUtility.HtmlDecode(m.Groups[1].Value).Trim();
                if (name.Length > 0)
                {
                    names.Add(name);
                }
            }
        }
        catch
        {
            // Service nicht erreichbar -> leere Liste
        }

        return names;
    }

    /// <summary>Ein Label drucken. Ohne printerName wird der erste Drucker genutzt.</summary>
    public async Task PrintAsync(string? printerName, string labelXml)
    {
        var port = await FindPortAsync();

        var printer = printerName;
        if (string.IsNullOrWhiteSpace(printer))
        {
            var names = await GetPrinterNamesAsync();
            printer = names.FirstOrDefault()
                ?? throw new Exception("Kein DYMO-Drucker gefunden.");
        }

        var form = new Dictionary<string, string>
        {
            ["printerName"] = printer,
            ["printParamsXml"] = "<LabelWriterPrintParams><Copies>1</Copies></LabelWriterPrintParams>",
            ["labelXml"] = labelXml,
            ["labelSetXml"] = "",
        };

        var resp = await _http.PostAsync($"{Base(port)}/PrintLabel2", new FormUrlEncodedContent(form));
        if (!resp.IsSuccessStatusCode)
        {
            throw new Exception($"DYMO PrintLabel2 -> HTTP {(int)resp.StatusCode}");
        }
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
