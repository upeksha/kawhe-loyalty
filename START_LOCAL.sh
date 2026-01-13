#!/bin/bash

# Kawhe Loyalty - Local Development Startup Script
# This script starts all required services for local development

echo "🚀 Starting Kawhe Loyalty App Locally..."
echo ""

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if .env exists
if [ ! -f .env ]; then
    echo -e "${YELLOW}⚠️  .env file not found. Creating from .env.example...${NC}"
    cp .env.example .env
    php artisan key:generate
    echo -e "${GREEN}✅ .env file created${NC}"
fi

# Clear caches
echo "🧹 Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
echo -e "${GREEN}✅ Caches cleared${NC}"

# Check if assets are built
if [ ! -d "public/build/assets" ] || [ -z "$(ls -A public/build/assets 2>/dev/null)" ]; then
    echo "📦 Building frontend assets..."
    npm run build
    echo -e "${GREEN}✅ Assets built${NC}"
else
    echo -e "${GREEN}✅ Assets already built${NC}"
fi

# Check database
echo "🗄️  Checking database..."
php artisan migrate:status > /dev/null 2>&1
if [ $? -ne 0 ]; then
    echo "⚠️  Database might need migrations. Run: php artisan migrate"
fi

echo ""
echo -e "${GREEN}✅ Setup complete!${NC}"
echo ""
echo "📋 To run the app, open 4 terminal windows and run:"
echo ""
echo "  Terminal 1 - Laravel Server:"
echo "    php artisan serve --port=8000"
echo ""
echo "  Terminal 2 - Reverb (WebSocket):"
echo "    php artisan reverb:start"
echo ""
echo "  Terminal 3 - Queue Worker (for emails):"
echo "    php artisan queue:work"
echo ""
echo "  Terminal 4 - Frontend Dev (optional, for hot-reload):"
echo "    npm run dev"
echo ""
echo "🌐 Then visit: http://localhost:8000"
echo ""
