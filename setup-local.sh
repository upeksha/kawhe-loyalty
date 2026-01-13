#!/bin/bash

# Setup script for local development

echo "🔧 Setting up Kawhe Loyalty for local development..."
echo ""

# Create .env if it doesn't exist
if [ ! -f .env ]; then
    echo "📝 Creating .env file from .env.example..."
    if [ -f .env.example ]; then
        cp .env.example .env
        php artisan key:generate
        echo "✅ .env file created"
    else
        echo "❌ .env.example not found!"
        exit 1
    fi
fi

# Update .env for local development
echo "⚙️  Configuring for local development..."

# Set APP_ENV to local
sed -i.bak 's/^APP_ENV=.*/APP_ENV=local/' .env 2>/dev/null || \
php -r "file_put_contents('.env', preg_replace('/^APP_ENV=.*/m', 'APP_ENV=local', file_get_contents('.env')));"

# Set APP_DEBUG to true
sed -i.bak 's/^APP_DEBUG=.*/APP_DEBUG=true/' .env 2>/dev/null || \
php -r "file_put_contents('.env', preg_replace('/^APP_DEBUG=.*/m', 'APP_DEBUG=true', file_get_contents('.env')));"

# Set APP_URL to localhost
sed -i.bak 's|^APP_URL=.*|APP_URL=http://localhost:8000|' .env 2>/dev/null || \
php -r "file_put_contents('.env', preg_replace('/^APP_URL=.*/m', 'APP_URL=http://localhost:8000', file_get_contents('.env')));"

# Set MAIL_MAILER to log for local
sed -i.bak 's/^MAIL_MAILER=.*/MAIL_MAILER=log/' .env 2>/dev/null || \
php -r "file_put_contents('.env', preg_replace('/^MAIL_MAILER=.*/m', 'MAIL_MAILER=log', file_get_contents('.env')));"

# Set QUEUE_CONNECTION to database
sed -i.bak 's/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=database/' .env 2>/dev/null || \
php -r "file_put_contents('.env', preg_replace('/^QUEUE_CONNECTION=.*/m', 'QUEUE_CONNECTION=database', file_get_contents('.env')));"

echo "✅ Configuration updated"

# Clear caches
echo "🧹 Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
echo "✅ Caches cleared"

# Build assets
if [ ! -d "public/build/assets" ] || [ -z "$(ls -A public/build/assets 2>/dev/null)" ]; then
    echo "📦 Building frontend assets..."
    npm run build
    echo "✅ Assets built"
else
    echo "✅ Assets already built"
fi

# Check migrations
echo "🗄️  Checking database..."
php artisan migrate:status > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "✅ Database ready"
else
    echo "⚠️  Run: php artisan migrate"
fi

echo ""
echo "✅ Setup complete!"
echo ""
echo "🚀 To start the app, run these in separate terminals:"
echo ""
echo "  Terminal 1: php artisan serve --port=8000"
echo "  Terminal 2: php artisan reverb:start"
echo "  Terminal 3: php artisan queue:work"
echo ""
echo "🌐 Then visit: http://localhost:8000"
echo ""
