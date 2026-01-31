#!/bin/bash

# Script to empty all data from SQLite database
# WARNING: This will delete ALL data from the database!

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${RED}⚠️  WARNING: This will delete ALL data from the database!${NC}"
echo ""
read -p "Are you sure you want to continue? Type 'yes' to confirm: " confirm

if [ "$confirm" != "yes" ]; then
    echo -e "${YELLOW}Operation cancelled.${NC}"
    exit 1
fi

# Get database path from .env or use default
DB_PATH="${DB_DATABASE:-database/database.sqlite}"

# Check if database exists
if [ ! -f "$DB_PATH" ]; then
    echo -e "${RED}Error: Database file not found at $DB_PATH${NC}"
    exit 1
fi

echo -e "${YELLOW}Creating backup...${NC}"
BACKUP_PATH="${DB_PATH}.backup.$(date +%Y%m%d_%H%M%S)"
cp "$DB_PATH" "$BACKUP_PATH"
echo -e "${GREEN}Backup created: $BACKUP_PATH${NC}"

echo -e "${YELLOW}Emptying database...${NC}"

# Method 1: Using Laravel Artisan (Recommended - respects foreign keys)
php artisan db:wipe --force

# Method 2: Using SQLite directly (Alternative - uncomment if Method 1 doesn't work)
# sqlite3 "$DB_PATH" <<EOF
# PRAGMA foreign_keys = OFF;
# 
# -- Get all table names and delete data
# .tables | tr -s ' ' '\n' | while read table; do
#     echo "DELETE FROM $table;";
# done | sqlite3 "$DB_PATH"
# 
# -- Reset auto-increment counters (if any)
# UPDATE sqlite_sequence SET seq = 0;
# 
# PRAGMA foreign_keys = ON;
# VACUUM;
# EOF

echo -e "${GREEN}✅ Database emptied successfully!${NC}"
echo -e "${YELLOW}Backup saved at: $BACKUP_PATH${NC}"
