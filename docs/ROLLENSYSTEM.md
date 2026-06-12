# Rollensystem — Funktionsweise & Anleitung

Stand: 13.06.2026

## Das Prinzip in einem Satz

**Definiert** wird im Katalog (eine Datei) → **verteilt** per Sync (ein Befehl) →
**verwaltet** in der Matrix (Einstellungen → Rollen & Rechte) → **geprüft** überall
automatisch über denselben Namen `bereich.ressource.aktion`.

## Die Bausteine

| Baustein | Datei | Aufgabe |
|---|---|---|
| Katalog | `config/permission-katalog.php` | EINZIGE Stelle, an der Rechte definiert werden |
| Sync | `app/Console/Commands/SyncPermissionCatalog.php` | `rosi:permissions-sync` — Katalog → DB, idempotent |
| Matrix-UI | `app/Filament/Partner/Pages/RoleMatrixPage.php` | Checkboxen = `role_has_permissions` bearbeiten |
| Resource-Trait | `app/Filament/Concerns/HasCatalogPermissions.php` | canViewAny/canCreate/… aus dem Katalog |
| Page-Trait | `app/Filament/Concerns/HasPageCatalogPermission.php` | canAccess für Custom-Pages |
| Widget-Trait | `app/Filament/Concerns/HasWidgetCatalogPermission.php` | Dashboard-Widgets einzeln schaltbar |
| Button-Gating | `app/Providers/AppServiceProvider.php` | Standard-Buttons folgen automatisch can*() |
| Stations-Rollen | `app/Models/EmployeeStationRole.php` | Mitarbeiter × Rolle × Tankstelle (NULL = Betrieb) |
| Aufloesung | `app/Models/User.php` → `permissionsForStation()` | Rechte fuer die Station des MDE-Geraets |
| API-Schutz | `app/Http/Middleware/EnsureMdePermission.php` | `->middleware('mde.permission:…')` an Routen |
| App-Filter | rosi_app `HomeScreen.kt` → `modulePermissions` | Kacheln/Drawer ausblenden (Kosmetik, Server erzwingt) |

## Was steuert welche Aktion?

| Aktion (Key) | Label in der Matrix | Steuert |
|---|---|---|
| `list` | Liste ansehen | **Menüpunkt** + Übersichtstabelle. Ohne `list` ist das Modul unsichtbar |
| `view` | Details ansehen | „Ansehen"-Button + Detailseite |
| `create` | Anlegen | „Anlegen"-Button + Anlegen-Seite |
| `edit` | Bearbeiten | „Bearbeiten"-Button + Bearbeiten-Seite |
| `delete` | Löschen / Archivieren | Lösch-/Archiv-Button pro Zeile |
| `delete-any` | Mehrere löschen (Auswahl) | Sammel-Löschen (Bulk) |
| `restore` | Wiederherstellen (aus Archiv) | Restore-Button bei archivierten Einträgen |
| `force-delete` | Endgültig löschen | Force-Delete (falls vorhanden) |
| frei waehlbar | z. B. „Versenden", „Freigeben" | Spezial-Buttons / MDE-Kacheln |

Fallbacks: Hat eine Ressource kein `list`, nimmt der Trait für den Menüpunkt
automatisch `view`, `use`, `manage` oder `logs`. Ist `delete-any`/`restore`/
`force-delete` nicht definiert, gilt `delete`.

## Rezept: Neue Resource anlegen (z. B. Lieferanten)

### 1. Katalog-Eintrag

```php
// config/permission-katalog.php → 'ressourcen'
'partner.suppliers' => [
    'label' => 'Lieferanten', 'bereich' => 'dashboard', 'emoji' => '🚚',
    'aktionen' => [
        'list'   => 'Liste ansehen',
        'create' => 'Anlegen',
        'edit'   => 'Bearbeiten',
        'delete' => 'Loeschen',
        'delete-any' => 'Mehrere loeschen (Auswahl)', // nur bei Bulk-Loeschen
    ],
    'defaults' => [
        'buero' => ['list', 'create', 'edit'],  // Werkseinstellung je System-Rolle
    ],
],
```

### 2. Resource: Trait + Key (2 Zeilen)

```php
class SupplierResource extends Resource
{
    use \App\Filament\Concerns\HasCatalogPermissions;

    protected static ?string $permissionKey = 'partner.suppliers';
    // ... form(), table(), pages wie immer
}
```

### 3. Deploy

```bash
php artisan rosi:permissions-sync
```

Fertig: Matrix-Zeile, DB-Permissions, Defaults, Menü-/Button-Ausblendung und
403 bei URL-Direktaufruf passieren automatisch.

### Sonderfaelle (je 1 Zeile)

**Eigener Spezial-Button:**

```php
Action::make('preisliste_import')
    ->visible(fn () => static::katalogPermission('import')),
// + 'import' => 'Preisliste importieren' im Katalog ergaenzen
```

**MDE-Modul:** Katalog-Block mit `'bereich' => 'mde'`, an die API-Route
`->middleware('mde.permission:mde.xyz.aktion')`, in der App eine Zeile im
`modulePermissions`-Mapping (HomeScreen.kt).

**Custom-Page:**

```php
use \App\Filament\Concerns\HasPageCatalogPermission;
protected static string $accessPermission = 'partner.suppliers.list';
```

## Sync-Verhalten (wichtig!)

- **Neue Permissions** bekommen ihre Defaults automatisch — auch bei bestehenden
  Mandanten. Bestehende Anpassungen der Partner werden NIE überschrieben.
- `--apply-defaults`: Werkseinstellungen erneut ADDITIV anwenden (nur Erst-Rollout).
- `--prune`: verwaiste Permissions löschen (werden sonst nur gemeldet).
- Inhaber-Rolle `partner` bekommt immer ALLE Katalog-Permissions.
- Eigene Rollen der Partner bekommen neue Permissions grundsätzlich NICHT
  automatisch (sicherer Standard — Partner schaltet bewusst frei).

## Stolperfallen

- Katalog-Keys enthalten Punkte → NIE `config('permission-katalog.ressourcen.partner.x')`
  per Dot-Notation; immer erst `config('permission-katalog.ressourcen')` als Array holen.
- NIE `canAccess() { return true; }` o. ä. in eine Resource schreiben — Klassen-Methoden
  übersteuern den Trait (genau so entstanden die Lecks bei Artikel/Artikelgruppen).
- Rolle „Mitarbeiter (nur Web-Ansicht)" ist die Legacy-Leserolle OHNE MDE-Module —
  normales Stationspersonal bekommt **Kassierer**.
- Rechte-Änderungen wirken im Web sofort, in der MDE-App beim nächsten Login
  (App meldet sich nach 5 Min Inaktivität ohnehin ab).
