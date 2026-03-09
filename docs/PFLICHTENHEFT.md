# ROSI - Pflichtenheft & Leitfaden
## Tankstellen-Partner-Management-Plattform

---

## 1. Kontext & Vision

**Problem:** Tankstellenpartner brauchen ein zentrales, digitales System zur Verwaltung ihrer Tankstellen, Mitarbeiter, Kunden und Lieferanten. Aktuell fehlt eine branchenspezifische SaaS-Loesung die mandantenfaehig, modern und KI-gestuetzt arbeitet.

**Ziel:** Eine mandantenfaehige SaaS-Plattform, auf der sich Tankstellenpartner registrieren, 14 Tage testen und danach ihre Tankstellen vollstaendig digital verwalten koennen. Spaeter als Android-App (PWA) verfuegbar.

---

## 2. Tech-Stack

| Komponente | Technologie |
|---|---|
| Backend | Laravel 12, PHP 8.2+ |
| Frontend | Livewire 3 + Tailwind CSS + Alpine.js |
| Admin (Super-Admin) | Filament 5 (separates Panel) |
| Datenbank | MySQL/MariaDB (XAMPP) |
| Multi-Tenancy | Shared DB mit `tenant_id` (eigene Implementierung mit Global Scopes) |
| Rollen/Rechte | spatie/laravel-permission v7 |
| PDF | spatie/laravel-pdf (Browsershot-Driver) |
| E-Signatur | laravel-sign-pad (creagia/laravel-sign-pad) |
| Zahlungen | Mollie (SEPA, PayPal, Ueberweisung) - spaeter |
| KI | OpenAI API (GPT) fuer Dokumentengenerierung + Chat-Assistent |
| Mobile | PWA (Progressive Web App) - spaeter als Android-App installierbar |
| Echtzeit | Laravel Reverb / Echo (Websockets fuer Notifications) |

---

## 3. Benutzerrollen-Hierarchie

```
Super-Admin (Plattform-Betreiber)
  |-- Sieht alle Mandanten, Statistiken, Abrechnungen
  |-- Filament 5 Admin Panel (/admin)
  |
Tankstellenpartner (Mandant/Tenant)
  |-- Volle Kontrolle ueber seinen Mandanten
  |-- Kann eigene Rollen & Permissions erstellen
  |-- Dashboard unter Haupt-Domain (/dashboard)
  |
Mitarbeiter (vom Partner angelegt)
  |-- Rechte werden vom Partner ueber Rollen gesteuert
  |-- Sieht nur zugewiesene Tankstellen
  |-- Eigenes Portal (/portal)
  |
Kunden & Lieferanten
  |-- Vom Partner verwaltet (CRM)
  |-- Kein eigener Login (vorerst)
```

---

## 4. Datenbank-Architektur (Shared DB + tenant_id)

### UUID-Strategie

Die folgenden Kern-Tabellen verwenden **UUID v7** (zeitbasiert, sortierbar) als Primaerschluessel:
- `tenants`, `users`, `gas_stations`, `customers`, `suppliers`
- `documents`, `document_templates`, `employee_profiles`
- `invitations`, `shifts`, `shift_templates`

Interne/Log-Tabellen behalten `bigint auto-increment`:
- `audit_logs`, `consent_logs`, `data_deletion_requests`
- `communication_logs`, `ai_chat_messages`, `notifications`
- Alle Pivot-Tabellen (`gas_station_user`, `customer_gas_station`, etc.)
- `supplier_price_lists`, `supplier_complaints`

**Laravel-Implementierung:** `HasUuids` Trait + `$keyType = 'string'` + `$incrementing = false`
```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Tenant extends Model
{
    use HasUuids;
    // Laravel 12 generiert automatisch UUID v7
}
```

**Migration:** `$table->uuid('id')->primary()` statt `$table->id()`
**Foreign Keys:** `$table->foreignUuid('tenant_id')->constrained()`

---

### Detaillierte Tabellen-Spezifikation

#### tenants (Mandanten)
| Feld | Typ | Beschreibung |
|---|---|---|
| id | uuid, PK | UUID v7 (zeitbasiert, sortierbar) |
| name | varchar(255) | Firmenname des Partners |
| slug | varchar(255), unique | URL-freundlicher Name |
| owner_id | FK users (uuid) | Inhaber/Ersteller |
| email | varchar(255) | Firmen-E-Mail |
| phone | varchar(50) | Telefon |
| street | varchar(255) | Strasse |
| zip | varchar(10) | PLZ |
| city | varchar(255) | Ort |
| country | varchar(2), default 'DE' | Laendercode |
| logo | varchar(255), nullable | Pfad zum Logo |
| tax_id | varchar(50), nullable | Steuernummer |
| trade_register | varchar(100), nullable | Handelsregisternummer |
| trial_ends_at | datetime | Ende der 14-Tage-Testphase |
| subscription_status | enum: trial, active, expired, cancelled | Abo-Status |
| subscription_plan | varchar(50), nullable | Gewaehltes Preismodell |
| settings | JSON | Mandanten-spezifische Einstellungen |
| is_active | boolean, default true | Aktiv/Gesperrt |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp, nullable | Soft Delete |

#### users
| Feld | Typ | Beschreibung |
|---|---|---|
| id | uuid, PK | UUID v7 |
| tenant_id | FK tenants (uuid), nullable | NULL = Super-Admin |
| first_name | varchar(255), nullable | Vorname (optional, z.B. bei Firmen leer) |
| last_name | varchar(255) | Nachname / Firmenname (Pflichtfeld) |
| name | varchar(255), virtual/accessor | Automatisch: first_name + ' ' + last_name (Laravel Accessor, nicht in DB) |
| email | varchar(255), unique | |
| password | varchar(255) | Bcrypt-Hash |
| email_verified_at | timestamp, nullable | E-Mail bestaetigt |
| type | enum: super_admin, partner, employee, customer | Benutzertyp |
| avatar | varchar(255), nullable | Profilbild-Pfad |
| phone | varchar(50), nullable | |
| is_active | boolean, default true | |
| locale | varchar(5), default 'de' | Sprache |
| last_login_at | timestamp, nullable | Letzter Login |
| two_factor_secret | text, nullable, encrypted | 2FA Secret |
| two_factor_confirmed_at | timestamp, nullable | |
| remember_token | varchar(100), nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp, nullable | Soft Delete |

**Hinweis `name`:** Wird als Laravel Accessor berechnet:
```php
// Automatisch zusammengesetzt, kein DB-Feld
public function getNameAttribute(): string
{
    return trim($this->first_name . ' ' . $this->last_name);
}
```

**Verknuepfungen vom User-Model:**
- User (type=partner) -> hasOne Tenant (als owner)
- User (type=employee) -> hasOne EmployeeProfile
- User (type=customer) -> hasOne Customer (optionaler Login fuer Kunden spaeter)
- User -> belongsToMany GasStation (Pivot: gas_station_user)
- User -> hasMany Document
- User -> hasMany AiChatMessage
- User -> morphMany AuditLog

#### gas_stations (Tankstellen)
| Feld | Typ | Beschreibung |
|---|---|---|
| id | uuid, PK | UUID v7 |
| tenant_id | FK tenants (uuid) | |
| name | varchar(255) | Tankstellenname |
| brand | varchar(100), nullable | Marke (Aral, Shell, frei, etc.) |
| station_number | varchar(50), nullable | Interne Stationsnummer |
| street | varchar(255) | Strasse |
| zip | varchar(10) | PLZ |
| city | varchar(255) | Ort |
| state | varchar(100), nullable | Bundesland |
| country | varchar(2), default 'DE' | |
| latitude | decimal(10,8), nullable | Breitengrad |
| longitude | decimal(11,8), nullable | Laengengrad |
| phone | varchar(50), nullable | |
| fax | varchar(50), nullable | |
| email | varchar(255), nullable | |
| tax_id | varchar(50), nullable | Steuernummer der Tankstelle |
| trade_register | varchar(100), nullable | |
| num_pumps | integer, nullable | Anzahl Zapfsaeulen |
| has_shop | boolean, default false | Hat Shop |
| has_car_wash | boolean, default false | Hat Waschanlage |
| opening_hours | JSON | Oeffnungszeiten (Mo-So) |
| services | JSON | Verfuegbare Services (LPG, AdBlue, etc.) |
| logo | varchar(255), nullable | Tankstellen-Logo/Foto |
| photos | JSON, nullable | Weitere Fotos (Array von Pfaden) |
| notes | text, nullable | Interne Notizen |
| is_active | boolean, default true | |
| settings | JSON, nullable | Stations-spezifische Einstellungen |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp, nullable | |

#### gas_station_user (Pivot: Mitarbeiter <-> Tankstelle)
| Feld | Typ | Beschreibung |
|---|---|---|
| id | bigint, PK | |
| gas_station_id | FK gas_stations (uuid) | |
| user_id | FK users (uuid) | |
| station_role | varchar(50), nullable | Zusaetzliche Rolle an dieser Station |
| is_primary | boolean, default false | Haupt-Tankstelle des Mitarbeiters |
| assigned_at | timestamp | Zuweisungsdatum |
| created_at | timestamp | |
| updated_at | timestamp | |

#### employee_profiles (Personalbogen - Erweitert)
| Feld | Typ | Beschreibung |
|---|---|---|
| id | bigint, PK | |
| user_id | FK users (uuid) | |
| tenant_id | FK tenants | |
| **--- Persoenliche Daten ---** | | |
| date_of_birth | date, encrypted | Geburtsdatum |
| place_of_birth | varchar(255), nullable | Geburtsort |
| gender | enum: male, female, diverse, nullable | |
| marital_status | enum: single, married, divorced, widowed, nullable | Familienstand |
| nationality | varchar(100) | Staatsangehoerigkeit |
| second_nationality | varchar(100), nullable | Zweite Staatsangehoerigkeit |
| residence_permit_type | varchar(100), nullable | Art des Aufenthaltstitels |
| residence_permit_expires | date, nullable | Ablaufdatum Aufenthaltstitel |
| residence_permit_file | varchar(255), nullable | Scan des Aufenthaltstitels |
| **--- Adresse ---** | | |
| street | varchar(255) | |
| zip | varchar(10) | |
| city | varchar(255) | |
| country | varchar(2), default 'DE' | |
| **--- Steuer & Sozialversicherung ---** | | |
| tax_id | varchar(20), encrypted | Steuerliche Identifikationsnummer |
| tax_class | enum: 1,2,3,4,5,6, nullable | Steuerklasse |
| church_tax | boolean, default false | Kirchensteuerpflichtig |
| denomination | varchar(50), nullable | Konfession |
| social_security_number | varchar(20), encrypted | SV-Nummer |
| health_insurance_name | varchar(255), nullable | Name der Krankenkasse |
| health_insurance_type | enum: gesetzlich, privat, nullable | |
| health_insurance_number | varchar(50), nullable, encrypted | Versichertennummer |
| pension_insurance | boolean, default true | Rentenversicherungspflichtig |
| **--- Bankverbindung ---** | | |
| bank_name | varchar(255), encrypted | |
| iban | varchar(34), encrypted | |
| bic | varchar(11), encrypted | |
| account_holder | varchar(255), encrypted | Kontoinhaber |
| **--- Notfallkontakt ---** | | |
| emergency_name | varchar(255), nullable | |
| emergency_phone | varchar(50), nullable | |
| emergency_relation | varchar(100), nullable | Beziehung (Ehepartner, Eltern, etc.) |
| **--- Kinder ---** | | |
| num_children | integer, default 0 | Anzahl Kinder |
| children_data | JSON, nullable, encrypted | Details: Name, Geburtsdatum pro Kind |
| child_allowance | boolean, default false | Kindergeldberechtigt |
| **--- Qualifikationen ---** | | |
| education_level | varchar(100), nullable | Hoechster Bildungsabschluss |
| professional_training | varchar(255), nullable | Berufsausbildung |
| drivers_license | JSON, nullable | Fuehrerscheinklassen (Array: B, C, CE, etc.) |
| drivers_license_expiry | date, nullable | Ablaufdatum |
| certifications | JSON, nullable | Zertifikate/Schulungen (Array) |
| safety_training_date | date, nullable | Letzte Sicherheitsunterweisung |
| first_aid_training_date | date, nullable | Letzte Erste-Hilfe-Schulung |
| hazmat_training_date | date, nullable | Gefahrgut-Schulung |
| **--- Arbeitsmedizin ---** | | |
| medical_exam_date | date, nullable | Letzte arbeitsmedizinische Untersuchung |
| medical_exam_next | date, nullable | Naechste Untersuchung faellig |
| medical_restrictions | text, nullable, encrypted | Einschraenkungen |
| health_certificate | boolean, default false | Gesundheitszeugnis vorhanden |
| health_certificate_date | date, nullable | Ausstellungsdatum |
| **--- Beschaeftigung ---** | | |
| employment_start | date, nullable | Eintrittsdatum |
| employment_end | date, nullable | Austrittsdatum |
| employment_type | enum: full_time, part_time, mini_job, intern, nullable | |
| weekly_hours | decimal(4,1), nullable | Wochenstunden |
| probation_end | date, nullable | Ende der Probezeit |
| vacation_days | integer, nullable | Jahresurlaub |
| salary | decimal(10,2), nullable, encrypted | Bruttogehalt |
| salary_type | enum: monthly, hourly, nullable | Gehaltstyp |
| **--- Status ---** | | |
| status | enum: pending, incomplete, complete, archived | Bogen-Status |
| completed_at | timestamp, nullable | Wann vollstaendig ausgefuellt |
| notes | text, nullable | Interne Notizen |
| created_at | timestamp | |
| updated_at | timestamp | |

#### customers (Kunden - B2B + B2C)
| Feld | Typ | Beschreibung |
|---|---|---|
| id | uuid, PK | UUID v7 |
| tenant_id | FK tenants (uuid) | |
| customer_number | varchar(50) | Eindeutige Kundennummer |
| customer_type | enum: b2b, b2c | Geschaefts- oder Privatkunde |
| **--- B2B Felder ---** | | |
| company_name | varchar(255), nullable | Firmenname |
| vat_id | varchar(50), nullable | USt-IdNr |
| trade_register | varchar(100), nullable | Handelsregisternummer |
| industry | varchar(100), nullable | Branche |
| company_size | varchar(50), nullable | Unternehmensgroesse |
| website | varchar(255), nullable | |
| **--- Kontaktperson / B2C Person ---** | | |
| salutation | enum: herr, frau, divers, nullable | Anrede |
| first_name | varchar(255) | |
| last_name | varchar(255) | |
| position | varchar(100), nullable | Position/Funktion (B2B) |
| email | varchar(255), nullable | |
| phone | varchar(50), nullable | |
| mobile | varchar(50), nullable | |
| fax | varchar(50), nullable | |
| **--- Adresse ---** | | |
| street | varchar(255), nullable | |
| zip | varchar(10), nullable | |
| city | varchar(255), nullable | |
| country | varchar(2), default 'DE' | |
| **--- CRM Felder ---** | | |
| status | enum: lead, active, inactive, blocked | Kundenstatus |
| source | varchar(100), nullable | Herkunft (Empfehlung, Website, etc.) |
| payment_terms | varchar(100), nullable | Zahlungsbedingungen (z.B. 14 Tage netto) |
| credit_limit | decimal(10,2), nullable | Kreditlimit |
| discount_rate | decimal(5,2), nullable | Rabatt in % |
| customer_card_number | varchar(50), nullable | Kundenkartennummer |
| loyalty_points | integer, default 0 | Bonuspunkte |
| tags | JSON, nullable | Schlagwoerter |
| notes | text, nullable | |
| last_contact_at | timestamp, nullable | Letzter Kontakt |
| next_followup_at | timestamp, nullable | Naechste Wiedervorlage |
| assigned_to | FK users (uuid), nullable | Zustaendiger Mitarbeiter |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp, nullable | |

#### customer_gas_station (Pivot)
| Feld | Typ | Beschreibung |
|---|---|---|
| id | bigint, PK | |
| customer_id | FK customers (uuid) | |
| gas_station_id | FK gas_stations (uuid) | |
| created_at | timestamp | |

#### suppliers (Lieferanten - Erweitert)
| Feld | Typ | Beschreibung |
|---|---|---|
| id | uuid, PK | UUID v7 |
| tenant_id | FK tenants (uuid) | |
| supplier_number | varchar(50) | Eindeutige Lieferantennummer |
| **--- Firmendaten ---** | | |
| company_name | varchar(255) | |
| vat_id | varchar(50), nullable | USt-IdNr |
| trade_register | varchar(100), nullable | |
| website | varchar(255), nullable | |
| **--- Kontakt ---** | | |
| contact_salutation | enum: herr, frau, divers, nullable | |
| contact_first_name | varchar(255), nullable | |
| contact_last_name | varchar(255), nullable | |
| contact_position | varchar(100), nullable | |
| contact_email | varchar(255), nullable | |
| contact_phone | varchar(50), nullable | |
| contact_mobile | varchar(50), nullable | |
| **--- Adresse ---** | | |
| street | varchar(255), nullable | |
| zip | varchar(10), nullable | |
| city | varchar(255), nullable | |
| country | varchar(2), default 'DE' | |
| **--- Lieferanten-Details ---** | | |
| category | enum: fuel, shop, technology, cleaning, food, other | Kategorie |
| supply_types | JSON | Liefertypen (Benzin, Diesel, LPG, Shop-Ware, etc.) |
| **--- Vertrag ---** | | |
| contract_start | date, nullable | Vertragsbeginn |
| contract_end | date, nullable | Vertragsende |
| contract_auto_renew | boolean, default false | Automatische Verlaengerung |
| contract_notice_period | varchar(50), nullable | Kuendigungsfrist |
| contract_file | varchar(255), nullable | Vertragsdokument |
| payment_terms | varchar(100), nullable | Zahlungsbedingungen |
| minimum_order | decimal(10,2), nullable | Mindestbestellmenge/-wert |
| delivery_days | JSON, nullable | Liefertage (Array) |
| **--- Bewertung ---** | | |
| rating | tinyint, nullable | Bewertung 1-5 Sterne |
| rating_notes | text, nullable | Bewertungskommentar |
| **--- CRM ---** | | |
| status | enum: active, inactive, blocked, prospect | |
| tags | JSON, nullable | |
| notes | text, nullable | |
| last_contact_at | timestamp, nullable | |
| next_followup_at | timestamp, nullable | |
| assigned_to | FK users (uuid), nullable | Zustaendiger Mitarbeiter |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp, nullable | |

#### supplier_gas_station (Pivot)
| Feld | Typ | Beschreibung |
|---|---|---|
| id | bigint, PK | |
| supplier_id | FK suppliers (uuid) | |
| gas_station_id | FK gas_stations (uuid) | |
| created_at | timestamp | |

#### supplier_price_lists (Preislisten)
| Feld | Typ | Beschreibung |
|---|---|---|
| id | bigint, PK | |
| supplier_id | FK suppliers (uuid) | |
| tenant_id | FK tenants | |
| product_name | varchar(255) | Produktbezeichnung |
| unit | varchar(50) | Einheit (Liter, Stueck, kg) |
| price | decimal(10,4) | Preis pro Einheit |
| valid_from | date | Gueltig ab |
| valid_until | date, nullable | Gueltig bis |
| created_at | timestamp | |
| updated_at | timestamp | |

#### supplier_complaints (Reklamationen)
| Feld | Typ | Beschreibung |
|---|---|---|
| id | bigint, PK | |
| supplier_id | FK suppliers (uuid) | |
| tenant_id | FK tenants | |
| gas_station_id | FK gas_stations (uuid), nullable | Betroffene Tankstelle |
| subject | varchar(255) | Betreff |
| description | text | Beschreibung |
| status | enum: open, in_progress, resolved, closed | |
| priority | enum: low, medium, high | |
| resolved_at | timestamp, nullable | |
| resolved_by | FK users (uuid), nullable | |
| attachments | JSON, nullable | Dateianhaenge |
| created_by | FK users (uuid) | |
| created_at | timestamp | |
| updated_at | timestamp | |

#### documents
| Feld | Typ | Beschreibung |
|---|---|---|
| id | uuid, PK | UUID v7 |
| tenant_id | FK tenants (uuid) | |
| user_id | FK users (uuid), nullable | Bezug zum Mitarbeiter |
| gas_station_id | FK gas_stations (uuid), nullable | Bezug zur Tankstelle |
| template_id | FK document_templates (uuid), nullable | Verwendete Vorlage |
| **--- Dokument ---** | | |
| type | enum: employment_contract, termination, warning, work_certificate, interim_certificate, amendment, vacation_request, sick_note, salary_adjustment, payslip, cash_report, delivery_note, inventory_list, damage_report, other | |
| category | enum: hr, operations, other | Kategorie |
| title | varchar(255) | |
| content | longtext, nullable | Generierter HTML-Inhalt |
| file_path | varchar(255), nullable | PDF-Dateipfad |
| file_size | bigint, nullable | Dateigroesse in Bytes |
| **--- Signatur ---** | | |
| requires_signature | boolean, default false | Unterschrift erforderlich |
| signed_at | timestamp, nullable | Unterschrieben am |
| signature_data | longtext, nullable | Signatur-Daten (Base64 SVG/PNG) |
| signed_by_name | varchar(255), nullable | Name des Unterzeichners |
| signed_by_ip | varchar(45), nullable | IP bei Unterschrift |
| counter_signed_at | timestamp, nullable | Gegenzeichnung (Partner) |
| counter_signature_data | longtext, nullable | |
| **--- Versand ---** | | |
| sent_at | timestamp, nullable | Per E-Mail gesendet am |
| sent_to_email | varchar(255), nullable | Empfaenger-E-Mail |
| **--- Status ---** | | |
| status | enum: draft, pending_signature, sent, signed, counter_signed, archived, revoked | |
| version | integer, default 1 | Dokumentversion |
| notes | text, nullable | |
| created_by | FK users (uuid) | Erstellt von |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp, nullable | |

#### document_templates
| Feld | Typ | Beschreibung |
|---|---|---|
| id | bigint, PK | |
| tenant_id | FK tenants (uuid), nullable | NULL = globale Systemvorlage |
| type | varchar(50) | Dokumententyp (entspricht documents.type) |
| category | enum: hr, operations, other | |
| name | varchar(255) | Vorlagenname |
| description | text, nullable | Beschreibung der Vorlage |
| content | longtext | HTML/Blade Template mit Platzhaltern |
| variables | JSON | Verfuegbare Platzhalter mit Beschreibung |
| header_html | longtext, nullable | Briefkopf |
| footer_html | longtext, nullable | Fusszeilene |
| is_default | boolean, default false | Standard-Vorlage fuer diesen Typ |
| is_active | boolean, default true | |
| sort_order | integer, default 0 | |
| created_at | timestamp | |
| updated_at | timestamp | |

#### invitations
| Feld | Typ | Beschreibung |
|---|---|---|
| id | uuid, PK | UUID v7 |
| tenant_id | FK tenants (uuid) | |
| email | varchar(255) | Eingeladene E-Mail |
| token | varchar(64), unique | Einladungs-Token |
| type | enum: employee, manager | Einladungstyp |
| role_id | FK roles, nullable | Vorgesehene Rolle | Vorgesehene Rolle |
| gas_station_ids | JSON, nullable | Zuzuweisende Tankstellen |
| invited_by | FK users (uuid) | Eingeladen von |
| message | text, nullable | Persoenliche Nachricht |
| expires_at | timestamp | Ablaufdatum (Standard: 7 Tage) |
| accepted_at | timestamp, nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

#### communication_logs (CRM-Historie)
| Feld | Typ | Beschreibung |
|---|---|---|
| id | uuid, PK | UUID v7 |
| tenant_id | FK tenants (uuid) | |
| communicable_type | varchar(255) | Polymorph: Customer oder Supplier |
| communicable_id | bigint | |
| type | enum: email, call, note, meeting, task | Kommunikationstyp |
| direction | enum: incoming, outgoing, internal | Richtung |
| subject | varchar(255), nullable | Betreff |
| content | text | Inhalt/Notiz |
| attachments | JSON, nullable | Dateianhaenge |
| scheduled_at | timestamp, nullable | Geplanter Termin (fuer Tasks/Meetings) |
| completed_at | timestamp, nullable | Erledigt am |
| reminder_at | timestamp, nullable | Erinnerung |
| created_by | FK users (uuid) | |
| created_at | timestamp | |
| updated_at | timestamp | |

#### shifts (Schichtplanung)
| Feld | Typ | Beschreibung |
|---|---|---|
| id | uuid, PK | UUID v7 |
| tenant_id | FK tenants (uuid) | |
| gas_station_id | FK gas_stations (uuid) | Tankstelle |
| user_id | FK users (uuid) | Mitarbeiter |
| **--- Schicht ---** | | |
| date | date | Datum |
| start_time | time | Beginn |
| end_time | time | Ende |
| break_minutes | integer, default 0 | Pause in Minuten |
| shift_type | enum: early, late, night, split, custom | Schichttyp |
| **--- Status ---** | | |
| status | enum: planned, confirmed, swap_requested, swapped, cancelled, completed | |
| notes | text, nullable | |
| swap_requested_by | FK users (uuid), nullable | Tausch angefragt von |
| swap_target_user_id | FK users (uuid), nullable | Tausch-Ziel |
| confirmed_by | FK users (uuid), nullable | Bestaetigt von |
| actual_start | time, nullable | Tatsaechlicher Beginn |
| actual_end | time, nullable | Tatsaechliches Ende |
| created_by | FK users (uuid) | |
| created_at | timestamp | |
| updated_at | timestamp | |

#### shift_templates (Schichtvorlagen)
| Feld | Typ | Beschreibung |
|---|---|---|
| id | uuid, PK | UUID v7 |
| tenant_id | FK tenants (uuid) | |
| gas_station_id | FK gas_stations (uuid), nullable | |
| name | varchar(255) | z.B. "Fruehschicht", "Spaetschicht" |
| start_time | time | |
| end_time | time | |
| break_minutes | integer, default 0 | |
| shift_type | enum: early, late, night, split, custom | |
| color | varchar(7), nullable | Farbe fuer Kalender (#hex) |
| created_at | timestamp | |
| updated_at | timestamp | |

#### notifications
| Feld | Typ | Beschreibung |
|---|---|---|
| id | uuid, PK | |
| tenant_id | FK tenants (uuid), nullable | |
| type | varchar(255) | Notification-Klasse |
| notifiable_type | varchar(255) | Polymorph (User) |
| notifiable_id | bigint | |
| data | JSON | Notification-Daten |
| channels | JSON | Gesendete Kanaele (email, database, push) |
| read_at | timestamp, nullable | Gelesen am |
| created_at | timestamp | |
| updated_at | timestamp | |

#### ai_chat_messages
| Feld | Typ | Beschreibung |
|---|---|---|
| id | uuid, PK | UUID v7 |
| tenant_id | FK tenants (uuid) | |
| user_id | FK users (uuid) | |
| conversation_id | varchar(36) | UUID fuer Gespraechsgruppierung |
| role | enum: user, assistant, system | |
| content | text | Nachrichteninhalt |
| context | JSON, nullable | Kontext-Daten (welche Seite, welcher Mitarbeiter etc.) |
| tokens_used | integer, nullable | Verbrauchte Tokens |
| model | varchar(50), nullable | Verwendetes KI-Modell |
| created_at | timestamp | |

#### audit_logs (DSGVO-Audit)
| Feld | Typ | Beschreibung |
|---|---|---|
| id | bigint, PK | |
| tenant_id | FK tenants (uuid), nullable | |
| user_id | FK users (uuid), nullable | |
| user_type | varchar(50) | super_admin, partner, employee |
| action | enum: create, read, update, delete, export, login, logout, permission_change | |
| auditable_type | varchar(255) | Polymorph (welches Model) |
| auditable_id | bigint, nullable | |
| old_values | JSON, nullable, encrypted | Alte Werte |
| new_values | JSON, nullable, encrypted | Neue Werte |
| ip_address | varchar(45) | |
| user_agent | varchar(500), nullable | |
| reason | text, nullable | Begruendung (Pflicht bei Super-Admin Level 3) |
| created_at | timestamp | |

#### consent_logs (DSGVO-Einwilligungen)
| Feld | Typ | Beschreibung |
|---|---|---|
| id | bigint, PK | |
| user_id | FK users (uuid) | |
| tenant_id | FK tenants (uuid), nullable | |
| type | enum: privacy_policy, terms, data_processing, cookies, marketing | |
| version | varchar(20) | Version der Erklaerung |
| accepted_at | timestamp | Eingewilligt am |
| revoked_at | timestamp, nullable | Widerruf am |
| ip_address | varchar(45) | |
| user_agent | varchar(500), nullable | |
| created_at | timestamp | |

#### data_deletion_requests (DSGVO-Loeschantraege)
| Feld | Typ | Beschreibung |
|---|---|---|
| id | bigint, PK | |
| user_id | FK users (uuid) | Antragsteller |
| tenant_id | FK tenants (uuid), nullable | |
| type | enum: full_deletion, data_export, rectification | Antragstyp |
| status | enum: pending, processing, completed, rejected | |
| description | text, nullable | Beschreibung des Antrags |
| requested_at | timestamp | |
| processed_at | timestamp, nullable | |
| completed_at | timestamp, nullable | |
| processed_by | FK users (uuid), nullable | Bearbeitet von |
| rejection_reason | text, nullable | |
| notes | text, nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

---

## 5. Module & Features (Phasenplan)

### Phase 1: Fundament (MVP)
**Ziel:** Registrierung, Login, Grundstruktur

1. **Landing Page** (`/`)
   - Modernes Design, Produktvorstellung
   - Registrierungsformular fuer Tankstellenpartner
   - 14-Tage-Trial startet automatisch

2. **Authentifizierung**
   - Registrierung mit E-Mail-Bestaetigung
   - Login/Logout
   - Passwort-Reset
   - Trial-Logik (14 Tage, danach Zugang gesperrt bis Abo)

3. **Multi-Tenancy Grundstruktur**
   - Tenant-Model mit Global Scopes
   - Middleware fuer Tenant-Erkennung
   - Tenant-Context in Session

4. **Super-Admin Panel** (`/admin` - Filament 5)
   - Mandanten-Uebersicht & Verwaltung
   - Benutzer-Uebersicht
   - Globale Einstellungen
   - System-Statistiken

5. **Partner-Dashboard** (`/dashboard`)
   - Uebersichtsseite mit KPIs
   - Tab-basiertes Layout fuer alle Formulare
   - Modernes, frisches Design (Tailwind)

### Phase 2: Kernmodule

6. **Tankstellen-Verwaltung**
   - CRUD fuer Tankstellen
   - Zuordnung von Mitarbeitern
   - Tankstellen-Details mit Tabs

7. **Mitarbeiter-Verwaltung**
   - CRUD fuer Mitarbeiter
   - Einladung per Link (Token-basiert)
   - Online-Personalbogen (mehrstufiges Tab-Formular)
   - Zuordnung zu Tankstellen (Multi-Select)
   - Rollen & Rechte pro Mandant

8. **Kunden-CRM**
   - CRUD mit CRM-Funktionen
   - Kommunikationshistorie
   - Aufgaben & Erinnerungen
   - Zuordnung zu Tankstellen

9. **Lieferanten-CRM**
   - CRUD mit CRM-Funktionen
   - Kommunikationshistorie
   - Vertragsinfos, Liefertyp
   - Zuordnung zu Tankstellen

### Phase 3: Dokumenten-Management & KI

10. **Schichtplanung / Dienstplan**
    - Kalenderansicht (Wochen- und Monatsansicht)
    - Drag & Drop Zuweisung von Mitarbeitern zu Schichten
    - Schichtvorlagen (Frueh-, Spaet-, Nachtschicht) konfigurierbar
    - Schichttausch-Boerse: Mitarbeiter koennen Tausch anfragen
    - Ist/Soll-Vergleich der Arbeitszeiten
    - Automatische Konflikterkennung (Doppelbelegung, Ruhezeiten)
    - Export als PDF

11. **Dokumenten-Management (DMS)**
    - Dokumentenvorlagen:
      - HR: Arbeitsvertrag, Kuendigung, Abmahnung, Arbeitszeugnis, Zwischenzeugnis, Aenderungsvertrag, Urlaubsantrag, Krankmeldung, Gehaltsanpassung
      - Betrieb: Kassenbericht, Lieferschein, Inventurliste, Schadensmeldung
    - PDF-Generierung aus Vorlagen mit Platzhalterbefuellung
    - Digitale Unterschrift (Touch/Stylus auf Tablet/Handy)
    - Gegen-Unterschrift durch Partner
    - Versand per E-Mail
    - Dokumenten-Archiv pro Mitarbeiter
    - Status-Tracking (Entwurf -> Ausstehend -> Gesendet -> Unterschrieben -> Gegengezeichnet -> Archiviert)
    - Versionierung von Dokumenten

12. **KI-Integration**
    - **Dokumenten-KI:** Generiert Vertraege, Kuendigungen, Zeugnisse basierend auf Mitarbeiterdaten und Vorlagen
    - **Chat-Assistent:** Beantwortet Fragen zu Arbeitsrecht, Personalmanagement, Tankstellenbetrieb
    - Kontext-aware (kennt die Daten des Mandanten)
    - Chat-Verlauf wird gespeichert
    - Anonymisierung personenbezogener Daten vor API-Versand

13. **Benachrichtigungssystem**
    - E-Mail-Benachrichtigungen (alle wichtigen Events)
    - In-App-Notifications (Glocken-Icon mit Badge-Counter)
    - Push-Notifications fuer PWA/Mobile
    - Konfigurierbar pro User: Welche Notifications ueber welchen Kanal
    - Events: Einladung, Dokument zur Unterschrift, Schicht zugewiesen, Trial-Ende, Tausch-Anfrage, etc.

### Phase 4: Mitarbeiter-Portal & Erweiterungen

14. **Mitarbeiter-Portal** (`/portal`)
    - Eigenes Dashboard fuer Mitarbeiter
    - Zugewiesene Tankstellen anzeigen & auswaehlen
    - Eigene Dokumente einsehen & unterschreiben
    - Personalbogen vervollstaendigen
    - Rechte-basiert: Was der Partner freigibt

15. **Rollen & Rechte System (grafische Verwaltung)**
    - Vorgefertigte Rollen: Super-Admin, Partner, Stationsleiter, Mitarbeiter
    - Mandanten-eigene Rollen erstellen/bearbeiten/loeschen
    - **Rollen-Tabelle:** Uebersicht aller Rollen mit Bearbeitungs-Buttons
    - **Permissions-Matrix:** Grafische Tabelle (Rollen = Spalten, Permissions = Zeilen)
      - Checkboxen zum An-/Abwaehlen von Rechten pro Rolle
      - Gruppierung nach Modul (Tankstellen, Mitarbeiter, Kunden, Lieferanten, Dokumente, KI)
      - Farbige Markierung (gruen = aktiv, grau = inaktiv)
      - Bulk-Aktionen: Alle Rechte eines Moduls auf einmal aktivieren/deaktivieren
    - **Rollen-Editor:** Formular zum Erstellen/Bearbeiten einer Rolle mit:
      - Name, Beschreibung
      - Drag & Drop Sortierung
      - Vorschau der effektiven Rechte
    - **Benutzer-Rollen-Zuweisung:** Tabelle mit allen Benutzern, Rolle per Dropdown zuweisbar
    - Permissions werden in der `permissions`-Tabelle gespeichert (spatie/laravel-permission v7)
    - Jeder Mandant sieht nur seine eigenen + die globalen System-Rollen

### Phase 5: Zahlungen & Mobile

16. **Abo-System** (Mollie)
    - SEPA-Lastschrift, Ueberweisung, PayPal
    - Trial -> Abo Konvertierung
    - Rechnungsgenerierung
    - Preismodell (wird spaeter definiert)

17. **PWA / Mobile**
    - Progressive Web App Konfiguration
    - Service Worker, Offline-Faehigkeit (Basis)
    - App-Icon, Splash Screen
    - Android-Installation ueber Browser
    - Touch-optimierte UI (bereits durch responsives Design)

---

## 6. Coding-Konventionen

- **Attribute/Spalten:** Immer Englisch (z.B. `first_name`, `street`, `is_active`)
- **Kommentare im Code:** Deutsch (z.B. `// Mandantenzuordnung pruefen`)
- **Migrations-Kommentare:** Deutsch (z.B. `$table->string('tax_id')->comment('Steuernummer');`)
- **UI/Frontend-Texte:** Deutsch (ueber Laravel Sprachdateien `lang/de/`)
- **Spaeter:** Mehrsprachigkeit ueber `lang/en/`, `lang/tr/` etc.
- **Model-Relationen:** Englisch (z.B. `gasStations()`, `employeeProfile()`)
- **Validation Messages:** Deutsch in `lang/de/validation.php`
- **Variablen/Funktionen:** Englisch, camelCase
- **Blade-Komponenten:** kebab-case (z.B. `<x-tab-form>`)
- **Livewire-Komponenten:** PascalCase (z.B. `EmployeeTable`)

---

## 7. Design-Richtlinien (unveraendert)

- **Frisch & Modern:** Keine spiessigen Enterprise-Designs
- **Farbschema:** Klare, energetische Farben (z.B. Indigo/Violet + helle Akzente)
- **Tab-Formulare:** Alle laengeren Formulare in Tabs aufgeteilt
- **Cards & Rounded Corners:** Moderne Card-basierte Layouts
- **Dark Mode:** Optional (spaeter)
- **Responsive:** Mobile-first fuer spaetere PWA
- **Micro-Animations:** Subtile Uebergaenge mit Alpine.js
- **Icons:** Heroicons (im Tailwind-Oekosystem)

---

## 8. DSGVO-Konformitaet & Datenschutz

### 7.1 Grundprinzipien (Art. 5 DSGVO)
- **Datenminimierung:** Nur notwendige Daten erheben
- **Zweckbindung:** Daten nur fuer den angegebenen Zweck nutzen
- **Speicherbegrenzung:** Automatische Loeschfristen konfigurierbar
- **Integritaet & Vertraulichkeit:** Verschluesselung, Zugriffskontrolle

### 7.2 Technische Massnahmen
- **Verschluesselung at Rest:** Sensible Felder (Bankdaten, SV-Nummer, Steuer-ID) mit Laravel Encryption (AES-256-CBC)
- **Verschluesselung in Transit:** HTTPS/TLS erzwungen (Middleware)
- **Pseudonymisierung:** Optionale Anonymisierung bei Datenexport
- **Audit-Log:** Jede Datenveraenderung wird protokolliert (wer, wann, was, alter Wert, neuer Wert)
- **Automatische Datenloesch-Jobs:** Konfigurierbare Aufbewahrungsfristen pro Datentyp

### 7.3 Betroffenenrechte (UI-Features)
- **Auskunftsrecht (Art. 15):** Button "Meine Daten exportieren" - Export aller gespeicherten Daten als JSON/PDF
- **Recht auf Loesch ung (Art. 17):** "Konto loeschen" - vollstaendige Datenloesch ung mit Bestaetigung
- **Recht auf Berichtigung (Art. 16):** Mitarbeiter koennen eigene Daten im Portal korrigieren
- **Recht auf Datenportabilitaet (Art. 20):** Datenexport in maschinenlesbarem Format (JSON/CSV)
- **Widerspruchsrecht (Art. 21):** Einwilligungen koennen jederzeit widerrufen werden

### 7.4 Einwilligungsmanagement
- **Cookie-Banner:** DSGVO-konform mit Opt-In (kein Opt-Out)
- **Datenschutzerklaerung:** Muss bei Registrierung akzeptiert werden
- **AV-Vertrag:** Auftragsverarbeitungsvertrag zwischen Plattform-Betreiber und Tankstellenpartner (als Mandant)
- **Einwilligungs-Log:** Jede Einwilligung wird mit Zeitstempel gespeichert

### 7.5 Super-Admin Datenschutz (besonders geschuetzt)
- **Minimaler Zugriff:** Super-Admin sieht standardmaessig KEINE personenbezogenen Mitarbeiterdaten der Mandanten
- **Aggregierte Ansicht:** Super-Admin sieht nur Statistiken (Anzahl Mandanten, Tankstellen, aktive User) - keine Klardaten
- **Zugriffs-Audit:** Jeder Super-Admin-Zugriff auf Mandantendaten wird protokolliert
- **Berechtigungsstufen fuer Super-Admin:**
  - Level 1 (Standard): Mandanten-Uebersicht, Abrechnungen, System-Status
  - Level 2 (Support): Mandanten-Stammdaten einsehen (Name, E-Mail) - nur bei Support-Anfrage
  - Level 3 (Notfall): Voller Zugriff - erfordert 2FA + Begruendung im Audit-Log
- **Keine Einsicht** in: Mitarbeiter-Personalboegen, Bankdaten, SV-Nummern der Mandanten-Mitarbeiter
- **Technische Trennung:** Super-Admin-Queries filtern sensible Spalten automatisch heraus

### 7.6 Audit-Log Tabelle
```
audit_logs
  - id, tenant_id (nullable)
  - user_id, user_type (super_admin | partner | employee)
  - action (create | read | update | delete | export | login)
  - auditable_type, auditable_id (polymorphe Beziehung)
  - old_values (JSON, verschluesselt)
  - new_values (JSON, verschluesselt)
  - ip_address, user_agent
  - reason (Pflicht bei Super-Admin Level 3 Zugriff)
  - created_at
```

### 7.7 Datenspeicherung & Loeschung
- **Mitarbeiterdaten:** Aufbewahrung gemaess gesetzlicher Fristen (6-10 Jahre fuer Lohndaten)
- **Trial-Accounts:** Automatische Loesch ung nach 30 Tagen Inaktivitaet
- **Gekuendigte Mandanten:** Daten werden nach 90 Tagen vollstaendig geloescht
- **Logs:** Audit-Logs 3 Jahre, danach automatische Anonymisierung
- **KI-Chat-Verlaeufe:** Automatische Loesch ung nach 12 Monaten

### 7.8 KI & DSGVO
- **Keine personenbezogenen Daten an OpenAI** ohne Einwilligung
- KI-Kontext wird anonymisiert/pseudonymisiert bevor er an die API gesendet wird
- Option: Lokale KI-Modelle (z.B. Ollama) als Alternative fuer maximalen Datenschutz
- Transparenz: User wird informiert welche Daten die KI verarbeitet

---

## 9. Technische Sicherheit

- Alle sensiblen Daten verschluesselt (bank_details, social_security)
- CSRF-Schutz auf allen Formularen
- Rate-Limiting auf Auth-Routen
- Tenant-Isolation durch Global Scopes (niemals Daten anderer Mandanten sichtbar)
- Einladungs-Tokens mit Ablaufdatum
- Session-basierte Auth mit Remember-Me
- Input-Validierung auf allen Formularen
- XSS-Schutz durch Blade-Escaping
- 2FA fuer Super-Admin (Pflicht) und Partner (optional)
- Content Security Policy (CSP) Headers
- SQL-Injection-Schutz durch Eloquent ORM / Prepared Statements
- Regelmässige Sicherheits-Updates (Dependabot/Composer Audit)

---

## 10. Ordnerstruktur (geplant)

```
app/
  Models/
    Tenant.php, User.php, GasStation.php
    Customer.php, Supplier.php
    Document.php, DocumentTemplate.php
    EmployeeProfile.php, Invitation.php
    CommunicationLog.php, AiChatMessage.php
  Http/
    Controllers/
      Auth/ (Login, Register, Invitation)
      Dashboard/ (DashboardController)
      GasStation/ (GasStationController)
      Employee/ (EmployeeController)
      Customer/ (CustomerController)
      Supplier/ (SupplierController)
      Document/ (DocumentController)
      Ai/ (AiChatController)
      Portal/ (PortalController - Mitarbeiter)
    Middleware/
      EnsureTenantContext.php
      CheckTrialExpired.php
      CheckSubscription.php
    Livewire/
      Dashboard/ (KPI-Widgets, Charts)
      GasStation/ (CRUD-Komponenten)
      Employee/ (CRUD, Personalbogen, Einladung)
      Customer/ (CRUD, CRM-Komponenten)
      Supplier/ (CRUD, CRM-Komponenten)
      Document/ (Generator, Signatur, Archiv)
      Ai/ (ChatWidget, DocGenerator)
      RolesPermissions/ (RolesTable, PermissionsMatrix, RoleEditor, UserRoleAssignment)
      Portal/ (Mitarbeiter-Portal Komponenten)
  Services/
    TenantService.php
    DocumentService.php
    AiService.php
    InvitationService.php
    PdfService.php
    SignatureService.php
  Scopes/
    TenantScope.php (Global Scope)
  Traits/
    BelongsToTenant.php
  Filament/
    Resources/ (Super-Admin CRUD)
    Pages/
    Widgets/

resources/views/
  layouts/ (app.blade.php, guest.blade.php, portal.blade.php)
  landing/ (Landing Page)
  dashboard/ (Partner-Dashboard)
  portal/ (Mitarbeiter-Portal)
  livewire/ (Livewire-Komponenten)
  documents/templates/ (PDF-Vorlagen)
  components/ (Wiederverwendbare Blade-Komponenten)

routes/
  web.php (Landing, Auth)
  dashboard.php (Partner-Routen)
  portal.php (Mitarbeiter-Routen)
  api.php (API fuer PWA/Mobile spaeter)
```

---

## 11. Implementierungs-Reihenfolge

```
Phase 1 - Fundament:
  Schritt 0:  Neues Laravel 12 Projekt aufsetzen + Packages installieren
  Schritt 1:  Datenbank-Setup (MySQL), alle Migrationen erstellen
  Schritt 2:  Multi-Tenancy Grundstruktur (Trait, Scope, Middleware)
  Schritt 3:  Auth-System (Register, Login, E-Mail-Verify, Trial-Logik)
  Schritt 4:  DSGVO-Grundlagen (Audit-Log, Consent-Log, Verschluesselung)
  Schritt 5:  Landing Page (modernes Design)
  Schritt 6:  Super-Admin Filament Panel
  Schritt 7:  Partner-Dashboard Layout (Sidebar, Tabs, Navigation)
  Schritt 8:  Benachrichtigungssystem (E-Mail + In-App + Push)

Phase 2 - Kernmodule:
  Schritt 9:  Tankstellen CRUD (mit Tabs, Karte)
  Schritt 10: Rollen & Rechte (spatie v7 + grafische Permissions-Matrix)
  Schritt 11: Mitarbeiter CRUD + Einladungssystem
  Schritt 12: Personalbogen (mehrstufiges Tab-Formular)
  Schritt 13: Kunden-CRM (B2B + B2C)
  Schritt 14: Lieferanten-CRM (mit Preislisten & Reklamationen)

Phase 3 - Erweitert:
  Schritt 15: Schichtplanung / Dienstplan (Kalender, Drag & Drop)
  Schritt 16: DMS (Templates, PDF-Generierung, Signatur)
  Schritt 17: KI-Integration (Dokumenten-KI + Chat-Assistent)
  Schritt 18: Mitarbeiter-Portal

Phase 4 - Finalisierung:
  Schritt 19: DSGVO-Betroffenenrechte UI (Export, Loesch ung, Korrektur)
  Schritt 20: Zahlungsintegration (Mollie)
  Schritt 21: PWA-Setup fuer Android
  Schritt 22: Testing & Security-Audit
```

---

## 12. Verifizierung / Testplan

- **Unit Tests:** Models, Services, Scopes (Tenant-Isolation)
- **Feature Tests:** Auth-Flow, CRUD-Operationen, Einladungen
- **Browser Tests:** Registrierung -> Trial -> Dashboard Flow
- **Tenant-Isolation:** Sicherstellen dass kein Mandant fremde Daten sieht
- **Manuell:** Landing Page responsive testen, Tab-Formulare, PDF-Generierung, Signatur auf Tablet
- **Dev-Server:** `php artisan serve` + `npm run dev` (Vite)
