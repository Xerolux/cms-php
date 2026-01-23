# GitHub Secrets Configuration Guide

This document provides a complete guide for configuring all required secrets for the XQUANTORIA CI/CD pipelines.

## How to Add Secrets

1. Go to your repository on GitHub
2. Click **Settings** → **Secrets and variables** → **Actions**
3. Click **New repository secret**
4. Add each secret as listed below

---

## Required Secrets

### 🚨 Critical Secrets (Required for all deployments)

| Secret Name | Description | Example | Required |
|-------------|-------------|---------|----------|
| `SLACK_WEBHOOK` | Slack webhook URL for deployment notifications | `https://hooks.slack.com/services/T00...` | Yes |
| `GITHUB_TOKEN` | GitHub token (automatically provided) | `ghp_xxxxxxxxxxxx` | Automatic |

---

### 📦 Staging Environment Secrets

#### Server Access
| Secret Name | Description | Example | Required |
|-------------|-------------|---------|----------|
| `STAGING_HOST` | Staging server hostname or IP | `staging.example.com` or `192.168.1.100` | Yes |
| `STAGING_USER` | SSH username for staging server | `deploy` or `ubuntu` | Yes |
| `STAGING_SSH_KEY` | Private SSH key for staging server | `-----BEGIN RSA PRIVATE KEY-----\n...` | Yes |
| `STAGING_URL` | Full URL of staging environment | `https://staging.xquantoria.com` | Yes |

#### Database Configuration
| Secret Name | Description | Example | Required |
|-------------|-------------|---------|----------|
| `STAGING_DB_HOST` | Database host (can be localhost) | `localhost` or `db.staging.example.com` | Yes |
| `STAGING_DB_PORT` | Database port | `5432` | Optional (default: 5432) |
| `STAGING_DB_USER` | Database username | `xquantoria_staging` | Yes |
| `STAGING_DB_PASSWORD` | Database password | `secure_password_here` | Yes |
| `STAGING_DB_NAME` | Database name | `xquantoria_staging` | Yes |

#### CDN Configuration (Optional)
| Secret Name | Description | Example | Required |
|-------------|-------------|---------|----------|
| `STAGING_CDN_ID` | CloudFlare Zone ID for CDN cache purging | `abc123def456` | No |

---

### 🏭 Production Environment Secrets

#### Server Access
| Secret Name | Description | Example | Required |
|-------------|-------------|---------|----------|
| `PRODUCTION_HOST` | Production server hostname or IP | `xquantoria.com` or `10.0.0.50` | Yes |
| `PRODUCTION_USER` | SSH username for production server | `deploy` or `ubuntu` | Yes |
| `PRODUCTION_SSH_KEY` | Private SSH key for production server | `-----BEGIN RSA PRIVATE KEY-----\n...` | Yes |
| `PRODUCTION_URL` | Full URL of production environment | `https://xquantoria.com` | Yes |
| `PRODUCTION_DOMAIN` | Domain name for SSL verification | `xquantoria.com` | Yes |

#### Database Configuration
| Secret Name | Description | Example | Required |
|-------------|-------------|---------|----------|
| `PRODUCTION_DB_HOST` | Database host | `db.production.example.com` | Yes |
| `PRODUCTION_DB_PORT` | Database port | `5432` | Optional (default: 5432) |
| `PRODUCTION_DB_USER` | Database username | `xquantoria_prod` | Yes |
| `PRODUCTION_DB_PASSWORD` | Database password | `very_secure_password_here` | Yes |
| `PRODUCTION_DB_NAME` | Database name | `xquantoria_production` | Yes |

#### CDN Configuration (Optional)
| Secret Name | Description | Example | Required |
|-------------|-------------|---------|----------|
| `PRODUCTION_CDN_ID` | CloudFlare Zone ID for CDN cache purging | `xyz789abc123` | No |

---

### 🔒 Security & Scanning Secrets

| Secret Name | Description | Example | Required |
|-------------|-------------|---------|----------|
| `SNYK_TOKEN` | Snyk API token for dependency scanning | `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx` | Recommended |
| `CODECOV_TOKEN` | Codecov token for coverage reporting | `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx` | Optional |

---

### ☁️ Cloud Services Secrets (Optional)

| Secret Name | Description | Example | Required |
|-------------|-------------|---------|----------|
| `CLOUDFLARE_API_TOKEN` | CloudFlare API token for CDN management | `xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx` | If using CDN |
| `AWS_ACCESS_KEY_ID` | AWS access key for S3 storage | `AKIAIOSFODNN7EXAMPLE` | If using AWS S3 |
| `AWS_SECRET_ACCESS_KEY` | AWS secret access key | `wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY` | If using AWS S3 |
| `AWS_REGION` | AWS region | `us-east-1` | If using AWS S3 |

---

## Secret Setup Instructions

### 1. SSH Key Generation

If you need to create SSH keys for server access:

```bash
# Generate new SSH key pair
ssh-keygen -t rsa -b 4096 -C "github-actions@xquantoria" -f github-actions-deploy

# Copy public key to staging server
ssh-copy-path -i github-actions-deploy.pub user@staging.example.com

# Copy public key to production server
ssh-copy-path -i github-actions-deploy.pub user@production.example.com

# Add private key to GitHub Secrets
# Copy contents of github-actions-deploy (without .pub)
```

**Important:**
- Never commit private keys to the repository
- Use read-only SSH keys when possible
- Rotate keys regularly (recommended: every 90 days)

### 2. Database User Setup

Create dedicated database users for deployments:

```sql
-- Staging user
CREATE USER xquantoria_staging WITH PASSWORD 'secure_password';
GRANT ALL PRIVILEGES ON DATABASE xquantoria_staging TO xquantoria_staging;

-- Production user
CREATE USER xquantoria_prod WITH PASSWORD 'very_secure_password';
GRANT ALL PRIVILEGES ON DATABASE xquantoria_production TO xquantoria_prod;
```

### 3. Slack Webhook Setup

1. Go to your Slack workspace
2. Navigate to **Apps** → **Incoming Webhooks**
3. Click **Add to Slack**
4. Select channel for notifications
5. Copy webhook URL to `SLACK_WEBHOOK` secret

### 4. CloudFlare API Token

1. Log in to CloudFlare dashboard
2. Go to **My Profile** → **API Tokens**
3. Click **Create Token**
4. Use template: **Edit zone DNS**
5. Set zone permissions to your domain
6. Copy token to `CLOUDFLARE_API_TOKEN` secret
7. Copy Zone ID to `*_CDN_ID` secrets

### 5. Snyk Token

1. Sign up at https://snyk.io
2. Go to **General** → **API Token**
3. Copy token to `SNYK_TOKEN` secret

### 6. Codecov Token

1. Sign up at https://codecov.io
2. Add your repository
3. Copy upload token to `CODECOV_TOKEN` secret

---

## Environment-Specific Database Configurations

### Staging Database (.env)
```bash
DB_CONNECTION=pgsql
DB_HOST=${{ secrets.STAGING_DB_HOST }}
DB_PORT=5432
DB_DATABASE=${{ secrets.STAGING_DB_NAME }}
DB_USERNAME=${{ secrets.STAGING_DB_USER }}
DB_PASSWORD=${{ secrets.STAGING_DB_PASSWORD }}
```

### Production Database (.env)
```bash
DB_CONNECTION=pgsql
DB_HOST=${{ secrets.PRODUCTION_DB_HOST }}
DB_PORT=5432
DB_DATABASE=${{ secrets.PRODUCTION_DB_NAME }}
DB_USERNAME=${{ secrets.PRODUCTION_DB_USER }}
DB_PASSWORD=${{ secrets.PRODUCTION_DB_PASSWORD }}
```

---

## Security Best Practices

### ✅ DO:
- Use strong, unique passwords (minimum 32 characters)
- Rotate secrets regularly
- Use different credentials for staging/production
- Limit database user permissions to required tables
- Use environment-specific secrets
- Enable GitHub secret scanning
- Monitor secret usage
- Document secret rotation schedule

### ❌ DON'T:
- Commit secrets to repository
- Share secrets via email or chat
- Use same credentials across environments
- Use default passwords
- Grant unnecessary permissions
- Forget to revoke access for former team members
- Use weak encryption keys

---

## Secret Rotation Schedule

| Secret | Rotation Frequency | Notes |
|--------|-------------------|-------|
| Database passwords | Every 90 days | Requires application restart |
| SSH keys | Every 90 days | Update server authorized_keys |
| API tokens | Every 180 days | Follow provider guidelines |
| Webhook URLs | When compromised | Regenerate immediately |
| Cloud credentials | Per AWS best practices | Use IAM roles when possible |

---

## Troubleshooting

### Common Issues

#### SSH Connection Failed
```
Error: Handshake failed
```
**Solution:**
- Verify `STAGING_SSH_KEY` or `PRODUCTION_SSH_KEY` is correctly formatted
- Ensure public key is added to server's `authorized_keys`
- Check server firewall allows SSH from GitHub Actions IPs

#### Database Connection Failed
```
Error: Connection refused
```
**Solution:**
- Verify database host is accessible from deployment server
- Check database is running: `sudo systemctl status postgresql`
- Verify credentials are correct
- Check database firewall rules

#### Health Check Failed
```
Error: curl: (7) Failed to connect
```
**Solution:**
- Verify `STAGING_URL` or `PRODUCTION_URL` is correct
- Check nginx is running: `sudo systemctl status nginx`
- Verify DNS is resolving correctly
- Check SSL certificate is valid

#### Permission Denied
```
Error: Permission denied (publickey)
```
**Solution:**
- Verify SSH key has correct permissions on server
- Ensure SSH key is in base64 format in secret
- Check GitHub Actions has permission to access secrets

---

## Testing Secrets Locally

To test secrets before committing:

```bash
# Load secrets from file (for testing only)
source .env.local

# Test SSH connection
ssh -i $STAGING_SSH_KEY $STAGING_USER@$STAGING_HOST

# Test database connection
psql -h $STAGING_DB_HOST -U $STAGING_DB_USER -d $STAGING_DB_NAME

# Test URL accessibility
curl -I $STAGING_URL/api/v1/health
```

---

## Emergency Procedures

### If Secrets Are Compromised

1. **Immediate Actions:**
   - Rotate all compromised secrets immediately
   - Review GitHub Actions logs for unauthorized access
   - Enable additional security monitoring

2. **Database Compromise:**
   - Change all database passwords
   - Review database logs for suspicious activity
   - Consider restoring from backup if data was modified

3. **Server Compromise:**
   - Rotate SSH keys
   - Review server access logs
   - Scan for malware or unauthorized changes

4. **After Recovery:**
   - Document the incident
   - Update security procedures
   - Train team on prevention

---

## Support & Resources

### Documentation
- GitHub Actions Secrets: https://docs.github.com/en/actions/security-guides/encrypted-secrets
- SSH Key Management: https://www.ssh.com/academy/ssh/key
- PostgreSQL Security: https://www.postgresql.org/docs/current/security.html

### Tools
- Secret scanning: GitHub secret scanning (automatic)
- Password manager: 1Password, LastPass, Bitwarden
- SSH key management: ssh-agent, keychain

---

## Checklist

Use this checklist when setting up secrets:

- [ ] All staging server secrets configured
- [ ] All production server secrets configured
- [ ] Database credentials tested
- [ ] SSH access verified
- [ ] Slack webhook tested
- [ ] Health endpoints accessible
- [ ] SSL certificates valid
- [ ] Backup procedures tested
- [ ] Rollback procedures tested
- [ ] Team members trained
- [ ] Secret rotation schedule documented
- [ ] Emergency procedures documented

---

## Contact

For questions or issues with secrets configuration:
- DevOps Team: devops@xquantoria.com
- Security Team: security@xquantoria.com
- Create GitHub Issue: [Security Issues]

Last updated: 2024-01-21
