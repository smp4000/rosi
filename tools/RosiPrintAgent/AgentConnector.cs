using System.Net.Http;
using System.Net.Http.Json;
using System.Text.Json;

namespace RosiPrintAgent;

/// <summary>
/// Verbindet den Agent automatisch mit dem Web (ohne Agent-Token):
///  - enroll:       Stations-Installer (enroll.json) -> sofort Token
///  - register:     generische EXE -> wartet auf Dashboard-Freigabe
///  - claim-token:  holt nach Freigabe das Token ab
/// </summary>
public class AgentConnector
{
    private readonly HttpClient _http;

    private static readonly JsonSerializerOptions JsonOpts = new()
    {
        PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower,
        PropertyNameCaseInsensitive = true,
    };

    public AgentConnector(string serverUrl)
    {
        _http = new HttpClient
        {
            BaseAddress = new Uri(serverUrl.TrimEnd('/') + "/api/v1/"),
            Timeout = TimeSpan.FromSeconds(15),
        };
    }

    public Task<ConnectData?> EnrollAsync(string enrollToken, string installId, string hostname, List<string> printers)
        => PostAsync("print/agent/enroll", new
        {
            EnrollToken = enrollToken,
            InstallId = installId,
            Hostname = hostname,
            Printers = printers,
        });

    public Task<ConnectData?> RegisterAsync(string installId, string hostname, List<string> printers)
        => PostAsync("print/agent/register", new
        {
            InstallId = installId,
            Hostname = hostname,
            Printers = printers,
        });

    public Task<ConnectData?> ClaimTokenAsync(string installId, string claimSecret)
        => PostAsync("print/agent/claim-token", new
        {
            InstallId = installId,
            ClaimSecret = claimSecret,
        });

    private async Task<ConnectData?> PostAsync(string path, object body)
    {
        var resp = await _http.PostAsJsonAsync(path, body, JsonOpts);
        var wrapper = await resp.Content.ReadFromJsonAsync<ApiResponse<ConnectData>>(JsonOpts);

        if (!resp.IsSuccessStatusCode || wrapper is { Success: false })
        {
            throw new Exception(wrapper?.Message ?? $"HTTP {(int)resp.StatusCode}");
        }

        return wrapper?.Data;
    }
}
