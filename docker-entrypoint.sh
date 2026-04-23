#!/bin/bash
set -e

# Copy MyBB files to persistent volume on first run
if ! [ -f /var/www/html/index.php ]; then
    echo "MyBB not found in /var/www/html - copying from /usr/src/mybb..."
    cp -r /usr/src/mybb/. /var/www/html/
    echo "MyBB copied successfully."
else
    # Sync plugins/admin modules from image to volume (for updates/additions)
    echo "Syncing plugins from image to volume..."
    # File Manager plugin
    if [ -d /usr/src/mybb/admin/modules/file ] && ! [ -d /var/www/html/admin/modules/file ]; then
        cp -r /usr/src/mybb/admin/modules/file /var/www/html/admin/modules/file
        cp -r /usr/src/mybb/admin/extension /var/www/html/admin/extension
        cp -r /usr/src/mybb/admin/jscripts/codemirror /var/www/html/admin/jscripts/codemirror
        cp -r /usr/src/mybb/inc/languages/english/admin/file_manager.lang.php /var/www/html/inc/languages/english/admin/file_manager.lang.php 2>/dev/null || true
        echo "File Manager plugin synced."
    else
        echo "Plugins already present, skipping sync."
    fi
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

echo "Fixing Apache MPM conflict..."
rm -f /etc/apache2/mods-enabled/mpm_event.*
rm -f /etc/apache2/mods-enabled/mpm_worker.*
a2enmod mpm_prefork >/dev/null 2>&1 || true

echo "Starting Apache on port 80..."
exec "$@"
