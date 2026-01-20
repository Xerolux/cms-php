# Email and Session Management Features

**Date:** 2026-01-20
**Version:** 1.0
**Status:** ✅ Completed

## Overview

This document details the newly implemented email and session management features for the CMS platform.

---

## 📧 EMAIL SYSTEM

### Password Reset Emails

**Purpose:** Send professional, branded emails for password reset requests.

**Features:**
- HTML and plain text email templates
- Secure token-based reset links
- 1-hour token expiration
- Security notices and best practices included
- Mobile-responsive design

**Files Created:**
- `app/Mail/PasswordResetMail.php` - Mailable class
- `resources/views/emails/password-reset.blade.php` - HTML template
- `resources/views/emails/password-reset-text.blade.php` - Plain text template

**Updated Files:**
- `app/Http/Controllers/Api/V1/PasswordResetController.php` - Now sends actual emails

**Email Template Features:**
- Clickable "Reset Password" button
- Fallback URL for copy/paste
- Security warnings (1-hour expiry, single-use)
- Password strength recommendations
- Professional branding

**Configuration Required:**
```env
# Add to .env
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-email@example.com
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="${APP_NAME}"
```

---

## ✉️ EMAIL VERIFICATION

### User Email Verification

**Purpose:** Verify user email addresses during registration to ensure valid contact information.

**Features:**
- Automatic verification email on registration
- 24-hour token expiration
- Resend verification email endpoint
- Check verification status endpoint
- Optional email verification requirement

**Database:**
- Migration: `2026_01_20_100005_create_email_verification_tokens_table.php`
- Table: `email_verification_tokens` (email, token, created_at)

**Files Created:**
- `app/Mail/EmailVerificationMail.php` - Verification mailable
- `resources/views/emails/email-verification.blade.php` - HTML template
- `resources/views/emails/email-verification-text.blade.php` - Plain text template
- `app/Http/Controllers/Api/V1/EmailVerificationController.php` - Verification endpoints
- `app/Http/Middleware/EnsureEmailIsVerified.php` - Verification middleware

**Updated Files:**
- `app/Models/User.php` - Implements `MustVerifyEmail` interface
- `app/Http/Controllers/Api/V1/AuthController.php` - Sends verification on registration
- `backend/bootstrap/app.php` - Registers 'verified' middleware alias

### API Endpoints

#### Public Endpoints:

**Register User:**
```
POST /api/v1/auth/register
```
Request:
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "StrongP@ssw0rd123",
  "password_confirmation": "StrongP@ssw0rd123"
}
```

Response (201):
```json
{
  "message": "Registration successful. Please check your email to verify your account.",
  "user": {...},
  "token": "1|abc123...",
  "email_verified": false
}
```

**Verify Email:**
```
POST /api/v1/auth/email/verify
```
Request:
```json
{
  "email": "john@example.com",
  "token": "verification_token_from_email"
}
```

Response (200):
```json
{
  "message": "Email verified successfully"
}
```

#### Authenticated Endpoints:

**Resend Verification Email:**
```
POST /api/v1/auth/email/resend
Headers: Authorization: Bearer {token}
```

Response (200):
```json
{
  "message": "Verification email sent successfully"
}
```

**Check Verification Status:**
```
GET /api/v1/auth/email/status
Headers: Authorization: Bearer {token}
```

Response (200):
```json
{
  "verified": true,
  "email": "john@example.com",
  "verified_at": "2026-01-20T10:30:00.000000Z"
}
```

### Requiring Email Verification

To require email verification for specific routes, add the `verified` middleware:

```php
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    // These routes require verified email
    Route::post('/posts', [PostController::class, 'store']);
});
```

If user is not verified, they'll receive:
```json
{
  "error": "Email verification required",
  "message": "Your email address is not verified. Please check your email for a verification link.",
  "email_verified": false
}
```

---

## 🔐 SESSION MANAGEMENT

### Advanced Session Tracking

**Purpose:** Track all user sessions with device information, enable session management, and support auto-logout.

**Features:**
- Track all active sessions per user
- Device detection (browser, OS, device type)
- IP address tracking
- Last activity tracking
- Session expiration
- Auto-logout after inactivity
- Revoke individual sessions
- Revoke all sessions except current
- Session heartbeat for keeping sessions alive

**Database:**
- Migration: `2026_01_20_100006_create_user_sessions_table.php`
- Table: `user_sessions`

**Files Created:**
- `app/Models/UserSession.php` - Session model
- `app/Services/SessionManagementService.php` - Session management logic
- `app/Http/Controllers/Api/V1/SessionController.php` - Session API
- `app/Http/Middleware/TrackSessionActivity.php` - Auto-track session activity

**Updated Files:**
- `app/Http/Controllers/Api/V1/AuthController.php` - Creates sessions on login/register
- `backend/bootstrap/app.php` - Applies session tracking middleware
- `backend/routes/api.php` - Session routes

### Session Schema

```sql
CREATE TABLE user_sessions (
    id BIGINT PRIMARY KEY,
    user_id BIGINT (FK to users),
    token_id VARCHAR (Sanctum token ID),
    device_name VARCHAR,
    browser VARCHAR,
    platform VARCHAR (Operating System),
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity_at TIMESTAMP,
    expires_at TIMESTAMP NULLABLE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Device Detection

The system automatically detects:
- **Browser**: Chrome, Firefox, Safari, Edge
- **Platform**: Windows, macOS, Linux, Android, iOS
- **Device Type**: Desktop, Mobile, Tablet

Example session data:
```json
{
  "device": "Desktop",
  "browser": "Chrome 120.0.6099.129",
  "platform": "Windows 10",
  "ip_address": "192.168.1.100"
}
```

### API Endpoints

#### Get All Active Sessions
```
GET /api/v1/sessions
Headers: Authorization: Bearer {token}
```

Response (200):
```json
{
  "total_active_sessions": 3,
  "sessions": [
    {
      "id": 1,
      "token_id": "1",
      "device": "Desktop",
      "browser": "Chrome 120.0",
      "platform": "Windows 10",
      "ip_address": "192.168.1.100",
      "last_activity": "2 minutes ago",
      "last_activity_at": "2026-01-20T10:28:00.000000Z",
      "is_current": true
    },
    {
      "id": 2,
      "token_id": "2",
      "device": "Mobile",
      "browser": "Safari 17.0",
      "platform": "iOS",
      "ip_address": "192.168.1.101",
      "last_activity": "1 hour ago",
      "last_activity_at": "2026-01-20T09:30:00.000000Z",
      "is_current": false
    }
  ]
}
```

#### Revoke a Specific Session
```
DELETE /api/v1/sessions/{tokenId}
Headers: Authorization: Bearer {token}
```

Response (200):
```json
{
  "message": "Session revoked successfully"
}
```

Note: Cannot revoke current session (use logout instead).

#### Revoke All Other Sessions
```
DELETE /api/v1/sessions
Headers: Authorization: Bearer {token}
```

Response (200):
```json
{
  "message": "All other sessions have been revoked",
  "revoked_count": 2
}
```

This is useful for "Log out all other devices" functionality.

#### Session Heartbeat (Keep-Alive)
```
POST /api/v1/sessions/heartbeat
Headers: Authorization: Bearer {token}
```

Response (200):
```json
{
  "message": "Session activity updated",
  "timestamp": "2026-01-20T10:30:00.000000Z"
}
```

**Frontend Usage:**
```javascript
// Call every 5 minutes to prevent auto-logout
setInterval(async () => {
  await fetch('/api/v1/sessions/heartbeat', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`
    }
  });
}, 5 * 60 * 1000); // 5 minutes
```

### Auto-Logout Mechanism

**How it works:**

1. **Middleware Tracking**: `TrackSessionActivity` middleware automatically updates `last_activity_at` on every API request
2. **Inactivity Detection**: Sessions with no activity for 30+ minutes are considered inactive
3. **Cleanup Command**: Run periodic cleanup to remove expired sessions:

```bash
# Add to scheduler (app/Console/Kernel.php)
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        app(SessionManagementService::class)->cleanupExpiredSessions();
    })->hourly();
}
```

4. **Session Expiry**: If `SESSION_LIFETIME` is set, sessions automatically expire after that duration

**Configuration:**
```env
# In .env (optional)
SESSION_LIFETIME=120  # Minutes (2 hours)
```

### Session Lifecycle

1. **Login/Register** → Session created with device info
2. **Each API Request** → `last_activity_at` updated automatically
3. **30 Minutes Inactivity** → Session marked as inactive
4. **Session Expiry** → Session and token deleted
5. **Logout** → Session and token immediately revoked

---

## 🔧 DEPLOYMENT STEPS

### 1. Run Migrations

```bash
cd backend
php artisan migrate
```

This creates:
- `email_verification_tokens` table
- `user_sessions` table

### 2. Configure Email

Add to `.env`:

```env
# Email Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"

# Frontend URL (for email links)
FRONTEND_URL=https://yourdomain.com
```

### 3. Test Email Sending

```bash
# Test in tinker
php artisan tinker

# Send test password reset email
$user = User::first();
Mail::to($user)->send(new \App\Mail\PasswordResetMail(
    'test-token-123',
    $user->email,
    'http://localhost:5173/reset-password?token=test-token-123'
));
```

### 4. Set Up Scheduler (for session cleanup)

```bash
# Add to crontab
* * * * * cd /path-to-project/backend && php artisan schedule:run >> /dev/null 2>&1
```

### 5. Frontend Integration

**Registration Flow:**
```javascript
const register = async (name, email, password) => {
  const response = await fetch('/api/v1/auth/register', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      name,
      email,
      password,
      password_confirmation: password
    })
  });

  const data = await response.json();
  // data.token - Store in localStorage
  // data.email_verified - Show "Please verify email" message
};
```

**Email Verification Flow:**
```javascript
// On verification page (/verify-email?token=xxx&email=xxx)
const verifyEmail = async (token, email) => {
  const response = await fetch('/api/v1/auth/email/verify', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ token, email })
  });

  if (response.ok) {
    // Show success message, redirect to dashboard
  }
};
```

**Session Management UI:**
```javascript
const SessionsList = () => {
  const [sessions, setSessions] = useState([]);

  useEffect(() => {
    fetch('/api/v1/sessions', {
      headers: { 'Authorization': `Bearer ${token}` }
    })
    .then(r => r.json())
    .then(data => setSessions(data.sessions));
  }, []);

  const revokeSession = async (tokenId) => {
    await fetch(`/api/v1/sessions/${tokenId}`, {
      method: 'DELETE',
      headers: { 'Authorization': `Bearer ${token}` }
    });
    // Refresh sessions list
  };

  return (
    <div>
      {sessions.map(session => (
        <div key={session.id}>
          <div>{session.device} - {session.browser}</div>
          <div>{session.platform} - {session.ip_address}</div>
          <div>Last active: {session.last_activity}</div>
          {!session.is_current && (
            <button onClick={() => revokeSession(session.token_id)}>
              Revoke
            </button>
          )}
        </div>
      ))}
    </div>
  );
};
```

---

## 🧪 TESTING

### Test Password Reset

```bash
# 1. Request password reset
curl -X POST http://localhost:8000/api/v1/auth/password/reset-request \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com"}'

# 2. Check email for token

# 3. Reset password
curl -X POST http://localhost:8000/api/v1/auth/password/reset \
  -H "Content-Type: application/json" \
  -d '{
    "email":"user@example.com",
    "token":"token-from-email",
    "password":"NewStrongP@ssw0rd123",
    "password_confirmation":"NewStrongP@ssw0rd123"
  }'
```

### Test Email Verification

```bash
# 1. Register new user
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name":"Test User",
    "email":"test@example.com",
    "password":"StrongP@ssw0rd123",
    "password_confirmation":"StrongP@ssw0rd123"
  }'

# 2. Check email for verification token

# 3. Verify email
curl -X POST http://localhost:8000/api/v1/auth/email/verify \
  -H "Content-Type: application/json" \
  -d '{
    "email":"test@example.com",
    "token":"token-from-email"
  }'

# 4. Check verification status
curl -X GET http://localhost:8000/api/v1/auth/email/status \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Test Session Management

```bash
# 1. Login from multiple devices/browsers to create sessions

# 2. Get all sessions
curl -X GET http://localhost:8000/api/v1/sessions \
  -H "Authorization: Bearer YOUR_TOKEN"

# 3. Revoke a specific session
curl -X DELETE http://localhost:8000/api/v1/sessions/TOKEN_ID \
  -H "Authorization: Bearer YOUR_TOKEN"

# 4. Revoke all other sessions
curl -X DELETE http://localhost:8000/api/v1/sessions \
  -H "Authorization: Bearer YOUR_TOKEN"

# 5. Send heartbeat
curl -X POST http://localhost:8000/api/v1/sessions/heartbeat \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 📊 SUMMARY

### New Features Implemented

| Feature | Files Created | Files Modified | Status |
|---------|---------------|----------------|--------|
| Password Reset Emails | 3 | 1 | ✅ Complete |
| Email Verification | 5 | 4 | ✅ Complete |
| Session Management | 4 | 4 | ✅ Complete |

**Total:**
- **New Files:** 12
- **Modified Files:** 9
- **Migrations:** 2
- **API Endpoints:** 10

### Configuration Files

**New:**
- `config/app.php` - Frontend URL configuration

**Updated:**
- `.env.example` - Added `FRONTEND_URL`

### Security Improvements

1. **Email Verification** - Prevents fake email registrations
2. **Session Tracking** - Monitor unauthorized access
3. **Device Detection** - Identify suspicious logins
4. **Auto-Logout** - Prevent session hijacking
5. **Session Revocation** - User-controlled security

---

## 🎯 NEXT STEPS

### Critical (Before Production)

1. **Configure SMTP** - Set up real email sending
2. **Test Email Delivery** - Verify emails reach inbox (not spam)
3. **Set Up Scheduler** - Enable automatic session cleanup
4. **Frontend UI** - Build verification pages and session management
5. **Email Templates** - Customize branding and styling

### Recommended

1. **Email Queue** - Use queues for email sending (avoid blocking)
2. **Email Notifications** - Notify on new login from unknown device
3. **Remember Me** - Extend session lifetime option
4. **Session Limits** - Limit max concurrent sessions per user
5. **Anomaly Detection** - Alert on suspicious login patterns

### Optional Enhancements

1. **Email Service Provider** - Use SendGrid, Mailgun, or AWS SES
2. **Push Notifications** - Mobile app session alerts
3. **Geolocation** - Show approximate location of sessions
4. **Session Naming** - Allow users to name devices
5. **Last Login Alert** - Email notification of last login

---

**Implementation Complete** ✅
**Production Ready:** Requires SMTP configuration
**Testing Required:** Yes (email delivery, session cleanup)
