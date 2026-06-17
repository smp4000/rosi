using System.Windows.Forms;

namespace RosiPrintAgent;

/// <summary>
/// Hintergrund-App mit Tray-Icon. Verbindet sich vollautomatisch mit dem Web
/// (enroll.json oder Self-Register + Freigabe), pollt die Druck-Queue, druckt
/// lokal am DYMO und quittiert. Heartbeat alle 30 s liefert auch Update-Infos
/// fuer das stille Auto-Update.
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
    private AgentConnector? _connector;
    private bool _busy;
    private int _tick;
    private int _connectTick;
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

        var versionItem = new ToolStripMenuItem($"ROSI Print {Program.Version}") { Enabled = false };

        var menu = new ContextMenuStrip();
        menu.Items.Add(versionItem);
        menu.Items.Add(_statusItem);
        menu.Items.Add(new ToolStripSeparator());
        menu.Items.Add("Jetzt pruefen", null, async (_, _) => await TickOnce());
        menu.Items.Add("Neu verbinden", null, (_, _) => Reconnect());
        menu.Items.Add("Nach Updates suchen", null, async (_, _) => await CheckUpdateNow());
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
        SetStatus(_config.HasToken ? "Verbinde…" : "Verbinde automatisch…", IconFactory.Idle);

        _timer = new System.Windows.Forms.Timer { Interval = 1000 };
        _timer.Tick += async (_, _) => await TickOnce();
        _timer.Start();
    }

    private void ApplyConfig()
    {
        var url = _config.EffectiveServerUrl;
        _connector = new AgentConnector(url);
        _api = _config.HasToken ? new RosiApiClient(url, _config.Token!) : null;
    }

    private async Task TickOnce()
    {
        if (_busy)
        {
            return;
        }

        _busy = true;
        try
        {
            // Noch kein Token? -> automatisch verbinden.
            if (_api == null)
            {
                await TryConnectOnce();
                return;
            }

            _tick++;

            // Heartbeat (mit Druckerliste) alle 30 Ticks -> auch Update-Check.
            if (_tick % 30 == 1)
            {
                var printers = await PrinterInventory.AllAsync(_dymo);
                var hb = await _api.HeartbeatAsync(printers);
                if (hb?.Update != null && await UpdateService.ApplyIfNewerAsync(hb.Update))
                {
                    _tray.ShowBalloonTip(3000, "ROSI Print", "Update wird installiert…", ToolTipIcon.Info);
                    return; // App beendet sich gleich (Neustart via Batch)
                }
            }

            await PollAndPrint();
        }
        catch (AgentUnauthorizedException)
        {
            // Token vom Server abgelehnt -> verwerfen und automatisch neu verbinden
            // (per enroll.json bzw. Self-Register).
            _config.Token = null;
            _config.Save();
            ApplyConfig();
            _connectTick = 0;
            SetStatus("Token ungueltig — verbinde neu…", IconFactory.Idle);
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

    /// <summary>Automatischer Verbindungsaufbau (alle ~5 s), solange kein Token da ist.</summary>
    private async Task TryConnectOnce()
    {
        _connectTick++;
        if (_connectTick % 5 != 1 || _connector == null)
        {
            return;
        }

        var installId = _config.InstallId ?? "";
        var hostname = Environment.MachineName;
        List<string> printers;
        try { printers = await PrinterInventory.AllAsync(_dymo); }
        catch { printers = new List<string>(); }

        try
        {
            // 1) Stations-Installer: enroll.json mit Token -> sofort verbinden.
            var enroll = EnrollFile.Load();
            if (!string.IsNullOrWhiteSpace(enroll?.EnrollToken))
            {
                var data = await _connector.EnrollAsync(enroll!.EnrollToken!, installId, hostname, printers);
                if (!string.IsNullOrWhiteSpace(data?.Token))
                {
                    SaveToken(data!.Token!);
                    SetStatus($"Verbunden mit {data.Station}", IconFactory.Ready);
                    return;
                }
            }

            // 2) Self-Register: nach Freigabe das Token abholen.
            if (!string.IsNullOrWhiteSpace(_config.ClaimSecret))
            {
                var data = await _connector.ClaimTokenAsync(installId, _config.ClaimSecret!);
                if (!string.IsNullOrWhiteSpace(data?.Token))
                {
                    SaveToken(data!.Token!);
                    _config.ClaimSecret = null;
                    _config.Save();
                    SetStatus("Freigegeben — verbunden", IconFactory.Ready);
                    return;
                }

                SetStatus("Wartet auf Freigabe im Dashboard…", IconFactory.Warning);
                return;
            }

            // 3) Erstmals registrieren -> wartet danach auf Freigabe.
            var reg = await _connector.RegisterAsync(installId, hostname, printers);
            if (!string.IsNullOrWhiteSpace(reg?.ClaimSecret))
            {
                _config.ClaimSecret = reg!.ClaimSecret;
                _config.Save();
            }
            SetStatus("Wartet auf Freigabe im Dashboard…", IconFactory.Warning);
        }
        catch (Exception ex)
        {
            SetStatus($"Verbinde…: {Short(ex.Message)}", IconFactory.Idle);
        }
    }

    /// <summary>
    /// Token + Anmeldedaten verwerfen und sofort neu verbinden (liest die
    /// enroll.json frisch). Loest haengende 401-Zustaende ohne Datei-Loeschen.
    /// </summary>
    private void Reconnect()
    {
        _config.Token = null;
        _config.ClaimSecret = null;
        _config.Save();
        ApplyConfig();      // _api wird null -> naechster Tick verbindet neu
        _connectTick = 0;
        _tick = 0;
        SetStatus("Verbinde neu…", IconFactory.Idle);
        _tray.ShowBalloonTip(2000, "ROSI Print", "Token verworfen — verbinde neu…", ToolTipIcon.Info);
    }

    private void SaveToken(string token)
    {
        _config.Token = token;
        if (string.IsNullOrWhiteSpace(_config.ServerUrl))
        {
            _config.ServerUrl = _config.EffectiveServerUrl;
        }
        _config.Save();
        ApplyConfig();
        _tick = 0; // erzwingt sofort Heartbeat
    }

    private async Task CheckUpdateNow()
    {
        if (_api == null)
        {
            _tray.ShowBalloonTip(3000, "ROSI Print", "Noch nicht verbunden.", ToolTipIcon.Warning);
            return;
        }

        try
        {
            var printers = await PrinterInventory.AllAsync(_dymo);
            var hb = await _api.HeartbeatAsync(printers);
            if (hb?.Update != null && hb.Update.VersionCode > Program.VersionCode)
            {
                _tray.ShowBalloonTip(3000, "ROSI Print", $"Update {hb.Update.Version} wird installiert…", ToolTipIcon.Info);
                await UpdateService.ApplyIfNewerAsync(hb.Update);
            }
            else
            {
                _tray.ShowBalloonTip(3000, "ROSI Print", "Aktuell – kein Update verfuegbar.", ToolTipIcon.Info);
            }
        }
        catch (Exception ex)
        {
            SetStatus($"Update-Check: {Short(ex.Message)}", IconFactory.Error);
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

        // DYMO-Druckernamen einmal holen, um DYMO vs. Windows-Drucker zu unterscheiden.
        var dymoNames = await _dymo.GetPrinterNamesAsync();

        foreach (var job in jobs)
        {
            try
            {
                var total = job.Labels.Count;
                var target = job.PrinterName;
                var isDymo = string.IsNullOrWhiteSpace(target)
                    || dymoNames.Any(n => n.Equals(target, StringComparison.OrdinalIgnoreCase));
                var isTest = string.Equals(job.Reference, "TESTDRUCK", StringComparison.OrdinalIgnoreCase);

                // Bremsen, wenn der Server es verlangt (Nachdrucke) ODER bei
                // groesseren Mengen — sonst ueberlaeuft der Spooler (leere Labels).
                var pace = job.Pace || total > PaceThreshold;

                // ── Nicht-DYMO-Drucker ──
                if (!isDymo)
                {
                    if (TscPrinting.IsTsc(target))
                    {
                        // TSC-Thermodrucker: Testetikett generieren ODER das vom Server
                        // gelieferte TSPL (Gutschein etc.) roh an den Drucker senden.
                        if (isTest)
                        {
                            SetStatus($"Druckt Testetikett: {target}…", IconFactory.Printing);
                            TscPrinting.PrintTestLabel(target!);
                        }
                        else
                        {
                            // ALLE Etiketten in EINEM Druckjob buendeln — viele einzelne
                            // Roh-Jobs ueberfordern den TSC-Spooler (einzelne schlagen fehl).
                            SetStatus(
                                total > 1 ? $"Druckt {total} Etiketten: {job.Reference}…" : $"Druckt: {job.Reference}…",
                                IconFactory.Printing);

                            var sb = new System.Text.StringBuilder();
                            foreach (var label in job.Labels)
                            {
                                sb.Append(label.Xml);
                            }
                            // € (U+20AC) -> Byte 0x80, damit es ueber CODEPAGE 1252 als € kommt.
                            var tspl = sb.ToString().Replace(((char)0x20AC).ToString(), ((char)0x80).ToString());
                            RawPrinterHelper.SendBytesToPrinter(
                                target!, System.Text.Encoding.Latin1.GetBytes(tspl));
                        }
                        await _api.AckAsync(job.Id, true, null);
                        SetStatus($"Gedruckt: {job.Reference ?? job.JobType}", IconFactory.Ready);
                        continue;
                    }

                    // Brother & andere: nur Testseite ueber den Windows-Spooler.
                    if (!isTest)
                    {
                        throw new Exception($"Etiketten-Druck auf '{target}' wird noch nicht unterstuetzt (nur DYMO + TSC).");
                    }
                    SetStatus($"Druckt Testseite: {target}…", IconFactory.Printing);
                    WindowsPrinting.PrintTestPage(target!, BuildTestLines(target!));
                    await _api.AckAsync(job.Id, true, null);
                    SetStatus($"Testseite gedruckt: {target}", IconFactory.Ready);
                    continue;
                }

                for (int i = 0; i < total; i++)
                {
                    SetStatus(
                        total > 1
                            ? $"Druckt {i + 1}/{total}: {job.Reference ?? job.JobType}…"
                            : $"Druckt: {job.Reference ?? job.JobType}…",
                        IconFactory.Printing);

                    await _dymo.PrintAsync(target, job.Labels[i].Xml);

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

    /// <summary>Inhalt der Testseite fuer Nicht-DYMO-Drucker (Windows-Spooler).</summary>
    private IEnumerable<string> BuildTestLines(string printer) => new[]
    {
        "==========================================",
        "          ROSI Print - Testseite",
        "==========================================",
        "",
        $"Drucker:   {printer}",
        $"Datum:     {DateTime.Now:dd.MM.yyyy HH:mm:ss}",
        $"Server:    {_config.EffectiveServerUrl}",
        "",
        "Dieser Testdruck wurde erfolgreich ueber",
        "den ROSI-Print-Agenten gesendet.",
        "",
        "==========================================",
    };

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
