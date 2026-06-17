; Inno Setup Skript fuer ROSI Print (Tray-Agent)
; Kompilieren:  "%LOCALAPPDATA%\Programs\Inno Setup 6\ISCC.exe" installer.iss
; Ergebnis:     installer-output\ROSI-Print-Setup.exe

#define MyAppName "ROSI Print"
#define MyAppVersion "1.1.2"
#define MyAppPublisher "ROSI"
#define MyAppExe "RosiPrintAgent.exe"

[Setup]
AppId={{8F3C1B7A-9D24-4E51-9C3A-ROSIPRINT001}}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
DefaultDirName={localappdata}\RosiPrintAgent
DefaultGroupName={#MyAppName}
DisableProgramGroupPage=yes
PrivilegesRequired=lowest
OutputDir=installer-output
OutputBaseFilename=ROSI-Print-Setup
Compression=lzma2/max
SolidCompression=yes
WizardStyle=modern
AppMutex=RosiPrintAgent_SingleInstance
UninstallDisplayName={#MyAppName}
ArchitecturesInstallIn64BitMode=x64compatible

[Languages]
Name: "de"; MessagesFile: "compiler:Languages\German.isl"

[Files]
Source: "bin\Release\net8.0-windows\win-x64\publish\RosiPrintAgent.exe"; DestDir: "{app}"; Flags: ignoreversion

[Registry]
; Autostart mit Windows (pro Benutzer, kein Admin noetig)
Root: HKCU; Subkey: "Software\Microsoft\Windows\CurrentVersion\Run"; ValueType: string; \
    ValueName: "RosiPrintAgent"; ValueData: """{app}\{#MyAppExe}"""; Flags: uninsdeletevalue

[Icons]
Name: "{userprograms}\{#MyAppName}"; Filename: "{app}\{#MyAppExe}"

[Run]
Filename: "{app}\{#MyAppExe}"; Description: "ROSI Print jetzt starten"; Flags: nowait postinstall skipifsilent

[Code]
// Laufende Instanz vor der Installation beenden (sonst ist die EXE gesperrt).
function PrepareToInstall(var NeedsRestart: Boolean): String;
var
  ResultCode: Integer;
begin
  Exec('taskkill.exe', '/im RosiPrintAgent.exe /f', '', SW_HIDE, ewWaitUntilTerminated, ResultCode);
  Result := '';
end;
