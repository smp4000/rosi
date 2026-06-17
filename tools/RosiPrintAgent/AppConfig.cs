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

    /// <summary>Config-Ordner: %APPDATA%\RosiPrintAgent (Roaming). Hier liegt config.json
    /// und alternativ die enroll.json (fester, vorhersehbarer Ablageort).</summary>
    public static string Dir =>
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
/// Optionale enroll.json: { "server_url": "...", "enroll_token": "..." }.
/// Wird an mehreren Orten gesucht, damit die genaue Ablage egal ist:
///   1) neben der EXE,  2) Config-Ordner (%APPDATA%\RosiPrintAgent),
///   3) Downloads-Ordner.
/// </summary>
public class EnrollFile
{
    [JsonPropertyName("server_url")] public string? ServerUrl { get; set; }
    [JsonPropertyName("enroll_token")] public string? EnrollToken { get; set; }

    public static EnrollFile? Load()
    {
        foreach (var dir in SearchDirs())
        {
            try
            {
                var path = Path.Combine(dir, "enroll.json");
                if (File.Exists(path))
                {
                    var f = JsonSerializer.Deserialize<EnrollFile>(File.ReadAllText(path));
                    if (f != null && !string.IsNullOrWhiteSpace(f.EnrollToken))
                    {
                        return f;
                    }
                }
            }
            catch
            {
                // naechsten Ort versuchen
            }
        }

        return null;
    }

    private static IEnumerable<string> SearchDirs()
    {
        yield return AppContext.BaseDirectory;                 // neben der EXE
        yield return AppConfig.Dir;                            // %APPDATA%\RosiPrintAgent
        var profile = Environment.GetFolderPath(Environment.SpecialFolder.UserProfile);
        if (!string.IsNullOrEmpty(profile))
        {
            yield return Path.Combine(profile, "Downloads");   // Downloads-Ordner
        }
    }
}
