# Database Setup for Production

## ❌ Why SQLite is NOT Recommended for Production

### Limitations of SQLite:

1. **Concurrency Issues:**
   - SQLite uses file-level locking
   - Only ONE write operation at a time
   - Multiple users stamping = database locks
   - Queue jobs will conflict with web requests

2. **No Network Access:**
   - File-based database (single file)
   - Cannot be accessed over network
   - Cannot scale horizontally
   - Cannot use read replicas

3. **Performance:**
   - Slower for concurrent operations
   - No connection pooling
   - Limited query optimization
   - File I/O bottlenecks

4. **Scalability:**
   - Cannot handle high traffic
   - No sharding support
   - Limited to single server

5. **Production Features Missing:**
   - No replication
   - No backup tools (must copy file)
   - No user management
   - Limited monitoring

## ✅ Recommended: MySQL or PostgreSQL

### MySQL/MariaDB (Recommended for Laravel)

**Pros:**
- Excellent Laravel support
- Easy to set up
- Good performance
- Great tooling
- Widely used

**Cons:**
- Slightly less advanced features than PostgreSQL

### PostgreSQL (Alternative)

**Pros:**
- More advanced features
- Better for complex queries
- Excellent JSON support
- Strong data integrity

**Cons:**
- Slightly more complex setup
- Less common in Laravel projects

## 🚀 Quick Setup: MySQL/MariaDB

### Step 1: Install MySQL

**Ubuntu/Debian:**
```bash
sudo apt update
sudo apt install mysql-server
sudo mysql_secure_installation
```

**CentOS/RHEL:**
```bash
sudo yum install mysql-server
sudo systemctl start mysqld
sudo mysql_secure_installation
```

### Step 2: Create Database and User

```bash
sudo mysql -u root -p
```

Then in MySQL:
```sql
-- Create database
CREATE DATABASE kawhe_loyalty CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user
CREATE USER 'kawhe_user'@'localhost' IDENTIFIED BY 'your_secure_password_here';

-- Grant privileges
GRANT ALL PRIVILEGES ON kawhe_loyalty.* TO 'kawhe_user'@'localhost';

-- Apply changes
FLUSH PRIVILEGES;

-- Exit
EXIT;
```

### Step 3: Update .env File

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kawhe_loyalty
DB_USERNAME=kawhe_user
DB_PASSWORD=your_secure_password_here
```

### Step 4: Test Connection

```bash
php artisan tinker
```

```php
\DB::connection()->getPdo();
echo "✅ Database connected!\n";
exit
```

### Step 5: Run Migrations

```bash
php artisan migrate --force
```

## 🔄 Migrating from SQLite to MySQL

### Step 1: Backup SQLite Database

```bash
# If you have existing SQLite data
cp database/database.sqlite database/database.sqlite.backup
```

### Step 2: Export Data (if needed)

If you have existing data in SQLite:

```bash
# Install sqlite3 if not available
sudo apt install sqlite3

# Export to SQL
sqlite3 database/database.sqlite .dump > sqlite_export.sql
```

### Step 3: Set Up MySQL

Follow steps above to install and configure MySQL.

### Step 4: Update .env

Change from:
```env
DB_CONNECTION=sqlite
# DB_DATABASE=database/database.sqlite
```

To:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kawhe_loyalty
DB_USERNAME=kawhe_user
DB_PASSWORD=your_password
```

### Step 5: Run Migrations

```bash
php artisan migrate:fresh --force
# This will create all tables in MySQL
```

### Step 6: Import Data (if you had SQLite data)

**Option A: Manual Import (if data is small)**
- Export from SQLite to CSV
- Import to MySQL

**Option B: Use Laravel Tinker**
```php
// Read from SQLite
DB::connection('sqlite')->table('users')->get();

// Write to MySQL
DB::connection('mysql')->table('users')->insert(...);
```

**Option C: Use Migration Script**
Create a one-time migration to copy data.

### Step 7: Verify

```bash
php artisan tinker
```

```php
// Check tables
\DB::select('SHOW TABLES');

// Check data
\App\Models\User::count();
\App\Models\Store::count();
\App\Models\LoyaltyAccount::count();

exit
```

## 📊 Database Optimization

### 1. Add Indexes

Your migrations should already have indexes, but verify:

```bash
php artisan tinker
```

```php
// Check indexes on loyalty_accounts
\DB::select("SHOW INDEXES FROM loyalty_accounts");

// Check indexes on stores
\DB::select("SHOW INDEXES FROM stores");
exit
```

### 2. Configure MySQL

Edit `/etc/mysql/mysql.conf.d/mysqld.cnf`:

```ini
[mysqld]
# Increase connection pool
max_connections = 200

# Buffer pool (adjust based on RAM)
innodb_buffer_pool_size = 1G

# Query cache (if using MySQL < 8.0)
query_cache_size = 64M
query_cache_type = 1

# Log slow queries
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow-query.log
long_query_time = 2
```

Restart MySQL:
```bash
sudo systemctl restart mysql
```

### 3. Monitor Performance

```bash
# Check connections
mysql -u root -p -e "SHOW PROCESSLIST;"

# Check slow queries
sudo tail -f /var/log/mysql/slow-query.log

# Check table sizes
mysql -u root -p -e "SELECT table_name, ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)' FROM information_schema.TABLES WHERE table_schema = 'kawhe_loyalty' ORDER BY (data_length + index_length) DESC;"
```

## 🔒 Security Best Practices

### 1. Use Strong Passwords

```bash
# Generate secure password
openssl rand -base64 32
```

### 2. Limit User Privileges

Only grant necessary privileges:
```sql
-- Don't use root for application
CREATE USER 'kawhe_user'@'localhost' IDENTIFIED BY 'password';
GRANT SELECT, INSERT, UPDATE, DELETE ON kawhe_loyalty.* TO 'kawhe_user'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Disable Remote Access (if not needed)

```bash
# Edit /etc/mysql/mysql.conf.d/mysqld.cnf
bind-address = 127.0.0.1
```

### 4. Regular Backups

```bash
# Create backup script
#!/bin/bash
BACKUP_DIR="/var/backups/mysql"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p $BACKUP_DIR

mysqldump -u kawhe_user -p'password' kawhe_loyalty | gzip > $BACKUP_DIR/kawhe_loyalty_$DATE.sql.gz

# Keep only last 7 days
find $BACKUP_DIR -name "*.sql.gz" -mtime +7 -delete
```

Add to crontab:
```bash
# Daily backup at 2 AM
0 2 * * * /path/to/backup-script.sh
```

## 📋 Production Checklist

- [ ] MySQL/MariaDB installed
- [ ] Database created with utf8mb4 charset
- [ ] User created with secure password
- [ ] `.env` updated with MySQL credentials
- [ ] Connection tested
- [ ] Migrations run successfully
- [ ] Indexes verified
- [ ] Backup script configured
- [ ] MySQL optimized for production
- [ ] Slow query log enabled
- [ ] Remote access disabled (if not needed)

## 🧪 Testing Database

### Test Connection
```bash
php artisan tinker
\DB::connection()->getPdo();
echo "✅ Connected\n";
exit
```

### Test Queries
```bash
php artisan tinker
```

```php
// Test basic queries
\App\Models\User::count();
\App\Models\Store::count();
\App\Models\LoyaltyAccount::count();

// Test relationships
$account = \App\Models\LoyaltyAccount::first();
$account->store;
$account->customer;

// Test transactions
\DB::transaction(function() {
    \App\Models\User::create(['name' => 'Test', 'email' => 'test@test.com', 'password' => bcrypt('test')]);
});

exit
```

### Test Performance
```bash
# Time a query
time php artisan tinker --execute="\App\Models\LoyaltyAccount::with('store', 'customer')->get();"
```

## 🔧 Troubleshooting

### Issue: "Access denied for user"
**Fix:**
- Check username/password in `.env`
- Verify user exists: `SELECT User FROM mysql.user;`
- Check privileges: `SHOW GRANTS FOR 'kawhe_user'@'localhost';`

### Issue: "Can't connect to MySQL server"
**Fix:**
- Check MySQL is running: `sudo systemctl status mysql`
- Check port: `netstat -tlnp | grep 3306`
- Check bind address in config

### Issue: "Table doesn't exist"
**Fix:**
- Run migrations: `php artisan migrate --force`
- Check database name in `.env`

### Issue: "Too many connections"
**Fix:**
- Increase `max_connections` in MySQL config
- Check for connection leaks in code
- Use connection pooling

## 📈 Scaling Considerations

### For High Traffic:

1. **Read Replicas:**
   - Set up MySQL replication
   - Use read replicas for queries
   - Write to master only

2. **Connection Pooling:**
   - Use PgBouncer (for PostgreSQL)
   - Or MySQL Proxy

3. **Caching:**
   - Use Redis for cache
   - Cache frequently accessed data

4. **Query Optimization:**
   - Add indexes
   - Optimize slow queries
   - Use EXPLAIN to analyze queries

## ✅ Recommended Production Setup

```env
# .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kawhe_loyalty
DB_USERNAME=kawhe_user
DB_PASSWORD=secure_password_here

# Use Redis for cache (optional but recommended)
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=database  # or redis if you prefer
```

## 🎯 Summary

**For Production:**
- ✅ Use **MySQL** or **PostgreSQL**
- ❌ Do NOT use **SQLite**

**Quick Setup:**
1. Install MySQL
2. Create database and user
3. Update `.env`
4. Run migrations
5. Test connection
6. Set up backups

Your Laravel app will perform much better with MySQL in production!
