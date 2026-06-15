using System.IO;
using System.Text.Json;
using System.Text.Json.Serialization;

namespace RosiPrintAgent;

/// <summary>
/// Konfiguration des Agents (Server-URL + Agent-Token), gespeichert unter
/// %APPDATA%\RosiPrintAgent\config.json.
/// </summary>
public class AppConfig
{
    public string? ServerUrl { get; set; }
    public string? Token { get; set; }

    [JsonIgnore]
    public bool IsComplete =>
        !string.IsNullOrWhiteSpace(ServerUrl) && !string.IsNullOrWhiteSpace(Token);

    private static string Dir =>
        Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.ApplicationData), "RosiPrintAgent");

    private static string FilePath => Path.Combine(Dir, "config.json");

    public static AppConfig Load()
    {
        try
        {
            if (File.Exists(FilePath))
            {
                var json = File.ReadAllText(FilePath);
                return JsonSerializer.Deserialize<AppConfig>(json) ?? new AppConfig();
            }
        }
        catch
        {
            // beschaedigte Datei -> Default
        }

        return new AppConfig();
    }

    public void Save()
    {
        Directory.CreateDirectory(Dir);
        var json = JsonSerializer.Serialize(this, new JsonSerializerOptions { WriteIndented = true });
        File.WriteAllText(FilePath, json);
    }
}
