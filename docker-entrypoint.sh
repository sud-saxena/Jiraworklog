#!/bin/bash

# ✅ Auto-create .env if missing
if [ ! -f /var/www/html/.env ]; then
    echo "[INFO] .env not found. Creating from .env.example..."
    cp /var/www/html/.env.example /var/www/html/.env
fi

# Optional: log env values (for debugging)
echo "[INFO] Starting Apache..."

# Start Apache
apache2-foreground