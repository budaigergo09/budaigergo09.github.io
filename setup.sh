#!/bin/bash

# PDC Darts Simulator - Server Setup Script
# Run this script after git pull to set up the server

set -e  # Exit on error

echo "=========================================="
echo "PDC Darts Simulator - Setup Script"
echo "=========================================="
echo ""

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "❌ PHP is not installed!"
    echo "Please install PHP 7.4+ with SQLite3 extension"
    exit 1
fi

echo "✓ PHP found: $(php -v | head -n 1)"

# Check if SQLite3 extension is enabled
if php -m | grep -qi sqlite; then
    echo "✓ SQLite3 extension is enabled"
else
    echo "❌ SQLite3 extension is NOT enabled!"
    echo ""
    echo "Install it with one of these commands:"
    echo "  Ubuntu/Debian: sudo apt-get install php-sqlite3"
    echo "  CentOS/RHEL:   sudo yum install php-pdo"
    exit 1
fi

echo ""
echo "Setting up directories..."

# Create the data directory if it doesn't exist
mkdir -p api/data
echo "✓ Created api/data directory"

# Set permissions (may require sudo on some systems)
chmod 755 api/data
echo "✓ Set permissions on api/data (755)"

# Try to set ownership to www-data (common web server user)
if id "www-data" &>/dev/null; then
    if chown www-data:www-data api/data 2>/dev/null; then
        echo "✓ Set ownership to www-data:www-data"
    else
        echo "⚠ Could not set ownership (may need sudo)"
        echo "  Run: sudo chown www-data:www-data api/data"
    fi
fi

echo ""
echo "=========================================="
echo "Setup Complete!"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Access the Admin Panel at: https://yoursite.com/admin.html"
echo "2. Go to 'Import Data' section"
echo "3. Click 'Import Tournaments' to load all season tournaments"
echo "4. Click 'Import Players' to load all players"
echo ""
echo "Default admin credentials: root / admin"
echo ""
echo "⚠ Security reminders:"
echo "  - Change JWT_SECRET in /api/index.php"
echo "  - Change admin credentials in /admin.html"
echo ""
