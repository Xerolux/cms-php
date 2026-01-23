#!/bin/bash

###############################################################################
# XQUANTORIA Health Check Script
# Usage: ./health-check.sh [staging|production]
###############################################################################

set -e

# Configuration
ENVIRONMENT=${1:-production}
FAILED=0

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
    FAILED=1
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Set URL based on environment
if [ "$ENVIRONMENT" = "staging" ]; then
    BASE_URL="https://staging.example.com"
    DEPLOY_PATH="/var/www/xquantoria-staging"
elif [ "$ENVIRONMENT" = "production" ]; then
    BASE_URL="https://example.com"
    DEPLOY_PATH="/var/www/xquantoria-production"
else
    echo "Usage: $0 [staging|production]"
    exit 1
fi

echo "============================================"
echo "XQUANTORIA Health Check - $ENVIRONMENT"
echo "============================================"
echo "Timestamp: $(date)"
echo "Base URL: $BASE_URL"
echo ""

# Test 1: Backend API Health
log "Testing Backend API Health..."
if curl -f -s "${BASE_URL}/api/v1/health" > /dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} Backend API is healthy"
else
    error "Backend API health check failed"
fi

# Test 2: Frontend
log "Testing Frontend..."
if curl -f -s "${BASE_URL}" > /dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} Frontend is accessible"
else
    error "Frontend health check failed"
fi

# Test 3: Static Assets
log "Testing Static Assets..."
if curl -f -s "${BASE_URL}/favicon.ico" > /dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} Static assets are accessible"
else
    error "Static assets check failed"
fi

# Test 4: Database Connection
log "Testing Database Connection..."
cd "$DEPLOY_PATH/backend"
source .env 2>/dev/null || true

if PGPASSWORD=$DB_PASSWORD psql \
    -h $DB_HOST \
    -U $DB_USERNAME \
    -d $DB_DATABASE \
    -c "SELECT 1" > /dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} Database connection successful"
else
    error "Database connection failed"
fi

# Test 5: Redis Connection
log "Testing Redis Connection..."
if command -v redis-cli &> /dev/null; then
    if redis-cli -h $REDIS_HOST -p $REDIS_PASSWORD ping > /dev/null 2>&1; then
        echo -e "${GREEN}✓${NC} Redis connection successful"
    else
        warning "Redis connection test failed"
    fi
else
    warning "redis-cli not available - skipping Redis test"
fi

# Test 6: Storage Writability
log "Testing Storage Writability..."
TEST_FILE="$DEPLOY_PATH/backend/storage/framework/cache/test_$(date +%s).tmp"
if touch "$TEST_FILE" 2>/dev/null; then
    rm -f "$TEST_FILE"
    echo -e "${GREEN}✓${NC} Storage is writable"
else
    error "Storage is not writable"
fi

# Test 7: Service Status
log "Testing Service Status..."
if systemctl is-active --quiet nginx; then
    echo -e "${GREEN}✓${NC} Nginx is running"
else
    error "Nginx is not running"
fi

if systemctl is-active --quiet php8.3-fpm; then
    echo -e "${GREEN}✓${NC} PHP-FPM is running"
else
    error "PHP-FPM is not running"
fi

# Test 8: Disk Space
log "Testing Disk Space..."
DISK_USAGE=$(df -h "$DEPLOY_PATH" | awk 'NR==2 {print $5}' | sed 's/%//')
if [ "$DISK_USAGE" -lt 90 ]; then
    echo -e "${GREEN}✓${NC} Disk usage: ${DISK_USAGE}%"
else
    error "Disk usage critical: ${DISK_USAGE}%"
fi

# Test 9: SSL Certificate
log "Testing SSL Certificate..."
if command -v openssl &> /dev/null; then
    EXPIRY=$(echo | openssl s_client -servername "${BASE_URL#https://}" -connect "${BASE_URL#https://}:443" 2>/dev/null | openssl x509 -noout -enddate | cut -d= -f2)
    EXPIRY_DATE=$(date -d "$EXPIRY" +%s)
    NOW=$(date +%s)
    DAYS_LEFT=$(( ($EXPIRY_DATE - $NOW) / 86400 ))

    if [ $DAYS_LEFT -gt 30 ]; then
        echo -e "${GREEN}✓${NC} SSL certificate expires in $DAYS_LEFT days"
    elif [ $DAYS_LEFT -gt 0 ]; then
        warning "SSL certificate expires in $DAYS_LEFT days (less than 30 days)"
    else
        error "SSL certificate has expired!"
    fi
else
    warning "openssl not available - skipping SSL check"
fi

# Test 10: Response Time
log "Testing Response Time..."
if command -v curl &> /dev/null; then
    RESPONSE_TIME=$(curl -o /dev/null -s -w '%{time_total}' "${BASE_URL}")
    RESPONSE_TIME_MS=$(echo "$RESPONSE_TIME * 1000" | bc)
    echo -e "${GREEN}✓${NC} Response time: ${RESPONSE_TIME_MS}ms"

    if (( $(echo "$RESPONSE_TIME > 3.0" | bc -l) )); then
        warning "Response time exceeds 3 seconds"
    fi
fi

# Summary
echo ""
echo "============================================"
if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}All Health Checks Passed${NC}"
    echo "============================================"
    exit 0
else
    echo -e "${RED}Some Health Checks Failed${NC}"
    echo "============================================"
    exit 1
fi
