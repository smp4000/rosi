using System.Net.Http;
using System.Net.Http.Json;
using System.Text.Json;

namespace RosiPrintAgent;

/// <summary>
/// Spricht die ROSI-Druck-Agent-API (snake_case JSON). Auth per Agent-Token.
/// </summary>
public class RosiApiClient
{
    private readonly HttpClient _http;
    private readonly string _token;

    private static readonly JsonSerializerOptions JsonOpts = new()
    {
        PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower,
        PropertyNameCaseInsensitive = true,
    };

    public RosiApiClient(string serverUrl, string token)
    {
        var baseUrl = serverUrl.TrimEnd('/') + "/api/v1/";
        _http = new HttpClient
        {
            BaseAddress = new Uri(baseUrl),
            Timeout = TimeSpan.FromSeconds(15),
        };
        _token = token;
    }

    public async Task<HeartbeatData?> HeartbeatAsync(List<string> printers)
    {
        var body = new { Token = _token, Printers = printers, AppVersion = Program.Version };
        return await PostAsync<HeartbeatData>("print/agent/heartbeat", body);
    }

    public async Task<List<PrintJobDto>> ClaimAsync()
    {
        var data = await PostAsync<ClaimData>("print/agent/jobs/claim", new { Token = _token });
        return data?.Jobs ?? new List<PrintJobDto>();
    }

    public async Task AckAsync(string jobId, bool success, string? errorMessage)
    {
        var body = new { Token = _token, Success = success, ErrorMessage = errorMessage };
        await PostAsync<object>($"print/agent/jobs/{jobId}/ack", body);
    }

    private async Task<T?> PostAsync<T>(string path, object body)
    {
        var resp = await _http.PostAsJsonAsync(path, body, JsonOpts);
        resp.EnsureSuccessStatusCode();

        var wrapper = await resp.Content.ReadFromJsonAsync<ApiResponse<T>>(JsonOpts);
        if (wrapper is { Success: false })
        {
            throw new Exception(wrapper.Message ?? "API-Fehler");
        }

        return wrapper is null ? default : wrapper.Data;
    }
}
