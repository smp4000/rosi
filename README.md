<p align="center">
  <img src="https://img.shields.io/badge/ROSI-SaaS%20Platform-0ea5e9?style=for-the-badge&logoColor=white" alt="ROSI">
</p>

<h1 align="center">ROSI - Tankstellenpartner-Verwaltung</h1>

<p align="center">
  Mandantenfaehige SaaS-Plattform fuer die digitale Verwaltung von Tankstellen, Mitarbeitern, Kunden und Lieferanten.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 12">
  <img src="https://img.shields.io/badge/Filament-5-FBBF24?style=flat-square" alt="Filament 5">
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.2+">
  <img src="https://img.shields.io/badge/Livewire-3-FB70A9?style=flat-square" alt="Livewire 3">
  <img src="https://img.shields.io/badge/Tailwind-4-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white" alt="Tailwind CSS 4">
</p>

---

## Ueberblick

ROSI ist eine mandantenfaehige SaaS-Plattform, die Tankstellenpartnern eine zentrale Loesung fuer ihre taeglichen Verwaltungsaufgaben bietet. Jeder Partner (Mandant) verwaltet seine Tankstellen, Mitarbeiter, Kunden und Lieferanten in einem eigenen, abgesicherten Bereich.

### Kernfunktionen

- **Tankstellen-Verwaltung** - 7-Tab-Formulare mit Stammdaten, Oeffnungszeiten, Bankkonten, interaktiver Karte (Leaflet/OpenStreetMap)
- **Stations-Import** - Wizard mit PLZ-Suche, automatischer Import von Stationsdaten inkl. Adresse, Marke und Oeffnungszeiten
- **Mitarbeiter-Verwaltung** - Profile, Vertraege, Dokumente, Schichtplanung
- **Multi-Tenancy** - Shared Database mit `tenant_id`, vollstaendige Datenisolation
- **Rollen & Rechte** - Feingranulares Berechtigungssystem mit spatie/laravel-permission
- **DSGVO-konform** - Audit-Logs, Consent-Tracking, Datenloeschungsantraege, verschluesselte sensible Felder

## Architektur

```
ROSI
├── Admin-Panel (/admin)          Super-Admin: Mandanten, User, Audit-Logs, Marken
├── Partner-Panel (/dashboard)    Partner: Tankstellen, Mitarbeiter, Widgets
├── Portal (/portal)              Mitarbeiter-Zugang (geplant)
└── API                           REST-Schnittstelle (geplant)
```

### Tech-Stack

| Komponente | Technologie |
|-----------|-------------|
| Backend | Laravel 12, PHP 8.2+ |
| Admin UI | Filament 5 (Admin + Partner Panels) |
| Frontend | Livewire 3, Alpine.js, Tailwind CSS 4 |
| Datenbank | MySQL / MariaDB |
| IDs | UUID v7 (zeitbasiert, sortierbar) |
| Berechtigungen | spatie/laravel-permission v6 (Teams) |
| Assets | Vite 7 |
| Karten | Leaflet + OpenStreetMap + Nominatim |

### Datenmodell

```
Tenant (Mandant)
 ├── User (mit Rollen)
 ├── GasStation (Tankstelle)
 │    ├── GasStationBankAccount
 │    └── Brand (global)
 ├── EmployeeProfile
 │    ├── Shift / ShiftTemplate
 │    └── Document
 ├── Customer
 ├── Supplier
 │    ├── SupplierPriceList
 │    └── SupplierComplaint
 ├── AuditLog
 ├── ConsentLog
 └── DataDeletionRequest
```

## Installation

### Voraussetzungen

- PHP 8.2+
- Composer
- Node.js 18+ & npm
- MySQL 8+ oder MariaDB 10.6+

### Setup

```bash
# Repository klonen
git clone https://github.com/smp4000/rosi.git
cd rosi

# Abhaengigkeiten installieren
composer install
npm install

# Umgebung konfigurieren
cp .env.example .env
php artisan key:generate

# .env anpassen: DB_DATABASE, DB_USERNAME, DB_PASSWORD

# Datenbank migrieren & seeden
php artisan migrate
php artisan db:seed

# Assets bauen
npm run build
```

### Entwicklung starten

```bash
# Alle Services gleichzeitig starten (Server, Queue, Vite, Logs)
composer run dev
```

Oder einzeln:

```bash
php artisan serve          # Laravel Dev-Server
npm run dev                # Vite HMR
php artisan queue:listen   # Queue Worker
```

### Zugang

| Panel | URL | Rolle |
|-------|-----|-------|
| Admin | `/admin` | Super-Admin |
| Partner | `/dashboard` | Partner |

## Projektstruktur

```
app/
├── Console/Commands/              # Artisan-Befehle
├── Filament/
│   ├── Resources/                 # Admin-Panel (Tenant, User, AuditLog, Brand)
│   ├── Partner/Resources/         # Partner-Panel (GasStation, Employee)
│   └── Widgets/                   # Dashboard-Widgets
├── Http/Middleware/                # Tenant-Scoping, Auth
├── Livewire/                      # Livewire-Komponenten
├── Models/                        # 20 Eloquent Models (UUID v7)
├── Services/                      # BenzinpreisService (Stations-Import)
└── Listeners/                     # Event-Listener

database/migrations/               # 20+ Migrationen
lang/de/                           # Deutsche Uebersetzungen
resources/views/filament/          # Blade-Komponenten (Karte, Oeffnungszeiten)
docs/PFLICHTENHEFT.md              # Detailliertes Pflichtenheft
```

## Tankstellen-Import (BenzinpreisService)

Der Wizard-basierte Import ermoeglicht es, bestehende Tankstellen per PLZ-Suche zu finden und deren Daten automatisch zu uebernehmen:

1. **PLZ eingeben** - Nominatim (OpenStreetMap) loest die PLZ in Stadt + Koordinaten auf
2. **Umkreissuche** - Stadtseiten + Nachbarstaedte werden parallel geladen
3. **Geo-Validierung** - Haversine-Distanzpruefung filtert gleichnamige Staedte in anderen Regionen
4. **Daten importieren** - Name, Marke, Adresse, Koordinaten, Oeffnungszeiten werden uebernommen

## Konventionen

- **Sprache**: Code-Attribute auf Englisch, Kommentare auf Deutsch, UI auf Deutsch
- **IDs**: UUID v7 fuer alle Kern-Entitaeten
- **Multi-Tenancy**: `BelongsToTenant` Trait + Global Scope
- **Formulare**: Tab-basiert mit Filament Tabs
- **Dateien**: PSR-4 Autoloading, Laravel-Konventionen

## Lizenz

Proprietaer - Alle Rechte vorbehalten.
