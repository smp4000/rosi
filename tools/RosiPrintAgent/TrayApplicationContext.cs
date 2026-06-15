using System.Windows.Forms;

namespace RosiPrintAgent;

/// <summary>
/// Hintergrund-App mit Tray-Icon. Pollt im Sekundentakt die Druck-Queue der
/// Station, druckt die Jobs lokal am DYMO und quittiert sie. Heartbeat (mit
/// Druckerliste) alle 30 s.
/// </summary>
public class TrayApplicationContext : ApplicationContext
{
    /// <summary>Pause nach jedem Etikett, damit der DYMO nicht ueberlaeuft (leere Labels).</summary>
    private const int LabelDelayMs = 600;

    /// <summary>Erst ab dieser Etikettenzahl wird gebremst (kleine Drucke bleiben sofort).</summary>
    private const int PaceThreshold = 10;

    private readonly NotifyIcon _tray;
    private readonly ToolStripMenuItem _statusItem;
    private readonly ToolStripMenuItem _autostartItem;
    private readonly System.Windows.Forms.Timer _timer;
    private readonly DymoClient _dymo = new();

    private AppConfig _config;
    private RosiApiClient? _api;
    private bool _busy;
    private int _tick;
    private string _status = "Start…";

    public TrayApplicationContext()
    {
        _config = AppConfig.Load();

        _statusItem = new ToolStripMenuItem("Status…") { Enabled = false };
        _autostartItem = new ToolStripMenuItem("Mit Windows starten") { Checked = Autostart.IsEnabled() };
        _autostartItem.Click += (_, _) =>
        {
            var on = !_autostartItem.Checked;
            Autostart.SetEnabled(on);
            _autostartItem.Checked = on;
        };

        var menu = new ContextMenuStrip();
        menu.Items.Add(_statusItem);
        menu.Items.Add(new ToolStripSeparator());
        menu.Items.Add("Jetzt pruefen", null, async (_, _) => await TickOnce());
        menu.Items.Add("Einstellungen…", null, (_, _) => OpenSettings());
        menu.Items.Add(_autostartItem);
        menu.Items.Add(new ToolStripSeparator());
        menu.Items.Add("Beenden", null, (_, _) => ExitApp());
        menu.Opening += (_, _) => _statusItem.Text = _status;

        _tray = new NotifyIcon
        {
            Icon = IconFactory.Idle,
            Visible = true,
            Text = "ROSI Print",
            ContextMenuStrip = menu,
        };
        _tray.DoubleClick += (_, _) => OpenSettings();

        ApplyConfig();

        _timer = new System.Windows.Forms.Timer { Interval = 1000 };
        _timer.Tick += async (_, _) => await TickOnce();
        _timer.Start();

        if (!_config.IsComplete)
        {
            SetStatus("Nicht konfiguriert — Einstellungen oeffnen", IconFactory.Error);
            OpenSettings();
        }
    }

    private void ApplyConfig()
    {
        _api = _config.IsComplete ? new RosiApiClient(_config.ServerUrl!, _config.Token!) : null;
    }

    private async Task TickOnce()
    {
        if (_busy || _api == null)
        {
            return;
        }

        _busy = true;
        try
        {
            _tick++;

            // Heartbeat (mit Druckerliste) alle 30 Ticks
            if (_tick % 30 == 1)
            {
                var printers = await _dymo.GetPrinterNamesAsync();
                await _api.HeartbeatAsync(printers);
            }

            await PollAndPrint();
        }
        catch (Exception ex)
        {
            SetStatus($"Server-Fehler: {Short(ex.Message)}", IconFactory.Error);
        }
        finally
        {
            _busy = false;
        }
    }

    private async Task PollAndPrint()
    {
        var jobs = await _api!.ClaimAsync();

        if (jobs.Count == 0)
        {
            var dymoOk = await _dymo.IsAvailableAsync();
            SetStatus(
                dymoOk ? "Verbunden — bereit" : "Server ok · DYMO nicht gefunden",
                dymoOk ? IconFactory.Ready : IconFactory.Warning);
            return;
        }

        foreach (var job in jobs)
        {
            try
            {
                var total = job.Labels.Count;

                // Nur bei groesseren Mengen (z.B. Gutscheine) bremsen — sonst
                // ueberlaeuft der DYMO-Spooler und es kommen leere Labels heraus.
                // Einzel-/Kleindrucke laufen ohne Verzoegerung.
                var pace = total > PaceThreshold;

                for (int i = 0; i < total; i++)
                {
                    SetStatus(
                        total > 1
                            ? $"Druckt {i + 1}/{total}: {job.Reference ?? job.JobType}…"
                            : $"Druckt: {job.Reference ?? job.JobType}…",
                        IconFactory.Printing);

                    await _dymo.PrintAsync(job.PrinterName, job.Labels[i].Xml);

                    if (pace)
                    {
                        await Task.Delay(LabelDelayMs);
                    }
                }

                await _api.AckAsync(job.Id, true, null);
                SetStatus($"Gedruckt: {job.Reference ?? job.JobType} ({job.Labels.Count})", IconFactory.Ready);
            }
            catch (Exception ex)
            {
                await SafeAckFail(job.Id, ex.Message);
                SetStatus($"Druckfehler: {Short(ex.Message)}", IconFactory.Error);
            }
        }
    }

    private async Task SafeAckFail(string jobId, string error)
    {
        try
        {
            await _api!.AckAsync(jobId, false, error);
        }
        catch
        {
            // Quittung fehlgeschlagen -> Cleanup requeued den Job spaeter
        }
    }

    private void OpenSettings()
    {
        using var form = new SettingsForm(_config);
        if (form.ShowDialog() == DialogResult.OK)
        {
            _config.ServerUrl = form.ServerUrl;
            _config.Token = form.Token;
            _config.Save();
            ApplyConfig();
            _tick = 0; // erzwingt sofort Heartbeat beim naechsten Tick
            SetStatus("Gespeichert — verbinde…", IconFactory.Idle);
        }
    }

    private void SetStatus(string text, Icon icon)
    {
        _status = text;
        _tray.Text = Short("ROSI Print — " + text, 63);
        _tray.Icon = icon;
    }

    private void ExitApp()
    {
        _timer.Stop();
        _tray.Visible = false;
        _tray.Dispose();
        ExitThread();
    }

    private static string Short(string s, int max = 80)
        => s.Length <= max ? s : s[..max];
}
