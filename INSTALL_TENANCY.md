# Multi-Tenancy Installation Guide für XQUANTORIA

## Schnellstart-Anleitung

### 1. Abhängigkeiten installieren

```bash
cd backend
composer install
```

### 2. Umgebungsvariablen konfigurieren

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Datenbank einrichten

Erstelle eine neue Datenbank für die zentrale Verwaltung:

```sql
CREATE DATABASE xquantoria_central;
GRANT ALL PRIVILEGES ON xquantoria_central.* TO 'cms_user'@'localhost';
```

### 4. Konfiguriere `.env`

```env
DB_DATABASE=xquantoria_central

# Multi-Tenancy
CENTRAL_DOMAIN=localhost
TENANT_DOMAIN_PATTERN=.xquantoria.test
```

### 5. Migrationen ausführen

```bash
# Zentral-Datenbank migrieren
php artisan migrate

# Demo-Tenants erstellen
php artisan db:seed --class=CentralSeeder
```

### 6. Hosts-Datei aktualisieren (lokal)

Füge zu `/etc/hosts` (Linux/Mac) oder `C:\Windows\System32\drivers\etc\hosts` (Windows) hinzu:

```
127.0.0.1 xquantoria.test
127.0.0.1 demo.xquantoria.test
127.0.0.1 starter-tenant.xquantoria.test
127.0.0.1 free-tenant.xquantoria.test
```

### 7. Development Server starten

```bash
php artisan serve --host=xquantoria.test --port=8000
```

### 8. Testen

**Zentral-Domain:**
```
http://xquantoria.test:8000
```

**Tenant-Domains:**
```
http://demo.xquantoria.test:8000
http://starter-tenant.xquantoria.test:8000
http://free-tenant.xquantoria.test:8000
```

**Demo-Login-Credentials:**
```
Email: admin@xquantoria.test
Password: password
```

## Nächste Schritte

### Einen neuen Tenant erstellen

```bash
php artisan tenant:create "My Company" "info@mycompany.com" "mycompany" --plan=professional --trial
```

### Tenant auflisten

```bash
php artisan tenant:list
```

### Migrationen für alle Tenants ausführen

```bash
php artisan tenants:migrate
```

### Tenant-Datenbank backuppen

```bash
php artisan tenants:backup
```

## Produktions-Deployment

### 1. DNS konfigurieren

Erstelle DNS-A-Records für:
- `xquantoria.com` (Zentral-Domain)
- `*.xquantoria.com` (Wildcard für alle Tenants)

### 2. SSL-Zertifikate

Konfiguriere Wildcard-SSL für `*.xquantoria.com`:

```bash
certbot certonly --manual -d *.xquantoria.com --preferred-challenges dns
```

### 3. Nginx-Konfiguration

```nginx
# Zentral-Domain
server {
    listen 80;
    server_name xquantoria.com www.xquantoria.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name xquantoria.com www.xquantoria.com;

    ssl_certificate /etc/ssl/certs/xquantoria.crt;
    ssl_certificate_key /etc/ssl/private/xquantoria.key;

    root /var/www/xquantoria/backend/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}

# Tenant-Domains (Wildcard)
server {
    listen 80;
    server_name *.xquantoria.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name *.xquantoria.com;

    ssl_certificate /etc/ssl/certs/xquantoria_wildcard.crt;
    ssl_certificate_key /etc/ssl/private/xquantoria_wildcard.key;

    root /var/www/xquantoria/backend/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 4. Queue Worker konfigurieren

```bash
# Supervisor Konfiguration
php artisan queue:work --queue=default,high,low --sleep=3 --tries=3
```

### 5. Cron-Jobs einrichten

```bash
# In crontab -e
* * * * * cd /var/www/xquantoria/backend && php artisan schedule:run >> /dev/null 2>&1
```

### 6. Backup-Strategie

```bash
# Tägliche Backups aller Tenant-Datenbanken
0 2 * * * php artisan tenants:backup >> /var/log/xquantoria/backup.log 2>&1
```

## Stripe Integration (optional)

### 1. Stripe-Konto erstellen

1. Registriere dich bei https://stripe.com
2. Erstelle Produkte und Preise für jeden Plan
3. Kopiere API-Keys

### 2. Umgebungsvariablen konfigurieren

```env
STRIPE_KEY=pk_live_xxx
STRIPE_SECRET=sk_live_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx
STRIPE_PRICE_FREE=price_xxx
STRIPE_PRICE_STARTER=price_xxx
STRIPE_PRICE_PROFESSIONAL=price_xxx
STRIPE_PRICE_ENTERPRISE=price_xxx
```

### 3. Webhook-Endpoint einrichten

```bash
php artisan route:list | grep webhook
```

Konfiguriere den Webhook in Stripe Dashboard mit der URL:
```
https://xquantoria.com/api/v1/webhook/stripe
```

### 4. Preise synchronisieren

```bash
php artisan stripe:sync-prices
```

## Monitoring & Logging

### Log-Level konfigurieren

```env
LOG_CHANNEL=daily
LOG_LEVEL=info
```

### Tenant-spezifische Logs

Logs werden automatisch pro Tenant getrennt:
```
storage/logs/tenant-{tenant-id}.log
```

### Performance-Monitoring

```bash
# Tenant-spezifische Statistiken
php artisan tenant:stats {tenant-id}
```

## Fehlerbehebung

### Tenant nicht gefunden

```bash
# Tenant prüfen
php artisan tenant:list

# Domain prüfen
mysql> SELECT * FROM domains WHERE domain = 'your-domain';
```

### Datenbank-Probleme

```bash
# Alle Tenant-Datenbanken auflisten
mysql> SHOW DATABASES LIKE 'tenant%';

# Spezifische Datenbank prüfen
mysql> USE tenant_xxx;
mysql> SHOW TABLES;
```

### Cache leeren

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Migrationen neu ausführen

```bash
# Zentral
php artisan migrate:fresh

# Tenant
php artisan tenants:migrate-fresh
```

## Häufige Fragen

**Q: Wie viele Tenants kann ich hosten?**

A: Das hängt von deiner Server-Infrastruktur ab. Mit Optimierungen sind 100+ Tenants auf einem einzelnen Server möglich.

**Q: Kann ich Tenants migrieren?**

A: Ja, mit dem `tenant:migrate` Kommando kannst du Tenants zwischen Servern migrieren.

**Q: Werden Daten automatisch getrennt?**

A: Ja, jeder Tenant hat seine eigene Datenbank. Die Tenancy-Middleware stellt sicher, dass keine Cross-Tenant Queries möglich sind.

**Q: Kann ich Custom Domains für Tenants aktivieren?**

A: Ja, du kannst Custom Domains über die API oder CLI hinzufügen:
```bash
php artisan tenant:add-domain {tenant-id} customdomain.com
```

## Support

Für weitere Hilfe:
- Dokumentation: `MULTI_TENANCY.md`
- Issues: https://github.com/xquantoria/xquantoria/issues
- stancl/tenancy: https://tenancyforlaravel.com/
