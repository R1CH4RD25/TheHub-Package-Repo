#!/bin/bash
# Migrate database from woodson_maintenance to woodson_hub

set -e  # Exit on error

echo "================================================"
echo "Database Migration: woodson_maintenance → woodson_hub"
echo "================================================"
echo ""

# Check if running as root or with sudo
if [ "$EUID" -ne 0 ]; then 
    echo "❌ Please run with sudo: sudo ./migrate-db-name.sh"
    exit 1
fi

OLD_DB="woodson_maintenance"
NEW_DB="woodson_hub"
DB_USER="WISDAdmin"
DB_PASS="$DB_PASSWORD"

echo "Step 1: Creating new database..."
mysql -e "CREATE DATABASE IF NOT EXISTS $NEW_DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" || exit 1
echo "✅ Database created: $NEW_DB"
echo ""

echo "Step 2: Granting permissions to $DB_USER..."
mysql -e "GRANT ALL PRIVILEGES ON $NEW_DB.* TO '$DB_USER'@'localhost'; FLUSH PRIVILEGES;" || exit 1
echo "✅ Permissions granted"
echo ""

echo "Step 3: Copying data from $OLD_DB to $NEW_DB..."
mysqldump -u $DB_USER -p"$DB_PASS" $OLD_DB | mysql -u $DB_USER -p"$DB_PASS" $NEW_DB
echo "✅ Data copied successfully"
echo ""

echo "Step 4: Verifying tables..."
TABLE_COUNT=$(mysql -u $DB_USER -p"$DB_PASS" $NEW_DB -sse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$NEW_DB';")
echo "✅ Found $TABLE_COUNT tables in $NEW_DB"
echo ""

echo "Step 5: Updating .env file..."
sed -i "s/DB_NAME=$OLD_DB/DB_NAME=$NEW_DB/" /var/www/woodson/thehub/.env
echo "✅ .env updated"
echo ""

echo "================================================"
echo "✅ Migration Complete!"
echo "================================================"
echo ""
echo "Database: $OLD_DB → $NEW_DB"
echo "Tables: $TABLE_COUNT"
echo ""
echo "The application will now use: $NEW_DB"
echo ""
echo "To rollback if needed:"
echo "  sed -i 's/DB_NAME=$NEW_DB/DB_NAME=$OLD_DB/' /var/www/woodson/thehub/.env"
echo ""
