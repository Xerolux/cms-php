# XQUANTORIA Multi-Tenancy Implementation

Diese Dokumentation beschreibt die Multi-Tenancy-Implementierung für XQUANTORIA mit `stancl/tenancy`.

## Übersicht

Die Multi-Tenancy-Implementierung ermöglicht es, mehrere voneinander isolierte Mandanten (Tenants) auf einer einzigen XQUANTORIA-Instanz zu betreiben. Jeder Tenant hat:

- Eigene Datenbank
- Eigene Benutzer
- Eigene Inhalte (Posts, Pages, Media, etc.)
- Eigene Einstellungen
- Eigene Subdomain (z.B. `tenant1.xquantoria.test`)

## Installation

### 1. Paket installieren

```bash
cd backend
composer require stancl/tenancy
```

### 2. Migrationen ausführen

```bash
php artisan migrate
```

Dies erstellt die zentralen Tabellen:
- `tenants` - Mandanten-Daten
- `domains` - Domains/Subdomains pro Mandant

### 3. Zentral-Datenbank seeden

```bash
php artisan db:seed --class=CentralSeeder
```

Erstellt Demo-Tenants:
- `demo.xquantoria.test` - Professional Plan (mit Trial)
- `starter-tenant.xquantoria.test` - Starter Plan
- `free-tenant.xquantoria.test` - Free Plan

## Architektur

### Zentral-Domain vs. Tenant-Domains

**Zentral-Domain** (z.B. `xquantoria.test` oder `localhost`):
- Super Admin Panel
- Tenant-Management
- Plattform-Statistiken
- Tenant-Registrierung

**Tenant-Domains** (z.B. `demo.xquantoria.test`):
- Tenant-spezifisches CMS
- Isolierte Datenbank
- Eigene Benutzer & Inhalte
- Tenant-spezifische Einstellungen

### Subscription-Pläne

| Plan | Preis | Benutzer | Speicher | Posts |
|------|-------|----------|----------|-------|
| Free | €0 | 2 | 1 GB | 10 |
| Starter | €9.99/Monat | 5 | 10 GB | 100 |
| Professional | €29.99/Monat | 20 | 50 GB | 1,000 |
| Enterprise | €99.99/Monat | Unlimited | 500 GB | Unlimited |

### Features pro Plan

**Free:**
- Basis Analytics
- Basis Theme
- Community Support

**Starter:**
- Basis Analytics
- Custom Theme
- Email Support
- Custom Domain
- SEO Tools

**Professional:**
- Advanced Analytics
- Custom Theme
- Priority Support
- Custom Domain
- SEO Tools
- API Access
- Backup
- Workflow Automation

**Enterprise:**
- Alle Professional Features
- White Label
- Custom Integrations
- Advanced Security
- Dedicated Server

## Verwendung

### Tenant erstellen (CLI)

```bash
# Einfacher Tenant
php artisan tenant:create "My Company" "info@mycompany.com" "mycompany"

# Mit Plan
php artisan tenant:create "My Company" "info@mycompany.com" "mycompany" --plan=professional

# Mit Trial
php artisan tenant:create "My Company" "info@mycompany.com" "mycompany" --plan=professional --trial

# Mit Seeder
php artisan tenant:create "My Company" "info@mycompany.com" "mycompany" --seed
```

### Tenant auflisten

```bash
php artisan tenant:list
```

### Tenant löschen

```bash
# Interaktiv
php artisan tenant:delete {tenant-id}

# Force (ohne Bestätigung)
php artisan tenant:delete {tenant-id} --force
```

## API-Routen

### Zentral-Domain (Super Admin)

```
GET  /api/v1/central/dashboard
GET  /api/v1/central/stats
GET  /api/v1/central/platform/stats
GET  /api/v1/central/tenants
POST /api/v1/central/tenants
GET  /api/v1/central/tenants/{tenant}
PUT  /api/v1/central/tenants/{tenant}
DELETE /api/v1/central/tenants/{tenant}
POST /api/v1/central/tenants/{tenant}/activate
POST /api/v1/central/tenants/{tenant}/deactivate
POST /api/v1/central/tenants/{tenant}/suspend
POST /api/v1/central/tenants/{tenant}/reset-trial
```

### Tenant-Domains

```
GET  /api/v1/dashboard
GET  /api/v1/tenant
PUT  /api/v1/tenant
GET  /api/v1/tenant/users
POST /api/v1/tenant/users
PUT  /api/v1/tenant/users/{user}
DELETE /api/v1/tenant/users/{user}
GET  /api/v1/tenant/subscription
GET  /api/v1/tenant/subscription/plans
POST /api/v1/tenant/subscription/upgrade
POST /api/v1/tenant/subscription/downgrade
POST /api/v1/tenant/subscription/cancel
POST /api/v1/tenant/subscription/resume
GET  /api/v1/tenant/subscription/usage
```

## Konfiguration

### `config/tenancy.php`

Wichtige Einstellungen:

```php
// Zentral-Domains (keine Tenancy)
'domain_identification' => [
    'central_domains' => [
        'localhost',
        'xquantoria.test',
        'www.xquantoria.test',
    ],
],

// Subscription-Pläne
'plans' => [
    'free' => [...],
    'starter' => [...],
    'professional' => [...],
    'enterprise' => [...],
],

// Trial-Einstellungen
'trial' => [
    'enabled' => true,
    'duration_days' => 14,
    'plan' => 'professional',
],
```

## Tenant-Isolierung

### Middleware

Die Tenancy-Middleware stellt sicher, dass:

1. **Subdomain-Erkennung**: Tenant wird anhand der Subdomain identifiziert
2. **Datenbank-Trennung**: Jeder Tenant verwendet seine eigene Datenbank
3. **Speicher-Trennung**: Dateien werden in tenant-spezifische Verzeichnisse gespeichert
4. **Limit-Prüfung**: Tenant-Limits (Benutzer, Posts, Speicher) werden geprüft

### Middleware-Gruppen

```php
// Tenancy-Middleware (automatisch für alle Tenant-Domains)
Route::middleware(['tenancy'])->group(function () {
    // Tenant-Routen
});

// Limit-Prüfung (optional)
Route::middleware(['tenancy.check_limits'])->group(function () {
    // Routen, die Limits prüfen
});
```

## Billing & Subscriptions

### Upgrade-Flow

1. Tenant wählt neuen Plan
2. Preis wird berechnet (inkl. Prorating)
3. Payment wird verarbeitet (Stripe-Integration vorbereitet)
4. Tenant wird upgraded
5. neue Limits werden angewendet

### Limit-Prüfung

Das System prüft automatisch:
- Benutzer-Limit vor User-Erstellung
- Post-Limit vor Post-Erstellung
- Speicher-Limit vor Uploads
- Feature-Zugriff basierend auf Plan

### Abrechnung

**Aktuell:**
- Platzhalter für Stripe-Integration
- Preise werden lokal konfiguriert
- Trial-Perioden werden unterstützt

**Zukünftig:**
- Stripe Checkout Integration
- Webhook-Handler für Payment Events
- Automatische Verlängerung
- Rechnungs-Export

## Entwicklung

### Tenant-aware Models

Alle Models, die zu einem Tenant gehören, sollten das `BelongsToTenant` Trait verwenden (wird automatisch von stancl/tenancy verwaltet).

### Tenant-spezifische Queries

```php
// Innerhalb eines Tenant-Kontextes
$posts = Post::all(); // Nur Posts dieses Tenants

// Zentral-Domain
$allTenants = Tenant::with('domains')->get();
```

### Tenant-Kontext wechseln

```php
// Tenant initialisieren
tenancy()->initialize($tenant);

// Tenant beenden
tenancy()->end();

// Aktuellen Tenant abrufen
$currentTenant = tenant();
```

## Deployment

### Vorbereitung

1. `.env` auf Server konfigurieren:
```env
CENTRAL_DOMAIN=xquantoria.com
TENANT_DOMAIN_PATTERN=.xquantoria.com
```

2. SSL-Zertifikate für Wildcard-Domain konfigurieren:
```
*.xquantoria.com
```

3. Datenbank-Backup-Strategie einrichten

### Migrationen ausrollen

```bash
# Zentral-Datenbank
php artisan migrate --path=database/migrations/central

# Alle Tenant-Datenbanken
php artisan tenants:migrate
```

### Tenant-Datenbanken backuppen

```bash
# Alle Tenants
php artisan tenants:backup

# Spezifischer Tenant
php artisan tenant:backup {tenant-id}
```

## Troubleshooting

### Tenant nicht gefunden

**Problem:** `TenantCouldNotBeIdentifiedException`

**Lösung:**
1. Prüfen, ob Domain in `domains` Tabelle existiert
2. Prüfen, ob Domain in `central_domains` Konfiguration steht
3. DNS prüfen

### Datenbank-Verbindungsprobleme

**Problem:** Tenant-Datenbank kann nicht verbunden werden

**Lösung:**
1. Prüfen, ob Tenant-Datenbank existiert
2. Datenbank-Permissions prüfen
3. `php artisan tenant:delete {tenant-id}` und neu erstellen

### Limits werden nicht durchgesetzt

**Problem:** User/Post-Limits werden ignoriert

**Lösung:**
1. Prüfen, ob `CheckTenantLimits` Middleware aktiv ist
2. Tenant-Limits in DB prüfen
3. Plan-Konfiguration prüfen

## Sicherheit

### Isolierung

- Jeder Tenant hat eigene Datenbank
- Dateien werden getrennt gespeichert
- Keine Cross-Tenant Queries möglich

### Access Control

- Super Admin nur auf zentraler Domain
- Tenant Admin nur auf eigener Domain
- API-Endpunkte sind entsprechend geschützt

### Best Practices

1. Niemals tenantübergreifende Queries im Tenant-Kontext
2. Immer `tenancy()->end()` nach Tenant-Operationen aufrufen
3. Central-Datenbank nur für Tenant-Management verwenden
4. Regelmäßige Backups aller Tenant-Datenbanken

## Zukünftige Erweiterungen

- [ ] Stripe Payment Integration
- [ ] Webhook Handler
- [ ] Rechnungs-Export
- [ ] Tenant-Specific SSL
- [ ] Custom Domain Mapping
- [ ] Tenant Migration Tool
- [ ] Multi-Region Support
- [ ] Advanced Analytics Dashboard
- [ ] White-Labeling Optionen

## Support & Dokumentation

- stancl/tenancy Dokumentation: https://tenancyforlaravel.com/
- Issues: https://github.com/xquantoria/xquantoria/issues
