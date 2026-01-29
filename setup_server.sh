#!/bin/bash

# PDC Darts Simulator - Nginx Server Setup Script
# Run this script with sudo: sudo bash setup_server.sh

# Configuration
PORT=8080
SITE_NAME="pdc-darts"
WEB_ROOT=$(pwd)

echo "=================================="
echo "PDC Darts Simulator - Server Setup"
echo "=================================="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Please run this script with sudo:"
    echo "  sudo bash setup_server.sh"
    exit 1
fi

# Install nginx if not installed
echo "[1/5] Checking nginx installation..."
if ! command -v nginx &> /dev/null; then
    echo "  Installing nginx..."
    apt-get update
    apt-get install -y nginx
else
    echo "  nginx is already installed"
fi

# Install PHP if not installed (for the API)
echo "[2/5] Checking PHP installation..."
if ! command -v php &> /dev/null; then
    echo "  Installing PHP and required modules..."
    apt-get install -y php-fpm php-sqlite3
else
    echo "  PHP is already installed"
fi

# Get PHP-FPM socket path
PHP_FPM_SOCK=$(find /var/run/php/ -name "*.sock" 2>/dev/null | head -1)
if [ -z "$PHP_FPM_SOCK" ]; then
    PHP_FPM_SOCK="/var/run/php/php-fpm.sock"
fi

# Set file permissions
echo "[3/5] Setting file permissions..."
chown -R www-data:www-data "$WEB_ROOT"
chmod -R 755 "$WEB_ROOT"

# Create data directory for SQLite database
mkdir -p "$WEB_ROOT/api/data"
chown -R www-data:www-data "$WEB_ROOT/api/data"
chmod -R 775 "$WEB_ROOT/api/data"

echo "  Permissions set for: $WEB_ROOT"

# Create nginx configuration
echo "[4/5] Creating nginx configuration..."
cat > /etc/nginx/sites-available/$SITE_NAME << EOF
server {
    listen $PORT;
    listen [::]:$PORT;
    
    server_name localhost;
    root $WEB_ROOT;
    index index.html index.php;
    
    # Main site
    location / {
        try_files \$uri \$uri/ =404;
    }
    
    # PHP API handling
    location /api {
        try_files \$uri \$uri/ /api/index.php?\$query_string;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:$PHP_FPM_SOCK;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Security - deny access to sensitive files
    location ~ /\.ht {
        deny all;
    }
    
    location ~ /api/data/ {
        deny all;
    }
    
    # CORS headers for API
    location ~* ^/api/ {
        add_header 'Access-Control-Allow-Origin' '*' always;
        add_header 'Access-Control-Allow-Methods' 'GET, POST, OPTIONS' always;
        add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization' always;
        
        if (\$request_method = 'OPTIONS') {
            return 204;
        }
    }
}
EOF

# Disable default site and enable our site
rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/$SITE_NAME /etc/nginx/sites-enabled/

# Stop any existing nginx process
systemctl stop nginx 2>/dev/null || service nginx stop 2>/dev/null
killall nginx 2>/dev/null

# Test nginx configuration
echo "[5/5] Testing and starting nginx..."
nginx -t

if [ $? -eq 0 ]; then
    # Restart services
    systemctl restart php*-fpm 2>/dev/null || service php*-fpm restart 2>/dev/null
    systemctl restart nginx 2>/dev/null || service nginx restart 2>/dev/null
    
    echo ""
    echo "=================================="
    echo "✓ Setup Complete!"
    echo "=================================="
    echo ""
    echo "Your server is now running at:"
    echo "  http://localhost:$PORT"
    echo ""
    echo "API endpoint:"
    echo "  http://localhost:$PORT/api"
    echo ""
    echo "Web root: $WEB_ROOT"
    echo ""
    echo "To stop the server:"
    echo "  sudo systemctl stop nginx"
    echo ""
    echo "To view logs:"
    echo "  sudo tail -f /var/log/nginx/error.log"
    echo ""
else
    echo ""
    echo "ERROR: nginx configuration test failed!"
    echo "Please check the configuration manually."
    exit 1
fi
