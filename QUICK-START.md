# CI/CD Quick Start Guide

## Quick Setup (5 Steps)

### 1. Configure GitHub Secrets

Go to: **Repository Settings** → **Secrets and variables** → **Actions**

Add these required secrets:

```bash
# Staging
STAGING_HOST=staging.example.com
STAGING_USER=deploy
STAGING_SSH_KEY=[paste SSH private key]
STAGING_URL=https://staging.example.com
STAGING_DB_HOST=localhost
STAGING_DB_USER=xquantoria_staging
STAGING_DB_PASSWORD=your_password
STAGING_DB_NAME=xquantoria_staging

# Production
PRODUCTION_HOST=example.com
PRODUCTION_USER=deploy
PRODUCTION_SSH_KEY=[paste SSH private key]
PRODUCTION_URL=https://example.com
PRODUCTION_DB_HOST=localhost
PRODUCTION_DB_USER=xquantoria_prod
PRODUCTION_DB_PASSWORD=your_password
PRODUCTION_DB_NAME=xquantoria_production

# Shared
SLACK_WEBHOOK=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
```

### 2. Generate SSH Keys

```bash
# Generate SSH key pair
ssh-keygen -t rsa -b 4096 -C "github-actions" -f github-actions-deploy

# Copy public key to servers
ssh-copy-id -i github-actions-deploy.pub user@staging.example.com
ssh-copy-id -i github-actions-deploy.pub user@production.example.com

# Add private key to GitHub Secrets
cat github-actions-deploy
# Copy entire output (including BEGIN/END markers) to STAGING_SSH_KEY and PRODUCTION_SSH_KEY
```

### 3. Prepare Servers

```bash
# On both staging and production servers

# Create deployment directories
sudo mkdir -p /var/www/xquantoria-staging
sudo mkdir -p /var/www/xquantoria-production
sudo mkdir -p /var/backups/xquantoria-staging
sudo mkdir -p /var/backups/xquantoria-production

# Set permissions
sudo chown -R $USER:$USER /var/www/xquantoria-*
sudo chown -R $USER:$USER /var/backups/xquantoria-*

# Clone repository
cd /var/www/xquantoria-staging
git clone https://github.com/YOUR_USERNAME/xquantoria.git .

cd /var/www/xquantoria-production
git clone https://github.com/YOUR_USERNAME/xquantoria.git .
```

### 4. Configure Environments

**Staging** (`/var/www/xquantoria-staging/backend/.env`):
```bash
APP_ENV=staging
APP_DEBUG=true
APP_URL=https://staging.example.com

DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=xquantoria_staging
DB_USERNAME=xquantoria_staging
DB_PASSWORD=your_password
```

**Production** (`/var/www/xquantoria-production/backend/.env`):
```bash
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com

DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=xquantoria_production
DB_USERNAME=xquantoria_prod
DB_PASSWORD=your_password
```

### 5. Make Scripts Executable

```bash
chmod +x scripts/backup.sh
chmod +x scripts/rollback.sh
chmod +x scripts/health-check.sh
```

## How to Deploy

### Deploy to Staging (Automatic)

```bash
# Just push to develop branch
git checkout develop
git merge your-feature
git push origin develop

# Deployment happens automatically!
# Check: https://github.com/YOUR_REPO/actions
```

### Deploy to Production (Manual)

```bash
# 1. Merge to main
git checkout main
git merge develop
git push origin main

# 2. Create version tag
git tag -a v1.0.0 -m "Release v1.0.0"
git push origin v1.0.0

# 3. Approve deployment in GitHub Actions
# Go to: https://github.com/YOUR_REPO/actions
# Find "Deploy to Production" workflow
# Click "Review deployments" → "Approve"
```

## Common Commands

### Manual Health Check

```bash
# On server
cd /var/www/xquantoria-production/../scripts
./health-check.sh production

# Or from GitHub Actions
gh workflow run deploy-staging.yml
```

### Manual Backup

```bash
# On server
./scripts/backup.sh production
```

### Manual Rollback

```bash
# On server
./scripts/rollback.sh production

# Or specify release
./scripts/rollback.sh production releases/20240120_120000
```

## What Happens During Deployment

### Staging Deployment
1. ✅ Pre-deployment checks
2. 💾 Database backup
3. 🚀 Deploy backend (git pull + composer install + migrations)
4. 🎨 Deploy frontend (npm ci + build)
5. 🧪 Health checks (API + frontend)
6. 🧹 Clear CDN cache
7. 🔔 Slack notification

### Production Deployment
1. ✅ Tag validation
2. 👤 Manual approval required
3. 💾 Full backup (database + files + code)
4. 📦 Create new release directory
5. 🔨 Build new release
6. 🔄 Symlink swap (zero-downtime)
7. 🧪 Extensive health checks (60 attempts)
8. 📱 E2E tests
9. 🧹 Clear CDN cache
10. 🔔 Slack notification
11. 🚨 Auto-rollback if any check fails

## Monitoring

### Check Deployment Status

```bash
# Using GitHub CLI
gh run list --workflow=deploy-staging.yml
gh run list --workflow=deploy-production.yml

# View specific run
gh run view [run-id]
```

### Health Endpoints

```bash
# Backend health
curl https://staging.example.com/api/v1/health
curl https://example.com/api/v1/health

# Response:
# {"status":"ok","timestamp":"2024-01-21T12:00:00Z","version":"1.0.0"}
```

## Troubleshooting

### Deployment Failed

```bash
# Check logs
gh run view --log-failed

# Check server logs
ssh user@staging.example.com
tail -f /var/www/xquantoria-staging/backend/storage/logs/laravel.log
```

### Health Check Failed

```bash
# Check services
sudo systemctl status nginx
sudo systemctl status php8.3-fpm
sudo systemctl status postgresql

# Restart services
sudo systemctl restart nginx
sudo systemctl restart php8.3-fpm
```

### Manual Rollback

```bash
# List available releases
ls -la /var/www/xquantoria-production/releases/

# Rollback
cd /var/www/xquantoria-production/../scripts
./rollback.sh production releases/[PREVIOUS_RELEASE]
```

## Coverage Requirements

- **Backend**: 80% (PHPUnit)
- **Frontend**: 80% (Vitest)
- Docker build requires coverage ≥ 80%

## Quality Gates

All must pass before deployment:
- ✅ Backend tests (PHPUnit)
- ✅ Frontend tests (Vitest)
- ✅ Backend linting (PHPStan + Pint)
- ✅ Frontend linting (ESLint + Prettier)
- ✅ Security scans (Trivy)

## Support

- **Documentation**: `.github/workflows/README.md`
- **Secrets Setup**: `.github/workflows/SECRETS.md`
- **Full Guide**: `docs/DEPLOYMENT-GUIDE.md`
- **Issues**: Create GitHub issue

## Tips

✅ **Always test in staging first**

✅ **Create backups before production changes**

✅ **Monitor deployments in real-time**

✅ **Keep Slack notifications enabled**

✅ **Review security scan results**

❌ **Don't skip tests in production**

❌ **Don't use `skip_backup` option**

❌ **Don't deploy on Friday afternoon**

---

**Ready to deploy?** Start with step 1! 🚀
