#!/bin/bash

# Set packages as active tab and verify loading
echo "Testing packages tab loading fix..."

# Start local server if not already running
if ! pgrep -f "php.*8000" > /dev/null; then
    echo "Starting local server..."
    cd /var/www/woodson/thehub/public && php -S localhost:8000 &
    sleep 2
fi

# Test if packages API works
echo "Testing packages API..."
curl -s "http://localhost:8000/api/packages.php?action=installed" | head -50

echo -e "\nPackages tab loading fix implemented. Please test by:"
echo "1. Opening http://localhost:8000/admin in your browser"
echo "2. Check browser console for debug messages"
echo "3. Navigate to packages tab and see if content loads immediately"
echo "4. Refresh page while on packages tab to test localStorage restoration"