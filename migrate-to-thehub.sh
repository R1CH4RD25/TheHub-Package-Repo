#!/bin/bash
# Migration script: maintenance -> thehub
# Changes domain from maintenance.woodsonisd.net to hub.woodsonisd.net

set -e  # Exit on any error

echo "================================================"
echo "Migration: maintenance → thehub"
echo "Domain: maintenance.woodsonisd.net → hub.woodsonisd.net"
echo "================================================"
echo ""

# Check if running as root or with sudo
if [ "$EUID" -ne 0 ]; then 
    echo "❌ Please run with sudo"
    exit 1
fi

CURRENT_DIR="/var/www/woodson/maintenance"
NEW_DIR="/var/www/woodson/thehub"

echo "Step 1: Update .env file..."
sed -i 's|maintenance.woodsonisd.net|hub.woodsonisd.net|g' "$CURRENT_DIR/.env"
sed -i 's|/var/www/woodson/maintenance/|/var/www/woodson/thehub/|g' "$CURRENT_DIR/.env"
sed -i 's|maintenance@woodsonisd.net|hub@woodsonisd.net|g' "$CURRENT_DIR/.env"
sed -i 's|"Woodson ISD Maintenance"|"Woodson ISD Hub"|g' "$CURRENT_DIR/.env"
echo "✅ .env file updated"
echo ""

echo "Step 2: Disable current Apache sites..."
a2dissite maintenance.woodsonisd.net.conf
a2dissite maintenance.woodsonisd.net-le-ssl.conf 2>/dev/null || echo "  (SSL config not found, skipping)"
echo "✅ Sites disabled"
echo ""

echo "Step 3: Rename directory..."
if [ -d "$NEW_DIR" ]; then
    echo "❌ Directory $NEW_DIR already exists!"
    exit 1
fi
mv "$CURRENT_DIR" "$NEW_DIR"
echo "✅ Renamed $CURRENT_DIR → $NEW_DIR"
echo ""

echo "Step 4: Copy new Apache configuration..."
cp "$NEW_DIR/apache/hub.woodsonisd.net.conf" /etc/apache2/sites-available/
chown root:root /etc/apache2/sites-available/hub.woodsonisd.net.conf
chmod 644 /etc/apache2/sites-available/hub.woodsonisd.net.conf
echo "✅ Configuration copied"
echo ""

echo "Step 5: Enable new site..."
a2ensite hub.woodsonisd.net.conf
echo "✅ Site enabled"
echo ""

echo "Step 6: Test Apache configuration..."
apache2ctl configtest
echo "✅ Configuration valid"
echo ""

echo "Step 7: Reload Apache..."
systemctl reload apache2
echo "✅ Apache reloaded"
echo ""

echo "================================================"
echo "✅ Migration Complete!"
echo "================================================"
echo ""
echo "✅ Completed:"
echo "  • Directory: /var/www/woodson/maintenance → /var/www/woodson/thehub"
echo "  • Apache config: hub.woodsonisd.net.conf installed"
echo "  • .env file updated with new domain and paths"
echo ""
echo "⚠️  IMPORTANT - Manual steps required:"
echo ""
echo "1. 🌐 Update DNS:"
echo "   hub.woodsonisd.net → $(hostname -I | awk '{print $1}')"
echo ""
echo "2. 🔒 Run Certbot for SSL (AFTER DNS is updated):"
echo "   sudo certbot --apache -d hub.woodsonisd.net"
echo ""
echo "3. 🔑 Update Google OAuth Console:"
echo "   https://console.cloud.google.com/apis/credentials"
echo "   Update redirect URI to:"
echo "   https://hub.woodsonisd.net/google_login.php"
echo ""
echo "4. 📧 Update email configuration (optional):"
echo "   Change hub@woodsonisd.net in Google Workspace if needed"
echo ""
echo "📝 Log locations:"
echo "  Old: /var/log/apache2/maintenance.woodsonisd.net-*.log"
echo "  New: /var/log/apache2/hub.woodsonisd.net-*.log"
echo ""
echo "🔗 Current .env settings:"
grep "APP_URL\|GOOGLE_REDIRECT_URI\|MAIL_FROM" "$NEW_DIR/.env"
echo ""
