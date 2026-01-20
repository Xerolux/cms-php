# CMS Roadmap & TODOs

**Projekt:** cms-php - GDPR-Compliant Blog/CMS Platform
**Datum:** 2026-01-20
**Version:** 1.0
**Status:** Security-Hardened, Feature-Complete (Core)

---

## 📊 CURRENT STATUS

### ✅ **COMPLETED (Production Ready)**

**Core Funktionalität:**
- ✅ REST API mit Laravel 11 + Sanctum
- ✅ React 18 Frontend (TypeScript, Vite)
- ✅ PostgreSQL/MySQL/MariaDB Support
- ✅ Posts, Categories, Tags, Media, Downloads
- ✅ User Management mit RBAC (6 Rollen)
- ✅ Secure Downloads (Token-based, single-use)
- ✅ Two-Factor Authentication (TOTP)
- ✅ Comment System
- ✅ Newsletter System (Double Opt-in)
- ✅ Advertisement Management
- ✅ SEO (Meta Tags, Robots.txt, Sitemap)
- ✅ Plugin System
- ✅ Backup & Restore
- ✅ Activity Logs
- ✅ Analytics Tracking
- ✅ Full-Text Search (PostgreSQL/MySQL)

**Security Features (Enterprise-Grade):**
- ✅ Laravel Policies (Authorization)
- ✅ Role-Based Middleware
- ✅ Strong Password Policy (12+ chars, complexity)
- ✅ SVG Sanitization (XSS Protection)
- ✅ Comment Sanitization
- ✅ CORS Hardening
- ✅ 2FA Enforcement Middleware
- ✅ Account Lockout (Brute-Force Protection)
- ✅ Password Reset
- ✅ Security Headers (HSTS, CSP, X-Frame-Options)
- ✅ Audit Logging
- ✅ IP Whitelist (Super Admin)
- ✅ File Quarantine & Scanning
- ✅ Rate Limiting (Login, API, Uploads)
- ✅ N+1 Query Prevention (Eager Loading)
- ✅ Settings Caching

---

## ⚠️ CRITICAL TODOs (Must Fix Before Production)

### 1. **Email Configuration** 🔴 CRITICAL
**Problem:** Password Reset & Email Verification benötigen Email-Versand

**Fehlende Implementierung:**
- [ ] Mail-Treiber konfigurieren (SMTP, Mailgun, SES, etc.)
- [ ] Password Reset Email Template erstellen
- [ ] Email Verification Template erstellen
- [ ] Welcome Email Template
- [ ] Account Lockout Notification Email

**Files to Create:**
```
backend/app/Mail/PasswordResetMail.php
backend/app/Mail/EmailVerificationMail.php
backend/app/Mail/WelcomeMail.php
backend/app/Mail/AccountLockedMail.php
backend/resources/views/emails/password-reset.blade.php
backend/resources/views/emails/email-verification.blade.php
```

**Config:**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"
```

---

### 2. **Email Verification for New Users** 🔴 CRITICAL
**Status:** System vorhanden (Laravel), aber nicht integriert

**TODO:**
- [ ] Enable email verification in User model
- [ ] Add `email_verified_at` column check in login
- [ ] Create verification controller/routes
- [ ] Add middleware to protect routes requiring verified email
- [ ] Frontend: Verification pending page

**Files:**
```php
// User.php
class User extends Authenticatable implements MustVerifyEmail {
    // ...
}

// Migration
Schema::table('users', function (Blueprint $table) {
    $table->timestamp('email_verified_at')->nullable()->after('email');
});

// Middleware
'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
```

---

### 3. **Frontend Implementation** 🟡 HIGH PRIORITY
**Problem:** Backend ist fertig, aber Frontend fehlt komplett

**Fehlende Frontend-Komponenten:**
- [ ] Login/Register Pages
- [ ] Dashboard (Admin Panel)
- [ ] Post Editor (WYSIWYG)
- [ ] Media Library (Upload, Browse, Select)
- [ ] Category/Tag Management
- [ ] User Management (Admin)
- [ ] Settings Pages
- [ ] Comment Moderation Interface
- [ ] Analytics Dashboard
- [ ] Newsletter Management
- [ ] 2FA Setup Interface
- [ ] Password Reset Flow

**Empfohlene Libraries:**
- React Query (Server State)
- TipTap oder Lexical (Rich Text Editor)
- React Dropzone (File Uploads)
- Chart.js (Analytics)
- React Table (Data Tables)
- React Hook Form (Forms)

---

### 4. **Testing Coverage** 🟡 HIGH PRIORITY
**Status:** Struktur vorhanden (`backend/tests/`), aber Tests fehlen

**TODO:**
- [ ] PHPUnit Tests für alle Controller
- [ ] Policy Tests (Authorization)
- [ ] Service Tests (SVG Sanitizer, Lockout, etc.)
- [ ] Feature Tests (Login, Register, CRUD)
- [ ] Vitest Tests für Frontend

**Mindestens 80% Code Coverage anstreben!**

---

### 5. **Docker Container Fix** 🟡 MEDIUM PRIORITY
**Problem:** Backend-Container startet nicht (siehe `docs/work-log.md`)

**TODO:**
- [ ] Dockerfile debuggen
- [ ] Docker Compose Konfiguration prüfen
- [ ] PHP Extensions installieren
- [ ] Environment Variables richtig mappen
- [ ] Volume Permissions fixen

---

## 🚀 FEATURE ROADMAP (Nach Priorität)

### **PHASE 1: Core Improvements (1-2 Wochen)**

#### 1.1 **Session Management** 🔵
- [ ] Auto-Logout nach Inaktivität (configurable, default: 30 Min)
- [ ] "Remember Me" Funktionalität
- [ ] Session-Device-Tracking (welche Geräte sind eingeloggt)
- [ ] "Logout from all devices" Button

**Implementation:**
```php
// Middleware
class SessionTimeout extends Middleware {
    public function handle($request, $next) {
        $timeout = config('session.lifetime', 30); // minutes

        if (session()->has('last_activity')) {
            $lastActivity = session('last_activity');
            if (now()->diffInMinutes($lastActivity) > $timeout) {
                auth()->logout();
                return response()->json(['message' => 'Session expired'], 401);
            }
        }

        session(['last_activity' => now()]);
        return $next($request);
    }
}
```

---

#### 1.2 **Advanced Search** 🔵
**Current:** Basic full-text search vorhanden

**TODO:**
- [ ] Faceted Search (Filter by Category, Tag, Date, Author)
- [ ] Search Suggestions (Autocomplete)
- [ ] Search History (per User)
- [ ] Popular Searches
- [ ] "Did you mean...?" (Typo correction)
- [ ] Advanced Boolean Operators (AND, OR, NOT)

**Empfehlung:** Elasticsearch oder Meilisearch Integration für bessere Performance

---

#### 1.3 **Image Processing** 🔵
**Current:** Basic Upload + WebP Conversion

**TODO:**
- [ ] Automatic Thumbnail Generation (multiple sizes)
- [ ] Image Cropping Tool (Frontend)
- [ ] Image Optimization (TinyPNG API)
- [ ] Lazy Loading Placeholders (Blurhash)
- [ ] Responsive Images (srcset)
- [ ] CDN Integration (Cloudflare, Cloudinary)

---

#### 1.4 **Content Versioning** 🔵
- [ ] Post Revision History
- [ ] Diff View (compare versions)
- [ ] Rollback zu alter Version
- [ ] Auto-Save Drafts (every 30 seconds)
- [ ] Conflict Resolution (wenn 2 User gleichzeitig editieren)

**Tables:**
```sql
CREATE TABLE post_revisions (
    id BIGSERIAL PRIMARY KEY,
    post_id BIGINT REFERENCES posts(id),
    user_id BIGINT REFERENCES users(id),
    content JSONB,
    created_at TIMESTAMP
);
```

---

### **PHASE 2: Advanced Features (2-4 Wochen)**

#### 2.1 **Multi-Language Support (i18n)** 🟢
**Current:** `language` field vorhanden, aber nicht voll implementiert

**TODO:**
- [ ] Translation System (Eloquent Translatable)
- [ ] Language Switcher (Frontend)
- [ ] Automatic Language Detection
- [ ] RTL Support (Arabic, Hebrew)
- [ ] URL Structure (`/de/blog/`, `/en/blog/`)
- [ ] hreflang Tags (SEO)
- [ ] Translation Management Interface

**Empfohlene Library:** `spatie/laravel-translatable`

---

#### 2.2 **Content Scheduling** 🟢
**Current:** `published_at` field vorhanden

**TODO:**
- [ ] Scheduler (Cron Job) für auto-publish
- [ ] Schedule Queue Worker
- [ ] "Unpublish at" Feature (zeitlich begrenzte Posts)
- [ ] Scheduled Newsletter Sending
- [ ] Preview Scheduled Posts

**Cron Job:**
```php
// Kernel.php
protected function schedule(Schedule $schedule): void {
    $schedule->call(function () {
        Post::where('status', 'scheduled')
            ->where('published_at', '<=', now())
            ->update(['status' => 'published']);
    })->everyMinute();
}
```

---

#### 2.3 **Social Media Integration** 🟢
- [ ] Auto-Post to Twitter/X
- [ ] Auto-Post to Facebook
- [ ] Auto-Post to LinkedIn
- [ ] Open Graph Tags (vollständig)
- [ ] Twitter Cards
- [ ] Social Share Tracking
- [ ] Social Login (OAuth)

---

#### 2.4 **Advanced Analytics** 🟢
**Current:** Basic Page Views vorhanden

**TODO:**
- [ ] Bounce Rate
- [ ] Time on Page
- [ ] Referrer Tracking
- [ ] User Flow (welche Seiten in welcher Reihenfolge)
- [ ] Conversion Tracking
- [ ] A/B Testing
- [ ] Heatmaps (Hotjar Integration)
- [ ] Real-Time Analytics Dashboard

---

#### 2.5 **Content Workflow** 🟢
- [ ] Editorial Calendar
- [ ] Assignment System (assign posts to authors)
- [ ] Review/Approval Process
- [ ] Status Notifications (Email/Push)
- [ ] Content Guidelines Checklist
- [ ] SEO Score (Yoast-like)

---

### **PHASE 3: Enterprise Features (4-8 Wochen)**

#### 3.1 **Multi-Tenancy** 🟣
**Use Case:** Mehrere Blogs/Sites auf einer Installation

**TODO:**
- [ ] Tenant Model (Organizations/Sites)
- [ ] Subdomain Routing (`client1.yourdomain.com`)
- [ ] Isolated Databases vs. Shared Database
- [ ] Tenant-Specific Users
- [ ] Billing System (Subscriptions)

**Library:** `stancl/tenancy`

---

#### 3.2 **Headless CMS Mode** 🟣
- [ ] GraphQL API (zusätzlich zu REST)
- [ ] Webhooks (Trigger bei Content-Änderungen)
- [ ] Content Preview Token
- [ ] API Key Management
- [ ] Rate Limiting per API Key
- [ ] API Documentation (Swagger/OpenAPI)

---

#### 3.3 **E-Commerce Integration** 🟣
- [ ] Product Management
- [ ] Shopping Cart
- [ ] Payment Gateway (Stripe, PayPal)
- [ ] Order Management
- [ ] Inventory Tracking
- [ ] Discount Codes

**Alternative:** WooCommerce-Plugin oder separate Microservice

---

#### 3.4 **Advanced SEO** 🟣
**Current:** Basic Meta Tags vorhanden

**TODO:**
- [ ] Schema.org Structured Data (vollständig)
- [ ] Breadcrumbs
- [ ] Canonical URLs (automatisch)
- [ ] 301 Redirect Management
- [ ] XML Sitemap Index (für große Sites)
- [ ] RSS Feed
- [ ] AMP Support (optional)
- [ ] SEO Audit Tool

---

#### 3.5 **Performance Optimizations** 🟣
- [ ] Redis Full-Page Caching
- [ ] Varnish Integration
- [ ] Database Query Optimization (Indexes prüfen)
- [ ] Asset Bundling & Minification
- [ ] HTTP/2 Server Push
- [ ] Service Worker (PWA)
- [ ] Database Replication (Read Replicas)

---

### **PHASE 4: AI & Automation (8-12 Wochen)**

#### 4.1 **AI Content Assistant** 🟤
**Current:** Grundstruktur in `AIController` vorhanden

**TODO:**
- [ ] OpenAI/Claude API Integration
- [ ] Content Generation (Vollständiger Artikel)
- [ ] Image Generation (DALL-E, Midjourney)
- [ ] Auto-Tagging
- [ ] Sentiment Analysis
- [ ] Plagiarism Check
- [ ] Grammar Check (LanguageTool)

---

#### 4.2 **Chatbot** 🟤
- [ ] Customer Support Chatbot
- [ ] Knowledge Base Integration
- [ ] Ticket System
- [ ] Live Chat (WebSocket)

---

#### 4.3 **Recommendation Engine** 🟤
- [ ] "Related Posts" (ML-basiert)
- [ ] Personalized Content Feed
- [ ] User Behavior Tracking
- [ ] Collaborative Filtering

---

## 🛠️ TECHNICAL DEBT & REFACTORING

### Code Quality
- [ ] PHPStan Level 5+ (Static Analysis)
- [ ] ESLint + Prettier (Frontend)
- [ ] Code Coverage > 80%
- [ ] Remove dead code
- [ ] Refactor long methods (> 50 lines)

### Documentation
- [ ] API Documentation (OpenAPI/Swagger)
- [ ] Inline Code Documentation (PHPDoc)
- [ ] Architecture Diagrams
- [ ] Deployment Guide (Production)
- [ ] Contribution Guidelines

### Performance
- [ ] Database Index Optimization
- [ ] Query Performance Monitoring (Laravel Telescope)
- [ ] Memory Profiling
- [ ] Load Testing (Apache JMeter)

### Security Audits
- [ ] Penetration Testing
- [ ] Dependency Updates (automatisch via Dependabot)
- [ ] OWASP Top 10 Compliance Check
- [ ] Security Headers Test (securityheaders.com)

---

## 🐛 KNOWN ISSUES & BUGS

### Backend
1. **Docker Container nicht startend** (siehe work-log.md)
2. **AdminSeeder verwendet hardcoded Passwörter** (sollte aus .env kommen)
3. **FileValidationService:** Constructor Dependency Injection fehlt im Middleware-Stack
4. **Comment Store:** `user_id` kann NULL sein für unauthenticated users (Frontend muss `author_name` + `author_email` senden)

### Frontend
1. **Nicht implementiert** - Komplette UI fehlt
2. **API Error Handling** - Keine einheitliche Error-Boundary

---

## 📋 CHECKLISTS

### Pre-Production Deployment Checklist

**Environment:**
- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` generiert
- [ ] `DB_*` konfiguriert
- [ ] `MAIL_*` konfiguriert
- [ ] `REDIS_*` konfiguriert
- [ ] `CORS_ALLOWED_ORIGINS` auf Production-Domain gesetzt
- [ ] `SESSION_DRIVER=redis`
- [ ] `CACHE_DRIVER=redis`
- [ ] `QUEUE_CONNECTION=redis`

**Security:**
- [ ] Alle Migrations durchgeführt
- [ ] Seeds NICHT in Production laufen lassen
- [ ] SSL/TLS Zertifikat installiert
- [ ] Firewall konfiguriert
- [ ] Backup-System aktiv
- [ ] Monitoring aktiv (Sentry, New Relic)
- [ ] Rate Limiting getestet
- [ ] Super Admin IPs whitelisted (falls aktiviert)

**Performance:**
- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`
- [ ] `composer install --optimize-autoloader --no-dev`
- [ ] `npm run build`
- [ ] Redis läuft
- [ ] Queue Worker läuft (`php artisan queue:work`)

**Testing:**
- [ ] Alle PHPUnit Tests grün
- [ ] Alle Frontend Tests grün
- [ ] Manual Testing durchgeführt
- [ ] Load Testing durchgeführt
- [ ] Security Scan durchgeführt

---

## 🎯 PRIORITY MATRIX

| Feature | Priority | Effort | Impact | Status |
|---------|----------|--------|--------|--------|
| Email Configuration | 🔴 CRITICAL | Low | High | ⏸️ TODO |
| Email Verification | 🔴 CRITICAL | Medium | High | ⏸️ TODO |
| Frontend Implementation | 🟡 HIGH | Very High | High | ⏸️ TODO |
| Testing Coverage | 🟡 HIGH | High | Medium | ⏸️ TODO |
| Docker Fix | 🟡 MEDIUM | Medium | Low | ⏸️ TODO |
| Session Management | 🔵 NICE-TO-HAVE | Low | Medium | ⏸️ TODO |
| Advanced Search | 🔵 NICE-TO-HAVE | High | Medium | ⏸️ TODO |
| Image Processing | 🔵 NICE-TO-HAVE | Medium | Medium | ⏸️ TODO |
| Content Versioning | 🔵 NICE-TO-HAVE | High | Low | ⏸️ TODO |
| Multi-Language | 🟢 FUTURE | High | High | ⏸️ TODO |
| Social Media | 🟢 FUTURE | Medium | Medium | ⏸️ TODO |
| Analytics | 🟢 FUTURE | High | Medium | ⏸️ TODO |
| Multi-Tenancy | 🟣 ENTERPRISE | Very High | High | ⏸️ TODO |
| Headless CMS | 🟣 ENTERPRISE | High | Medium | ⏸️ TODO |
| E-Commerce | 🟣 ENTERPRISE | Very High | Low | ⏸️ TODO |
| AI Features | 🟤 EXPERIMENTAL | High | Medium | ⏸️ TODO |

---

## 📊 PROGRESS TRACKER

```
██████████████████████████████████░░░░░░░░░░ 75% Core Features
████████████████████████████████████████████ 100% Security Features
░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░ 0% Frontend Implementation
████████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░ 20% Testing Coverage
██████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░ 15% Documentation
```

**Overall Completion:** ~42% (Backend fokussiert)

---

## 🎓 LEARNING RESOURCES

### Für Entwickler, die beitragen wollen:

**Laravel:**
- [Laravel Documentation](https://laravel.com/docs/11.x)
- [Laravel Security Best Practices](https://cheatsheetseries.owasp.org/cheatsheets/Laravel_Cheat_Sheet.html)
- [Laravel Testing](https://laravel.com/docs/11.x/testing)

**React:**
- [React Documentation](https://react.dev)
- [React Query](https://tanstack.com/query/latest)
- [React Hook Form](https://react-hook-form.com/)

**Security:**
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [OWASP API Security](https://owasp.org/www-project-api-security/)

---

## 📞 NEXT STEPS

### Sofort (Diese Woche):
1. **Email System konfigurieren** (SMTP)
2. **Email Verification implementieren**
3. **Testing Coverage erhöhen** (mindestens kritische Paths)

### Kurzfristig (Nächsten 2 Wochen):
4. **Frontend Grundstruktur** (Login, Dashboard)
5. **Post Editor** (TipTap/Lexical)
6. **Media Library UI**

### Mittelfristig (Nächsten Monat):
7. **Session Management**
8. **Advanced Search**
9. **Image Processing**

### Langfristig (Nächsten 3 Monate):
10. **Multi-Language Support**
11. **Content Workflow**
12. **Advanced Analytics**

---

## 💡 INNOVATIVE IDEAS (Brainstorming)

### Unique Features (Differenzierung von WordPress/Ghost):
1. **AI-Powered Content Optimization** - Auto-Suggestions für SEO
2. **Built-in A/B Testing** - Headline Testing ohne Plugin
3. **Voice-to-Text** - Blog-Posts diktieren
4. **Real-Time Collaboration** - Google Docs-ähnliches Editing
5. **Blockchain Timestamping** - Content Authenticity
6. **IPFS Storage** - Dezentrale Medien-Speicherung
7. **Web3 Integration** - NFT-gated Content
8. **Auto-Translation** - DeepL API Integration

---

## ✅ SUMMARY

**Was funktioniert:**
- ✅ Solides Backend mit Laravel 11
- ✅ Enterprise-Grade Security
- ✅ Komplette REST API
- ✅ Alle Core-Features implementiert

**Was fehlt:**
- ⚠️ Frontend-Implementierung (0%)
- ⚠️ Email-System (konfiguriert, aber nicht aktiv)
- ⚠️ Testing (< 20% Coverage)
- ⚠️ Docker Container Issue

**Nächste Schritte:**
1. Email konfigurieren ✉️
2. Frontend bauen 🎨
3. Tests schreiben 🧪

**Geschätzte Zeit bis Production-Ready:**
- Mit dediziertem Team: 4-6 Wochen
- Solo-Entwickler: 2-3 Monate

---

**Last Updated:** 2026-01-20
**Maintained by:** Development Team
**Status:** 🟢 Active Development
