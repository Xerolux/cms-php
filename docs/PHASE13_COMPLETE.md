# Phase 13: Two-Factor Authentication (2FA) - ABGESCHLOSSEN ✅

## Übersicht

Die Two-Factor Authentication (2FA) wurde erfolgreich implementiert! Admins können jetzt ihr Konto mit TOTP (Time-based One-Time Password) schützen, kompatibel mit Google Authenticator, Authy, Microsoft Authenticator und anderen Apps.

## Backend Implementierung

### 1. Database Migration

**Datei:** `backend/database/migrations/2024_01_20_000017_add_two_factor_auth_to_users_table.php`

```php
Schema::table('users', function (Blueprint $table) {
    $table->text('two_factor_secret')->nullable();
    $table->text('two_factor_recovery_codes')->nullable();
    $table->timestamp('two_factor_confirmed_at')->nullable();
});
```

**Spalten:**
- `two_factor_secret` - Verschlüsselter TOTP Secret
- `two_factor_recovery_codes` - 8 Recovery Codes (verschlüsselt)
- `two_factor_confirmed_at` - Bestätigungs-Zeitpunkt

### 2. User Model Erweiterungen

**Datei:** `backend/app/Models/User.php`

**Neue Attribute:**
```php
protected $hidden = [
    'password',
    'remember_token',
    'two_factor_secret',
    'two_factor_recovery_codes',
];

protected $appends = [
    'has_two_factor_enabled',
];
```

**Neue Methoden:**

**Status Check:**
```php
public function getHasTwoFactorEnabledAttribute(): bool
{
    return !is_null($this->two_factor_secret) &&
           !is_null($this->two_factor_confirmed_at);
}
```

**Enable 2FA:**
```php
public function enableTwoFactorAuthentication(string $secret, array $recoveryCodes): void
{
    $this->update([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        'two_factor_confirmed_at' => now(),
    ]);
}
```

**Disable 2FA:**
```php
public function disableTwoFactorAuthentication(): void
{
    $this->update([
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
        'two_factor_confirmed_at' => null,
    ]);
}
```

**Verify Code:**
```php
public function verifyTwoFactorCode(string $code): bool
{
    // Check recovery codes first
    $recoveryCodes = $this->recovery_codes;
    $codeIndex = array_search($code, $recoveryCodes);

    if ($codeIndex !== false) {
        // Remove used recovery code
        unset($recoveryCodes[$codeIndex]);
        $this->update([
            'two_factor_recovery_codes' => encrypt(json_encode(array_values($recoveryCodes))),
        ]);
        return true;
    }

    // Verify TOTP code
    return $this->verifyTotpCode($code);
}
```

**TOTP Algorithmus (Google Authenticator kompatibel):**
```php
protected function verifyTotpCode(string $code): bool
{
    $secret = $this->two_factor_secret;
    $timeWindow = 30; // 30 seconds
    $currentTime = floor(time() / $timeWindow);

    // Check current, previous, and next time windows (for clock drift)
    for ($i = -1; $i <= 1; $i++) {
        $time = ($currentTime + $i) * $timeWindow;
        $expectedCode = $this->generateTotpCode($secret, $time);

        if (hash_equals($expectedCode, $code)) {
            return true;
        }
    }

    return false;
}
```

**QR Code URL:**
```php
public function getTwoFactorQrCodeUrl(): string
{
    $secret = $this->two_factor_secret;
    $email = urlencode($this->email);
    $issuer = urlencode(config('app.name', 'Blog CMS'));

    return "otpauth://totp/{$issuer}:{$email}?secret={$secret}&issuer={$issuer}";
}
```

### 3. TwoFactorAuthMiddleware

**Datei:** `backend/app/Http/Middleware/TwoFactorAuthenticatable.php`

```php
public function handle(Request $request, Closure $next)
{
    $user = Auth::user();

    // Skip if not authenticated or 2FA not enabled
    if (!$user || !$user->two_factor_secret) {
        return $next($request);
    }

    // Allow access to 2FA verification routes
    $allowedRoutes = ['2fa.verify', '2fa.confirm', '2fa.disable', 'auth.logout'];
    if (in_array($request->route()?->getName(), $allowedRoutes)) {
        return $next($request);
    }

    // Check if 2FA session is confirmed
    if (session()->get('2fa.confirmed') !== true) {
        return response()->json([
            'message' => 'Two-factor authentication required',
            'requires_2fa' => true,
        ], 423);
    }

    return $next($request);
}
```

### 4. API Controller

**Datei:** `backend/app/Http/Controllers/Api/V1/TwoFactorAuthController.php`

**Endpoints:**
- `GET /api/v1/2fa/status` - Status prüfen
- `POST /api/v1/2fa/setup` - Secret generieren
- `POST /api/v1/2fa/confirm` - Bestätigen & aktivieren
- `POST /api/v1/2fa/verify` - Code verifizieren (bei Login)
- `POST /api/v1/2fa/disable` - Deaktivieren
- `GET /api/v1/2fa/recovery-codes` - Recovery Codes anzeigen
- `POST /api/v1/2fa/recovery-codes/regenerate` - Neue Recovery Codes

## Frontend Implementierung

### 1. API Service

**Datei:** `frontend/src/services/api.ts`

```typescript
const twoFactorService = {
  async getStatus() {
    const { data } = await api.get('/2fa/status');
    return data;
  },

  async setup() {
    const { data } = await api.post('/2fa/setup');
    return data;
  },

  async confirm(code: string) {
    const { data } = await api.post('/2fa/confirm', { code });
    return data;
  },

  async verify(code: string) {
    const { data } = await api.post('/2fa/verify', { code });
    return data;
  },

  async disable(password: string, code?: string) {
    const { data } = await api.post('/2fa/disable', { password, code });
    return data;
  },

  async getRecoveryCodes() {
    const { data } = await api.get('/2fa/recovery-codes');
    return data;
  },

  async regenerateRecoveryCodes(password: string) {
    const { data } = await api.post('/2fa/recovery-codes/regenerate', { password });
    return data;
  },
};
```

### 2. Profile Page UI

**Datei:** `frontend/src/pages/ProfilePage.tsx`

**Features:**

**Profile Information Card:**
- Name, Email, Role, Status

**2FA Card:**
- Status Tag (Enabled/Disabled)
- Recovery Codes Progress Bar (X/8)
- Enable/Disable Buttons
- View Recovery Codes Button

**Setup Modal (3 Steps):**

1. **Step 1: Scan QR Code**
   - QR Code Bild (200x200)
   - Manual Secret Entry (copyable)
   - Alternative to QR scan

2. **Step 2: Save Recovery Codes**
   - 8 Recovery Codes anzeigen
   - Copy-Button für jeden Code
   - Warnung: Nur einmal nutzbar

3. **Step 3: Verify**
   - 6-stelliger Code Input
   - Monospace Font (letter-spacing)
   - Nur Zahlen möglich
   - Verify & Enable Button

**Disable Modal:**
- Password Bestätigung
- Optional: 2FA Code
- Warnung vor Deaktivierung

**Recovery Codes Modal:**
- Alle 8 Codes auflisten
- Copy-Button pro Code
- Copy All Button
- Download als .txt Datei
- Warnung: Safe speichern!

## TOTP Algorithmus

### Was ist TOTP?

**TOTP** = Time-based One-Time Password
- Basierend auf HMAC-SHA1
- 30-Sekunden Zeitfenster
- 6-stelliger Code
- Kompatibel mit Google Authenticator, Authy, etc.

### Algorithmus:

```php
// 1. Secret (Base32 encoded)
$secret = 'JBSWY3DPEHPK3PXP';

// 2. Current time (Unix timestamp)
$time = floor(time() / 30);

// 3. Pack time as binary
$timeBytes = pack('N', $time);

// 4. Base32 decode secret
$secretBytes = base32_decode($secret);

// 5. Generate HMAC-SHA1
$hash = hash_hmac('sha1', $timeBytes, $secretBytes, true);

// 6. Truncate to 6 digits
$offset = ord($hash[strlen($hash) - 1]) & 0x0F;
$truncatedHash = substr($hash, $offset, 4);

// 7. Convert to number
$code = unpack('N', $truncatedHash)[1];
$code = $code & 0x7FFFFFFF;
$code = $code % 1000000;

// 8. Pad with zeros
$code = str_pad($code, 6, '0', STR_PAD_LEFT);

// Result: "123456"
```

### Clock Drift Tolerance:

```php
// Check ±1 time window (±30 seconds)
for ($i = -1; $i <= 1; $i++) {
    $time = ($currentTime + $i) * $timeWindow;
    $expectedCode = $this->generateTotpCode($secret, $time);

    if (hash_equals($expectedCode, $code)) {
        return true; // Code is valid
    }
}
```

## Recovery Codes

### Was sind Recovery Codes?

- **8 Einweg-Codes** (10-10 alphanumeric)
- **Einmal nutzbar** - nach Gebrauch gelöscht
- **Backup** wenn Authenticator verloren
- **Download** als .txt Datei möglich

### Beispiel:

```
ABC123DEF4-GHI567JKL8
MNO9PQR0STU-VWX1YZA2B3
...
```

### Regenerate:

```bash
POST /api/v1/2fa/recovery-codes/regenerate
{
  "password": "current_password"
}

Response:
{
  "message": "Recovery codes regenerated successfully",
  "recovery_codes": ["ABC123-DEF456", ...]
}
```

⚠️ **Warnung:** Alte Codes werden ungültig!

## QR Code Format

### otpauth:// URL:

```
otpauth://totp/Example%20Service:user@example.com?
  secret=JBSWY3DPEHPK3PXP&
  issuer=Example%20Service
```

### Parameter:

- `type`: totp (Time-based)
- `label`: `issuer:email`
- `secret`: Base32 encoded secret
- `issuer`: Service name
- `algorithm`: SHA1 (default)
- `digits`: 6 (default)
- `period`: 30 (default)

### Frontend QR Code Generation:

```typescript
import QRCode from 'qrcode';

const qrCodeDataUrl = await QRCode.toDataURL(qrCodeUrl);
// Result: data:image/png;base64,iVBORw0KGgo...
```

## Login Flow mit 2FA

### Ohne 2FA:

```
1. POST /auth/login (email + password)
2. Return token + user
```

### Mit 2FA:

```
1. POST /auth/login (email + password)
2. Return token + user + requires_2fa: true

3. Frontend zeigt 2FA Eingabe

4. POST /2fa/verify (code)
5. Set session('2fa.confirmed' = true)

6. Jetzt Zugriff auf alle geschützten Routes
```

### Middleware Check:

```php
// In TwoFactorAuthenticatable middleware
if (session()->get('2fa.confirmed') !== true) {
    return response()->json([
        'message' => 'Two-factor authentication required',
        'requires_2fa' => true,
    ], 423); // HTTP 423 Locked
}
```

## Supported Apps

### iOS:
- **Google Authenticator** - Free
- **Authy** - Free
- **Microsoft Authenticator** - Free
- **1Password** - Paid
- **LastPass Authenticator** - Free

### Android:
- **Google Authenticator** - Free
- **Authy** - Free
- **Microsoft Authenticator** - Free
- **andOTP** - Free, Open Source
- **1Password** - Paid

### Desktop:
- **Authy** - Windows/Mac/Linux
- **WinAuth** - Windows
- **1Password** - Cross-platform

## Best Practices

### ✅ DO:
1. **Recovery Codes sicher speichern**
   - Password Manager
   - Safe/Safe Deposit Box
   - Ausgedruckt, sicher verwahrt

2. **Backup Authenticator App**
   - App-Backup aktivieren
   - Secret separat notieren
   - Multiple Devices (wenn möglich)

3. **Clock Sync**
   - Geräte-Uhr synchronisieren
   - NTP aktivieren
   - Zeitzone korrekt

4. **Test Recovery Codes**
   - Mindestens einmal testen
   - Funktioniert Backup?

### ❌ DON'T:
1. **Recovery Codes nicht teilen**
   - Nicht per Email senden
   - Nicht in Cloud speichern (unverschlüsselt)
   - Nicht screenshoten

2. **Secret nicht weitergeben**
   - Nur im QR Code
   - Nicht teilen/notieren

3. **Device nicht verlieren ohne Backup**
   - Authenticator App verloren?
   - Recovery Codes gelöscht?
   - = Konto gesperrt!

## Security Features

### Encryption:
- `two_factor_secret` - verschlüsselt mit `encrypt()`
- `two_factor_recovery_codes` - verschlüsselt mit `encrypt()`
- App Key nötig zum Entschlüsseln

### Session:
- `2fa.confirmed` - Session-basiert
- Wird bei Logout gelöscht
- Token Refresh setzt Session zurück

### Rate Limiting:
- `throttle:api` (100/Minute)
- Brute-Force Schutz
- Aber erlaubt genügend Versuche

### Time Window:
- ±30 Sekunden Toleranz
- Verzeiht Clock Drift
- Maximal 90 Sekunden Abweichung

## API Examples

### Get Status:

```bash
GET /api/v1/2fa/status

Response:
{
  "enabled": true,
  "confirmed_at": "2024-01-20T10:00:00Z",
  "qr_code_url": null,
  "recovery_codes_remaining": 8
}
```

### Setup:

```bash
POST /api/v1/2fa/setup

Response:
{
  "secret": "JBSWY3DPEHPK3PXP",
  "qr_code_url": "otpauth://totp/...",
  "recovery_codes": [
    "ABC123DEF4-GHI567JKL8",
    "MNO9PQR0STU-VWX1YZA2B3",
    ...
  ]
}
```

### Confirm:

```bash
POST /api/v1/2fa/confirm
{
  "code": "123456"
}

Response:
{
  "message": "Two-factor authentication enabled successfully",
  "recovery_codes": [...]
}
```

### Verify (Login):

```bash
POST /api/v1/2fa/verify
{
  "code": "654321"
}

Response:
{
  "message": "Two-factor authentication verified successfully"
}
```

### Disable:

```bash
POST /api/v1/2fa/disable
{
  "password": "current_password",
  "code": "123456" // optional
}

Response:
{
  "message": "Two-factor authentication disabled successfully"
}
```

## Troubleshooting

### Problem: "Invalid verification code"

**Mögliche Ursachen:**
1. Uhrzeit falsch (mehr als ±30 Sekunden)
2. Falscher Secret
3. Zeitzone falsch
4. Code bereits abgelaufen (30 Sekunden)

**Lösungen:**
1. Gerät-Uhr synchronisieren
2. NTP aktivieren
3. Zeitzone prüfen
4. Neuen Code generieren

### Problem: Recovery Codes verloren

**Lösung:**
- Neue Recovery Codes generieren
- Alte werden ungültig
- Backup erstellen!

### Problem: Authenticator App verloren

**Lösung:**
- Recovery Code verwenden
- Danach: Neue Recovery Codes generieren
- 2FA neu einrichten

## Features Zusammenfassung

### Backend:
- ✅ TOTP Algorithmus (Google Authenticator kompatibel)
- ✅ Recovery Codes (8 Stück)
- ✅ Encryption (Secret + Codes)
- ✅ Session-based Confirmation
- ✅ Middleware (2FA Check)
- ✅ Clock Drift Tolerance (±30 Sekunden)
- ✅ QR Code URL Generator

### Frontend:
- ✅ Profile Page mit 2FA Management
- ✅ Setup Wizard (3 Steps)
- ✅ QR Code Anzeige
- ✅ Recovery Codes Modal
- ✅ Copy to Clipboard
- ✅ Download als .txt
- ✅ 2FA Status Badge
- ✅ Recovery Codes Progress Bar

---

**Phase 13 Status:** ✅ KOMPLETT

Die Two-Factor Authentication ist voll funktionsfähig mit TOTP, Recovery Codes und kompatibel mit allen gängigen Authenticator Apps!
