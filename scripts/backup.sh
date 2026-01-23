#!/bin/bash

###############################################################################
# XQUANTORIA Database and Files Backup Script
# Usage: ./backup.sh [staging|production]
###############################################################################

set -e

# Configuration
ENVIRONMENT=${1:-production}
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/xquantoria-${ENVIRONMENT}"
CURRENT_BACKUP="${BACKUP_DIR}/${TIMESTAMP}"

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

# Create backup directory
mkdir -p "$CURRENT_BACKUP"
log "Created backup directory: $CURRENT_BACKUP"

# Load environment variables
if [ "$ENVIRONMENT" = "staging" ]; then
    source /var/www/xquantoria-staging/backend/.env || error "Failed to load .env file"
    DEPLOY_PATH="/var/www/xquantoria-staging"
elif [ "$ENVIRONMENT" = "production" ]; then
    source /var/www/xquantoria-production/backend/.env || error "Failed to load .env file"
    DEPLOY_PATH="/var/www/xquantoria-production"
else
    error "Invalid environment. Use 'staging' or 'production'"
fi

# Backup PostgreSQL database
log "Backing up PostgreSQL database..."
PGPASSWORD=$DB_PASSWORD pg_dump \
    -h $DB_HOST \
    -U $DB_USERNAME \
    -d $DB_DATABASE \
    -F c \
    -f "$CURRENT_BACKUP/database.dump" || error "Database backup failed"

log "Database backup completed: database.dump"

# Backup uploaded files
log "Backing up uploaded files..."
tar -czf "$CURRENT_BACKUP/uploads.tar.gz" \
    "$DEPLOY_PATH/backend/storage/app/uploads" \
    "$DEPLOY_PATH/public/uploads" 2>/dev/null || warning "No uploads to backup"

log "Files backup completed: uploads.tar.gz"

# Backup current deployment (code)
log "Backing up deployment..."
tar -czf "$CURRENT_BACKUP/deployment.tar.gz" \
    -C "$DEPLOY_PATH" \
    backend \
    frontend \
    --exclude=node_modules \
    --exclude=vendor \
    --exclude=.git \
    --exclude=storage/logs \
    --exclude=storage/framework/cache \
    --exclude=storage/framework/sessions \
    --exclude=storage/framework/views || error "Deployment backup failed"

log "Deployment backup completed: deployment.tar.gz"

# Generate backup manifest
cat > "$CURRENT_BACKUP/manifest.txt" << EOF
Backup Information
==================
Environment: $ENVIRONMENT
Timestamp: $TIMESTAMP
Date: $(date)
Hostname: $(hostname)
Git Commit: $(cd $DEPLOY_PATH && git rev-parse HEAD 2>/dev/null || echo "N/A")
Git Branch: $(cd $DEPLOY_PATH && git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "N/A")

Files Included
==============
- database.dump: PostgreSQL database backup
- uploads.tar.gz: User uploaded files
- deployment.tar.gz: Application code
- manifest.txt: This file

File Sizes
==========
$(du -sh "$CURRENT_BACKUP"/*)

Disk Usage
==========
$(df -h /var/backups)
EOF

log "Backup manifest created"

# Calculate checksums
log "Calculating checksums..."
cd "$CURRENT_BACKUP"
sha256sum * > checksums.txt
log "Checksums calculated"

# Cleanup old backups (keep last 30 days)
log "Cleaning up old backups..."
find "$BACKUP_DIR" -type d -mtime +30 -exec rm -rf {} \;
log "Old backups removed"

# Display summary
echo ""
echo "============================================"
echo "Backup Summary"
echo "============================================"
echo "Environment: $ENVIRONMENT"
echo "Timestamp: $TIMESTAMP"
echo "Location: $CURRENT_BACKUP"
echo ""
echo "Backup Contents:"
ls -lh "$CURRENT_BACKUP"
echo ""
echo "Total Size:"
du -sh "$CURRENT_BACKUP"
echo "============================================"

log "Backup completed successfully!"

# Send notification (if webhook is configured)
if [ -n "$SLACK_WEBHOOK" ]; then
    curl -X POST "$SLACK_WEBHOOK" \
        -H 'Content-Type: application/json' \
        -d "{
            \"text\": \"Backup Completed - $ENVIRONMENT\",
            \"attachments\": [{
                \"color\": \"good\",
                \"fields\": [
                    {\"title\": \"Environment\", \"value\": \"$ENVIRONMENT\", \"short\": true},
                    {\"title\": \"Timestamp\", \"value\": \"$TIMESTAMP\", \"short\": true},
                    {\"title\": \"Location\", \"value\": \"$CURRENT_BACKUP\", \"short\": false},
                    {\"title\": \"Size\", \"value\": \"$(du -sh $CURRENT_BACKUP | cut -f1)\", \"short\": true}
                ]
            }]
        }" 2>/dev/null || true
fi
