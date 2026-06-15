using System.Windows.Forms;

namespace RosiPrintAgent;

/// <summary>Kleiner Dialog fuer Server-URL + Agent-Token.</summary>
public class SettingsForm : Form
{
    private readonly TextBox _serverBox;
    private readonly TextBox _tokenBox;

    public string ServerUrl => _serverBox.Text.Trim();
    public string Token => _tokenBox.Text.Trim();

    public SettingsForm(AppConfig config)
    {
        Text = "ROSI Print — Einstellungen";
        FormBorderStyle = FormBorderStyle.FixedDialog;
        StartPosition = FormStartPosition.CenterScreen;
        MaximizeBox = false;
        MinimizeBox = false;
        ClientSize = new Size(440, 200);

        var serverLabel = new Label { Text = "Server-URL", Left = 16, Top = 18, Width = 400 };
        _serverBox = new TextBox
        {
            Left = 16, Top = 40, Width = 408,
            Text = config.ServerUrl ?? "https://rosi.aral-welle.com",
        };

        var tokenLabel = new Label { Text = "Agent-Token", Left = 16, Top = 78, Width = 400 };
        _tokenBox = new TextBox
        {
            Left = 16, Top = 100, Width = 408,
            Text = config.Token ?? "",
            UseSystemPasswordChar = false,
        };

        var ok = new Button { Text = "Speichern", Left = 248, Top = 150, Width = 80, DialogResult = DialogResult.OK };
        var cancel = new Button { Text = "Abbrechen", Left = 344, Top = 150, Width = 80, DialogResult = DialogResult.Cancel };

        ok.Click += (_, _) =>
        {
            if (ServerUrl.Length == 0 || Token.Length == 0)
            {
                MessageBox.Show("Bitte Server-URL und Agent-Token angeben.", "ROSI Print",
                    MessageBoxButtons.OK, MessageBoxIcon.Warning);
                DialogResult = DialogResult.None;
            }
        };

        Controls.AddRange(new Control[] { serverLabel, _serverBox, tokenLabel, _tokenBox, ok, cancel });
        AcceptButton = ok;
        CancelButton = cancel;
    }
}
