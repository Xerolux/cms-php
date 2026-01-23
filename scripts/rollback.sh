#!/bin/bash

###############################################################################
# XQUANTORIA Rollback Script
# Usage: ./rollback.sh [staging|production] [release_directory]
###############################################################################

set -e

# Configuration
ENVIRONMENT=${1:-production}
RELEASE_DIR=${2}

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
    exit 1
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

confirm() {
    read -p "$(echo -e ${YELLOW}Confirm rollback to $ENVIRONMENT? (yes/no): ${NC})" response
    if [ "$response" != "yes" ]; then
        log "Rollback cancelled"
        exit 0
    fi
}

# Validate environment
if [ "$ENVIRONMENT" != "staging" ] && [ "$ENVIRONMENT" != "production" ]; then
    error "Invalid environment. Use 'staging' or 'production'"
fi

if [ "$ENVIRONMENT" = "staging" ]; then
    DEPLOY_PATH="/var/www/xquantoria-staging"
else
    DEPLOY_PATH="/var/www/xquantoria-production"
fi

log "Starting rollback to $ENVIRONMENT..."

# Find release if not specified
if [ -z "$RELEASE_DIR" ]; then
    log "No release specified. Finding previous release..."
    RELEASES_DIR="$DEPLOY_PATH/releases"

    if [ ! -d "$RELEASES_DIR" ]; then
        error "No releases directory found at $RELEASES_DIR"
    fi

    # Get second most recent release (most recent is likely the failed one)
    RELEASE_DIR=$(ls -t "$RELEASES_DIR" | head -2 | tail -1)

    if [ -z "$RELEASE_DIR" ]; then
        error "No previous release found for rollback"
    fi

    RELEASE_PATH="$RELEASES_DIR/$RELEASE_DIR"
else
    RELEASE_PATH="$RELEASE_DIR"
fi

# Verify release exists
if [ ! -d "$RELEASE_PATH" ]; then
    error "Release directory not found: $RELEASE_PATH"
fi

log "Rolling back to release: $RELEASE_PATH"

# Confirm rollback
confirm

# Create pre-rollback backup
log "Creating pre-rollback backup..."
BACKUP_TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/xquantoria-${ENVIRONMENT}/pre-rollback-${BACKUP_TIMESTAMP}"
mkdir -p "$BACKUP_DIR"

# Backup database
source "$DEPLOY_PATH/backend/.env" || error "Failed to load .env file"
PGPASSWORD=$DB_PASSWORD pg_dump \
    -h $DB_HOST \
    -U $DB_USERNAME \
    -d $DB_DATABASE \
    -F c \
    -f "$BACKUP_DIR/database.dump" || warning "Database backup failed"

log "Pre-rollback backup created: $BACKUP_DIR"

# Rollback database migrations
log "Rolling back database migrations..."
cd "$DEPLOY_PATH/backend"

# Rollback one migration step
php artisan migrate:rollback --step=1 --force || warning "Database rollback failed or nothing to rollback"

# Switch symlinks to previous release
log "Switching to previous release..."

# Create new symlinks
ln -nfs "$RELEASE_PATH/backend" "$DEPLOY_PATH/backend-new" || error "Failed to create backend symlink"
ln -nfs "$RELEASE_PATH/frontend" "$DEPLOY_PATH/frontend-new" || error "Failed to create frontend symlink"

# Atomic swap
mv -T "$DEPLOY_PATH/backend-new" "$DEPLOY_PATH/backend" || error "Failed to swap backend symlink"
mv -T "$DEPLOY_PATH/frontend-new" "$DEPLOY_PATH/frontend" || error "Failed to swap frontend symlink"

log "Symlinks updated"

# Restart services
log "Restarting services..."
sudo systemctl reload nginx || error "Failed to reload nginx"
sudo systemctl restart php8.3-fpm || error "Failed to restart php-fpm"

# Restart horizon if installed
if [ -f "$RELEASE_PATH/backend/bin/horizon" ]; then
    cd "$RELEASE_PATH/backend"
    php artisan horizon:terminate
    log "Horizon restarted"
fi

log "Services restarted"

# Verify rollback
log "Verifying rollback..."
sleep 10

# Health check
if command -v curl &> /dev/null; then
    if [ "$ENVIRONMENT" = "staging" ]; then
        HEALTH_URL="https://staging.example.com/api/v1/health"
    else
        HEALTH_URL="https://example.com/api/v1/health"
    fi

    if curl -f -s "$HEALTH_URL" > /dev/null; then
        log "Health check passed"
    else
        warning "Health check failed - manual intervention required"
    fi
fi

# Display summary
echo ""
echo "============================================"
echo "Rollback Summary"
echo "============================================"
echo "Environment: $ENVIRONMENT"
echo "Release: $RELEASE_PATH"
echo "Timestamp: $(date)"
echo ""
echo "Current Symlinks:"
ls -la "$DEPLOY_PATH/backend" | grep "->"
ls -la "$DEPLOY_PATH/frontend" | grep "->"
echo ""
echo "Backup Location: $BACKUP_DIR"
echo "============================================"

log "Rollback completed successfully!"

# Send notification
if [ -n "$SLACK_WEBHOOK" ]; then
    curl -X POST "$SLACK_WEBHOOK" \
        -H 'Content-Type: application/json' \
        -d "{
            \"text\": \"Rollback Executed - $ENVIRONMENT\",
            \"attachments\": [{
                \"color\": \"warning\",
                \"fields\": [
                    {\"title\": \"Environment\", \"value\": \"$ENVIRONMENT\", \"short\": true},
                    {\"title\": \"Release\", \"value\": \"$RELEASE_PATH\", \"short\": true},
                    {\"title\": \"Timestamp\", \"value\": \"$(date)\", \"short\": true},
                    {\"title\": \"Backup\", \"value\": \"$BACKUP_DIR\", \"short\": false}
                ]
            }]
        }" 2>/dev/null || true
fi
