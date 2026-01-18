#!/bin/bash
# Safe Deployment Script for Kawhe Loyalty
# This script safely pulls from git without breaking production

set -e  # Exit on error

# Configuration - UPDATE THESE FOR YOUR SERVER
APP_DIR="/var/www/kawhe"
GIT_BRANCH="main"  # or "master" depending on your setup
WEB_USER="www-data"  # or "nginx" depending on your setup

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Create backup directory
BACKUP_DIR="$HOME/backups/$(date +%Y%m%d_%H%M%S)"
mkdir -p $BACKUP_DIR

echo -e "${GREEN}🚀 Starting safe deployment...${NC}"

# 1. Navigate to app directory
if [ ! -d "$APP_DIR" ]; then
    echo -e "${RED}❌ App directory not found: $APP_DIR${NC}"
    exit 1
fi

cd $APP_DIR
echo -e "${GREEN}📁 Working in: $(pwd)${NC}"

# 2. Create backup
echo -e "${YELLOW}📦 Creating backup...${NC}"
if [ -f ".env" ]; then
    cp .env $BACKUP_DIR/.env.backup
    echo -e "${GREEN}✅ .env backed up${NC}"
else
    echo -e "${YELLOW}⚠️  .env file not found${NC}"
fi

# Backup entire app (excluding large directories)
tar -czf $BACKUP_DIR/app-backup.tar.gz \
    --exclude='vendor' \
    --exclude='node_modules' \
    --exclude='.git' \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    . 2>/dev/null || true

echo -e "${GREEN}✅ Backup created: $BACKUP_DIR${NC}"

# 3. Check git status
echo -e "${YELLOW}🔍 Checking git status...${NC}"
if ! git rev-parse --git-dir > /dev/null 2>&1; then
    echo -e "${RED}❌ Not a git repository${NC}"
    exit 1
fi

git status --short

# 4. Check for uncommitted changes
if ! git diff-index --quiet HEAD --; then
    echo -e "${YELLOW}⚠️  You have uncommitted changes${NC}"
    echo -e "${YELLOW}Files modified:${NC}"
    git diff --name-only
    
    read -p "Stash these changes? (y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        git stash save "Pre-deployment stash $(date +%Y%m%d_%H%M%S)"
        echo -e "${GREEN}✅ Changes stashed${NC}"
    else
        echo -e "${RED}❌ Deployment cancelled${NC}"
        exit 1
    fi
fi

# 5. Fetch latest
echo -e "${YELLOW}⬇️  Fetching latest from git...${NC}"
git fetch origin

# 6. Show what will change
echo -e "${YELLOW}📋 Files that will change:${NC}"
CHANGED_FILES=$(git diff HEAD origin/$GIT_BRANCH --name-only)
if [ -z "$CHANGED_FILES" ]; then
    echo -e "${GREEN}✅ No changes to pull${NC}"
else
    echo "$CHANGED_FILES"
    echo ""
    echo -e "${YELLOW}Total files: $(echo "$CHANGED_FILES" | wc -l)${NC}"
fi

# 7. Ask for confirmation
echo ""
read -p "Continue with deployment? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${RED}❌ Deployment cancelled${NC}"
    if git stash list | grep -q "Pre-deployment stash"; then
        git stash pop
    fi
    exit 1
fi

# 8. Pull changes
echo -e "${YELLOW}⬇️  Pulling changes...${NC}"
git pull origin $GIT_BRANCH

# 9. Install/update dependencies
echo -e "${YELLOW}📦 Updating dependencies...${NC}"
if [ -f "composer.json" ]; then
    composer install --no-dev --optimize-autoloader --no-interaction
    echo -e "${GREEN}✅ Dependencies updated${NC}"
else
    echo -e "${YELLOW}⚠️  composer.json not found, skipping${NC}"
fi

# 10. Run migrations
echo -e "${YELLOW}🗄️  Running migrations...${NC}"
php artisan migrate --force || echo -e "${YELLOW}⚠️  Migration failed or no migrations${NC}"

# 11. Clear all caches
echo -e "${YELLOW}🧹 Clearing cache...${NC}"
php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true
echo -e "${GREEN}✅ Cache cleared${NC}"

# 12. Cache for production
echo -e "${YELLOW}⚡ Caching for production...${NC}"
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true
echo -e "${GREEN}✅ Production cache created${NC}"

# 13. Set permissions
echo -e "${YELLOW}🔐 Setting permissions...${NC}"
if [ -d "storage" ]; then
    chown -R $WEB_USER:$WEB_USER storage bootstrap/cache 2>/dev/null || true
    chmod -R 775 storage bootstrap/cache 2>/dev/null || true
    echo -e "${GREEN}✅ Permissions set${NC}"
else
    echo -e "${YELLOW}⚠️  storage directory not found${NC}"
fi

# 14. Check for stashed changes
if git stash list | grep -q "Pre-deployment stash"; then
    echo ""
    echo -e "${YELLOW}⚠️  You have stashed changes${NC}"
    echo -e "${YELLOW}   Review with: git stash show -p${NC}"
    echo -e "${YELLOW}   To restore: git stash pop${NC}"
fi

# 15. Summary
echo ""
echo -e "${GREEN}✅ Deployment complete!${NC}"
echo -e "${GREEN}📦 Backup saved to: $BACKUP_DIR${NC}"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo "  1. Test your application"
echo "  2. Check logs: tail -f storage/logs/laravel.log"
echo "  3. If issues occur, restore from: $BACKUP_DIR"
