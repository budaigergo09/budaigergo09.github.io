#!/bin/bash

# PDC Darts Simulator - Cleanup Script
# Removes all nginx configurations created by setup scripts
# Run with: sudo bash cleanup.sh

echo "=================================="
echo "Cleaning up PDC Darts nginx setup"
echo "=================================="

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Please run with sudo: sudo bash cleanup.sh"
    exit 1
fi

# Remove site configuration
echo "[1/4] Removing nginx site config..."
rm -f /etc/nginx/sites-enabled/pdc-darts
rm -f /etc/nginx/sites-available/pdc-darts

# Restore default nginx site
echo "[2/4] Restoring default nginx site..."
if [ -f /etc/nginx/sites-available/default ]; then
    ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default
fi

# Remove log files
echo "[3/4] Removing log files..."
rm -f /var/log/nginx/pdc-darts-access.log
rm -f /var/log/nginx/pdc-darts-error.log

# Restart nginx
echo "[4/4] Restarting nginx..."
nginx -t && systemctl restart nginx

echo ""
echo "=================================="
echo "Cleanup complete!"
echo "=================================="
echo ""
echo "Default nginx has been restored."
echo "Your files in /home/admin/ are NOT deleted."
echo ""
