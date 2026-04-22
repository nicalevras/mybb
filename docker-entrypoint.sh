#!/bin/bash
set -e

# Copy MyBB files to persistent volume on first run
if ! [ -f /var/www/html/index.php ]; then
    echo "MyBB not found in /var/www/html - copying from /usr/src/mybb..."
    cp -r /usr/src/mybb/. /var/www/html/
    echo "MyBB copied successfully."
fi

# Set ownership
chown -R www-data:www-data /var/www/html

# Set file permissions
find /var/www/html -type d -exec chmod 755 {} \;
find /var/www/html -type f -exec chmod 644 {} \;

# Writable directories for MyBB
mkdir -p /var/www/html/cache/themes
mkdir -p /var/www/html/uploads/avatars
chmod -R 777 /var/www/html/cache
chmod -R 777 /var/www/html/uploads

# Make settings.php writable for installer
[ -f /var/www/html/inc/settings.php ] && chmod 666 /var/www/html/inc/settings.php

echo "Starting Apache on port 80..."
exec "$@"
