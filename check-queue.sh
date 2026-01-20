#!/bin/bash

cd /var/www/kawhe

echo "=== Queue Status Check ==="
echo ""

echo "1. Queue Configuration:"
php artisan config:show queue.default 2>/dev/null | tail -1
echo ""

echo "2. Pending Jobs:"
PENDING=$(php artisan tinker --execute="echo \DB::table('jobs')->count();" 2>/dev/null | tail -1)
echo "   $PENDING jobs in queue"
echo ""

echo "3. Failed Jobs:"
FAILED=$(php artisan tinker --execute="echo \DB::table('failed_jobs')->count();" 2>/dev/null | tail -1)
echo "   $FAILED failed jobs"
echo ""

echo "4. Queue Worker Status:"
if pgrep -f "queue:work" > /dev/null; then
    echo "   ✓ Queue worker is running"
    ps aux | grep "queue:work" | grep -v grep | head -1
else
    echo "   ✗ Queue worker is NOT running"
    echo ""
    echo "   To start queue worker:"
    echo "   php artisan queue:work --daemon"
    echo ""
    echo "   OR use sync queue (processes immediately):"
    echo "   Add to .env: QUEUE_CONNECTION=sync"
    echo "   Then: php artisan config:clear"
fi
echo ""

echo "5. Recent Jobs (last 5):"
php artisan tinker --execute="
\$jobs = \DB::table('jobs')->orderBy('id', 'desc')->limit(5)->get();
if (\$jobs->count() > 0) {
    foreach (\$jobs as \$job) {
        echo '   Job ID: ' . \$job->id . ', Queue: ' . \$job->queue . ', Created: ' . \$job->created_at . PHP_EOL;
    }
} else {
    echo '   No pending jobs' . PHP_EOL;
}
" 2>/dev/null | tail -5
echo ""

echo "=== Quick Fix ==="
echo ""
echo "Option A: Use Sync Queue (Immediate Processing)"
echo "  1. Add to .env: QUEUE_CONNECTION=sync"
echo "  2. php artisan config:clear"
echo "  3. php artisan config:cache"
echo ""
echo "Option B: Start Queue Worker"
echo "  1. php artisan queue:work --daemon"
echo "  2. Or set up supervisor/systemd for production"
echo ""
