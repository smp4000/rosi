using System.IO;
using System.Text.Json;
using System.Text.Json.Serialization;

namespace RosiPrintAgent;

/// <summary>
/// Konfiguration des Agents, gespeichert unter
/// %APPDATA%\RosiPrintAgent\config.json. Liegt im AppData (ueberlebt Updates),
/// damit die Install-ID stabil bleibt (ein PC = ein Agent).
/// </summary>
public class AppConfig
{
    public string? ServerUrl { get; set; }
    public string? Token { get; set; }

    /// <summary>Stabile Maschinen-ID (einmalig erzeugt) — verhindert Doppel-Agenten.</summary>
    public string? InstallId { get; set; }

    /// <summary>Self-Register: Geheimnis zum spaeteren Token-Abholen nach Freigabe.</summary>
    public string? ClaimSecret { get; set; }

    [JsonIgnore]
    public bool HasToken => !string.IsNullOrWhiteSpace(Token);

    /// <summary>Server-URL: Konfiguration > enroll.json > fest eingebauter Default.</summary>
    [JsonIgnore]
    public string EffectiveServerUrl =>
        !string.IsNullOrWhiteSpace(ServerUrl) ? ServerUrl!
            : (EnrollFile.Load()?.ServerUrl ?? Program.DefaultServerUrl);

    private static string Dir =>
        Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.ApplicationData), "RosiPrintAgent");

    private static string FilePath => Path.Combine(Dir, "config.json");

    public static AppConfig Load()
    {
        AppConfig cfg;
        try
        {
            cfg = File.Exists(FilePath)
                ? JsonSerializer.Deserialize<AppConfig>(File.ReadAllText(FilePath)) ?? new AppConfig()
                : new AppConfig();
        }
        catch
        {
            cfg = new AppConfig();
        }

        // Install-ID einmalig erzeugen und festschreiben.
        if (string.IsNullOrWhiteSpace(cfg.InstallId))
        {
            cfg.InstallId = Guid.NewGuid().ToString("N");
            cfg.Save();
        }

        return cfg;
    }

    public void Save()
    {
        Directory.CreateDirectory(Dir);
        var json = JsonSerializer.Serialize(this, new JsonSerializerOptions { WriteIndented = true });
        File.WriteAllText(FilePath, json);
    }
}

/// <summary>
/// Optionale enroll.json NEBEN der EXE (vom Stations-Installer mitgeliefert):
/// { "server_url": "...", "enroll_token": "..." }.
/// </summary>
public class EnrollFile
{
    [JsonPropertyName("server_url")] public string? ServerUrl { get; set; }
    [JsonPropertyName("enroll_token")] public string? EnrollToken { get; set; }

    public static EnrollFile? Load()
    {
        try
        {
            var path = Path.Combine(AppContext.BaseDirectory, "enroll.json");
            if (File.Exists(path))
            {
                return JsonSerializer.Deserialize<EnrollFile>(File.ReadAllText(path));
            }
        }
        catch
        {
            // ignorieren
        }

        return null;
    }
}
