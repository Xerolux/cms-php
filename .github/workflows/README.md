# XQUANTORIA CI/CD Pipeline Documentation

## Overview

This repository contains a comprehensive CI/CD pipeline for the XQUANTORIA application with automated testing, quality checks, and deployment capabilities.

## Pipeline Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Push / Pull Request                      │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│                   CI Workflow (ci.yml)                      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │   Backend    │  │   Frontend   │  │   Security   │     │
│  │    Tests     │  │    Tests     │  │    Scans     │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
│         │                 │                  │              │
│         ▼                 ▼                  ▼              │
│  ┌──────────────────────────────────────────────────┐     │
│  │              Quality Gate + Coverage             │     │
│  └──────────────────────────────────────────────────┘     │
└────────────────────┬────────────────────────────────────────┘
                     │ (if passed)
                     ▼
┌─────────────────────────────────────────────────────────────┐
│            Docker Build (docker-build.yml)                  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │   Backend    │  │   Frontend   │  │    Nginx     │     │
│  │   Docker     │  │   Docker     │  │   Docker     │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
└────────────────────┬────────────────────────────────────────┘
                     │ (on develop branch)
                     ▼
┌─────────────────────────────────────────────────────────────┐
│         Deploy to Staging (deploy-staging.yml)              │
│  - Automated deployment                                     │
│  - Database migrations                                      │
│  - Health checks                                            │
│  - Smoke tests                                              │
└────────────────────┬────────────────────────────────────────┘
                     │ (on version tag)
                     ▼
┌─────────────────────────────────────────────────────────────┐
│        Deploy to Production (deploy-production.yml)         │
│  - Manual approval required                                 │
│  - Full backup                                              │
│  - Zero-downtime deployment                                 │
│  - Automatic rollback on failure                            │
└─────────────────────────────────────────────────────────────┘
```

## Workflows

### 1. CI Workflow (`.github/workflows/ci.yml`)

**Trigger:**
- Push to `main` or `develop` branches
- Pull requests to `main` or `develop`
- Daily schedule at 2 AM UTC

**Jobs:**

#### Backend Tests & Analysis
- **backend-tests**: PHPUnit tests with coverage
  - PostgreSQL & Redis services
  - Code coverage reporting to Codecov
  - Coverage threshold: 75%

- **backend-linting**: Code quality checks
  - Laravel Pint (code style)
  - PHPStan (static analysis)

- **backend-security**: Security audits
  - Composer audit
  - Enlightn security scanner

#### Frontend Tests & Analysis
- **frontend-tests**: Vitest tests with coverage
  - TypeScript type checking
  - Coverage reporting to Codecov

- **frontend-linting**: Code quality checks
  - ESLint
  - Prettier

- **frontend-security**: Security audits
  - npm audit
  - Snyk security scan

#### Additional Checks
- **performance-tests**: Lighthouse CI
  - Performance, accessibility, best practices, SEO
  - Only runs on PRs and main branch

- **security-scan**: Trivy vulnerability scanner
  - Scans entire codebase
  - Uploads results to GitHub Security

- **dependency-review**: Reviews dependency changes
  - Only runs on pull requests
  - Fails on moderate+ severity

- **build-verification**: Verifies frontend builds successfully

- **quality-gate**: Final quality check
  - Ensures all critical jobs passed

### 2. Docker Build Workflow (`.github/workflows/docker-build.yml`)

**Trigger:**
- Push to `main` or `develop` branches
- Pull requests
- Version tags (`v*.*.*`)

**Stages:**

1. **Test Stage** (Quality Gate)
   - Runs backend tests with coverage
   - Runs frontend tests with coverage
   - Enforces 80% coverage threshold
   - Must pass before building images

2. **Build Stage**
   - Builds Docker images for:
     - Backend (PHP/Laravel)
     - Frontend (React/Vite)
     - Nginx (reverse proxy)
   - Pushes to GitHub Container Registry (ghcr.io)
   - Multi-platform support (linux/amd64)

3. **Deploy Stage**
   - **Staging**: Automated on push to `develop`
   - **Production**: Automated on version tags
   - Includes health checks and smoke tests

### 3. Deploy to Staging Workflow (`.github/workflows/deploy-staging.yml`)

**Trigger:**
- Push to `develop` branch
- Manual workflow dispatch

**Features:**
- **Pre-deployment checks**: Verifies branch and changes
- **Database backup**: Automatic before deployment
- **Backend deployment**:
  - Git pull
  - Composer install
  - Database migrations
  - Cache optimization
- **Frontend deployment**:
  - npm ci
  - Production build
- **Health checks**:
  - Backend API health endpoint
  - Frontend accessibility
  - Smoke tests
- **Post-deployment**:
  - CDN cache clearing
  - Cache warmup
  - Deployment notifications

**Manual Options:**
- `skip_tests`: Skip tests (use with caution)
- `migrate_only`: Run migrations only (no deployment)

### 4. Deploy to Production Workflow (`.github/workflows/deploy-production.yml`)

**Trigger:**
- Version tags (`v*.*.*`)
- Manual workflow dispatch

**Features:**

#### Pre-Deployment
- Tag validation (semantic versioning)
- CI checks verification
- Manual approval gate (required)

#### Backup
- Full database backup
- Uploaded files backup
- Current deployment backup
- Retains last 30 backups

#### Zero-Downtime Deployment
- Creates new release directory
- Builds new release in isolation
- Symlink swap (atomic)
- Graceful service restart
- Old release cleanup (keeps last 5)

#### Health Checks & Monitoring
- Backend API health (60 attempts, 5s interval)
- Frontend accessibility
- Response time verification (< 3s)
- SSL certificate expiry check
- Comprehensive smoke tests
- E2E tests

#### Automatic Rollback
- Triggers on any health check failure
- Rolls back database migrations
- Restores previous release via symlinks
- Restarts services
- Sends alert notifications

**Manual Options:**
- `version`: Specify version tag
- `skip_backup`: Skip backup (NOT recommended)
- `canary_release`: Deploy to subset of servers

## Required Secrets

### Staging Environment
```
STAGING_HOST                  # Staging server hostname
STAGING_USER                  # SSH username
STAGING_SSH_KEY              # SSH private key
STAGING_URL                  # Staging URL (e.g., https://staging.example.com)
STAGING_DB_HOST              # Database host
STAGING_DB_USER              # Database username
STAGING_DB_PASSWORD          # Database password
STAGING_DB_NAME              # Database name
STAGING_CDN_ID               # CloudFlare zone ID (optional)
```

### Production Environment
```
PRODUCTION_HOST              # Production server hostname
PRODUCTION_USER              # SSH username
PRODUCTION_SSH_KEY          # SSH private key
PRODUCTION_URL              # Production URL (e.g., https://example.com)
PRODUCTION_DB_HOST          # Database host
PRODUCTION_DB_USER          # Database username
PRODUCTION_DB_PASSWORD      # Database password
PRODUCTION_DB_NAME          # Database name
PRODUCTION_CDN_ID           # CloudFlare zone ID (optional)
PRODUCTION_DOMAIN           # Domain name for SSL check
```

### Shared Secrets
```
SLACK_WEBHOOK               # Slack webhook for notifications
CLOUDFLARE_API_TOKEN        # CloudFlare API token (optional)
SNYK_TOKEN                 # Snyk security scanner token
CODECOV_TOKEN              # Codecov token (optional)
GITHUB_TOKEN               # GitHub token (automatic)
```

## Environment Configurations

### Staging Database
- Name: `xquantoria_staging`
- Automatic migrations on deploy
- Seed data enabled

### Production Database
- Name: `xquantoria_production`
- Manual migrations via deploy workflow
- Seed data disabled
- Automatic backups before deploy

## Coverage Requirements

| Component | Threshold | Tool |
|-----------|-----------|------|
| Backend | 80% | PHPUnit + Xdebug |
| Frontend | 80% | Vitest |
| Overall Quality Gate | 75% | Combined |

## Quality Gates

### Must Pass
- Backend tests (PHPUnit)
- Frontend tests (Vitest)
- Backend linting (PHPStan + Pint)
- Frontend linting (ESLint)
- Security scans (Trivy)

### Warnings Allowed
- Performance tests (Lighthouse)
- Snyk security scan
- Dependency review

## Deployment Strategies

### Staging
- **Strategy**: Rolling update
- **Downtime**: ~30 seconds
- **Rollback**: Manual via SSH
- **Frequency**: Every push to develop

### Production
- **Strategy**: Blue-green deployment (symlink swap)
- **Downtime**: < 1 second
- **Rollback**: Automatic on failure
- **Frequency**: Version tags only

## Monitoring & Alerts

### Health Endpoints
```
GET /api/v1/health
```

Response:
```json
{
  "status": "ok",
  "timestamp": "2024-01-21T12:00:00Z",
  "version": "1.0.0"
}
```

### Slack Notifications
- Deployment started
- Deployment succeeded
- Deployment failed
- Rollback initiated
- Health check failures

## Troubleshooting

### Deployment Failed
1. Check workflow logs in GitHub Actions
2. Verify server connectivity (SSH)
3. Check database credentials
4. Review application logs: `tail -f backend/storage/logs/laravel.log`

### Health Check Failed
1. Verify application is running: `sudo systemctl status php8.3-fpm`
2. Check nginx status: `sudo systemctl status nginx`
3. Review nginx logs: `tail -f /var/log/nginx/error.log`
4. Test database connection

### Rollback Needed
Option 1: Automatic (on health check failure)
Option 2: Manual via SSH:
```bash
cd /var/www/xquantoria-production
ls -lt releases/
# Choose previous release
ln -nfs releases/[PREVIOUS]/backend backend
ln -nfs releases/[PREVIOUS]/frontend frontend
sudo systemctl reload nginx
```

## Best Practices

### Before Deploying to Production
1. Test thoroughly in staging
2. Create database backup
3. Notify team members
4. Schedule during low-traffic hours
5. Have rollback plan ready

### During Deployment
1. Monitor health checks
2. Watch for Slack notifications
3. Be ready to intervene
4. Verify functionality post-deploy

### After Deployment
1. Run smoke tests
2. Monitor application logs
3. Check error rates
4. Verify performance metrics
5. Document any issues

## Versioning

Semantic versioning is enforced:
- Format: `v1.0.0` or `v1.0.0-beta`
- Major: Breaking changes
- Minor: New features
- Patch: Bug fixes

## Maintenance

### Regular Tasks
- Review and update dependencies monthly
- Check security scan results
- Monitor coverage trends
- Update GitHub Actions versions
- Review and optimize workflows

### Backup Management
- Staging: Keep last 7 days
- Production: Keep last 30 days
- Off-site backup recommended

## Performance Benchmarks

### Target Metrics
- Homepage load: < 2s
- API response: < 500ms
- Lighthouse score: > 90
- Uptime: > 99.9%

## Support

For issues or questions:
1. Check this documentation
2. Review workflow logs
3. Contact DevOps team
4. Create GitHub issue

## Changelog

### Version 1.0.0 (Current)
- Initial CI/CD pipeline implementation
- Automated testing and quality gates
- Docker image building
- Staging and production deployments
- Zero-downtime deployment strategy
- Automatic rollback capability
- Comprehensive health checks
- Slack notifications integration
