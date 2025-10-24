#!/bin/bash
# The Hub - Automated System Package Installation
# Detects OS and installs required packages

set -e  # Exit on error

echo "════════════════════════════════════════════════════════════"
echo "  The Hub - System Package Installer"
echo "════════════════════════════════════════════════════════════"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "⚠️  This script must be run as root or with sudo"
    echo "   Please run: sudo $0"
    echo ""
    exit 1
fi

# Detect OS
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
    VERSION=$VERSION_ID
else
    echo "❌ Cannot detect operating system"
    exit 1
fi

echo "Detected OS: $PRETTY_NAME"
echo ""

# Ubuntu/Debian
if [ "$OS" = "ubuntu" ] || [ "$OS" = "debian" ]; then
    echo "Installing packages for Ubuntu/Debian..."
    echo "────────────────────────────────────────────────────────────"
    
    # Update package list
    echo "📦 Updating package list..."
    apt update
    
    # Install packages
    echo "📦 Installing required packages..."
    apt install -y \
        apache2 \
        mariadb-server mariadb-client \
        php8.1 php8.1-cli php8.1-fpm php8.1-mysql php8.1-mbstring php8.1-xml \
        php8.1-curl php8.1-zip php8.1-gd php8.1-intl php8.1-bcmath \
        libapache2-mod-php8.1 \
        certbot python3-certbot-apache \
        git curl wget unzip nano
    
    # Enable Apache modules
    echo "🔧 Enabling Apache modules..."
    a2enmod rewrite ssl headers
    
    # Enable and start services
    echo "🚀 Starting services..."
    systemctl enable apache2
    systemctl enable mariadb
    systemctl start apache2
    systemctl start mariadb
    
    echo ""
    echo "✅ Ubuntu/Debian packages installed successfully!"

# CentOS/RHEL/Rocky/AlmaLinux
elif [ "$OS" = "centos" ] || [ "$OS" = "rhel" ] || [ "$OS" = "rocky" ] || [ "$OS" = "almalinux" ]; then
    echo "Installing packages for CentOS/RHEL/Rocky/Alma..."
    echo "────────────────────────────────────────────────────────────"
    
    # Install packages
    echo "📦 Installing required packages..."
    dnf install -y \
        httpd \
        mariadb-server mariadb \
        php php-cli php-fpm php-mysqlnd php-mbstring php-xml \
        php-curl php-zip php-gd php-intl php-bcmath php-json \
        certbot python3-certbot-apache \
        git curl wget unzip nano
    
    # Enable and start services
    echo "🚀 Starting services..."
    systemctl enable httpd
    systemctl enable mariadb
    systemctl start httpd
    systemctl start mariadb
    
    # Configure firewall
    echo "🔒 Configuring firewall..."
    firewall-cmd --permanent --add-service=http
    firewall-cmd --permanent --add-service=https
    firewall-cmd --reload
    
    echo ""
    echo "✅ CentOS/RHEL/Rocky/Alma packages installed successfully!"

else
    echo "❌ Unsupported operating system: $OS"
    echo "   Please install packages manually"
    echo "   See: packages.txt for package list"
    exit 1
fi

echo ""
echo "════════════════════════════════════════════════════════════"
echo "  Next Steps"
echo "════════════════════════════════════════════════════════════"
echo ""
echo "1. Install Composer:"
echo "   curl -sS https://getcomposer.org/installer | php"
echo "   sudo mv composer.phar /usr/local/bin/composer"
echo "   sudo chmod +x /usr/local/bin/composer"
echo ""
echo "2. Secure MariaDB:"
echo "   sudo mysql_secure_installation"
echo ""
echo "3. Clone The Hub repository:"
echo "   cd /var/www"
echo "   git clone https://github.com/yourusername/thehub.git"
echo "   cd thehub"
echo ""
echo "4. Install PHP dependencies:"
echo "   composer install"
echo ""
echo "5. Continue with installation:"
echo "   See QUICKSTART.md for detailed steps"
echo ""
echo "════════════════════════════════════════════════════════════"
echo ""

# Verify installations
echo "📋 Verification:"
echo "────────────────────────────────────────────────────────────"
php -v | head -n 1
mysql --version
if command -v apache2 &> /dev/null; then
    apache2 -v | head -n 1
elif command -v httpd &> /dev/null; then
    httpd -v | head -n 1
fi
git --version
echo ""
echo "✅ All core packages installed!"
echo ""
