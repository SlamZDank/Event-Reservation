#!/bin/sh
set -e

echo "=== EventRes Container Init ==="

# Generate JWT keys if they do not exist
echo "[1/4] Checking JWT keys..."
mkdir -p /var/www/config/jwt
if [ ! -f /var/www/config/jwt/private.pem ]; then
    echo "  -> JWT keys not found. Generating keypair..."
    php bin/console lexik:jwt:generate-keypair --skip-if-exists
    echo "  -> Keys generated successfully."
else
    echo "  -> Keys already exist."
fi
chmod -R 755 /var/www/config/jwt

# Create uploads directory
echo "[2/4] Ensuring upload directories..."
mkdir -p public/uploads/events
chmod -R 777 public/uploads

# Clear cache for clean start
echo "[3/4] Warming cache..."
php bin/console cache:clear --no-warmup 2>/dev/null || true
php bin/console cache:warmup 2>/dev/null || true

echo "[4/4] Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# Uncomment the following line to seed the database with fixtures on startup:
# php bin/console doctrine:fixtures:load --no-interaction

echo "=== Starting PHP-FPM ==="
exec php-fpm
