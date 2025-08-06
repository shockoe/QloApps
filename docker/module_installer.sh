#!/bin/bash
set -eu

# Module installer script for QloApps Docker container
# This script waits for QloApps installation to complete and then installs required modules

user=${user:-qloapps}
base_dir="/home/$user/www/hotelcommerce"
max_attempts=120  # Wait up to 20 minutes (120 * 10 seconds)
attempt=0

echo "=== QloApps Module Auto-Installer ==="
echo "Waiting for QloApps installation to complete..."

# Function to check if installation is complete
check_installation_complete() {
    # Check for settings.inc.php (created at end of installation)
    if [[ -f "$base_dir/config/settings.inc.php" ]]; then
        return 0
    fi
    return 1
}

# Function to check if database is ready
check_database_ready() {
    cd "$base_dir"
    # Try to query the module table to ensure database schema is ready
    if php -r "
        require_once('config/config.inc.php'); 
        try { 
            \$result = Db::getInstance()->executeS('SELECT COUNT(*) as count FROM ' . _DB_PREFIX_ . 'module LIMIT 1'); 
            echo 'ready'; 
        } catch(Exception \$e) { 
            echo 'not_ready: ' . \$e->getMessage(); 
        }
    " 2>/dev/null | grep -q "ready"; then
        return 0
    fi
    return 1
}

# Wait for QloApps installation to complete
echo "📋 Waiting for QloApps installation to complete..."
while [[ $attempt -lt $max_attempts ]]; do
    # First check if basic config exists
    if [[ -f "$base_dir/config/config.inc.php" ]]; then
        echo "✅ Basic configuration found"
        
        # Now check if installation is fully complete
        if check_installation_complete; then
            echo "✅ Installation completion marker found (settings.inc.php)"
            
            # Finally check if database schema is ready
            if check_database_ready; then
                echo "✅ Database schema is ready for module installation"
                break
            else
                echo "⏳ Database schema not ready yet..."
            fi
        else
            echo "⏳ Installation in progress (no settings.inc.php yet)..."
        fi
    else
        echo "⏳ Waiting for QloApps basic configuration... (attempt $((attempt + 1))/$max_attempts)"
    fi
    
    sleep 10
    attempt=$((attempt + 1))
done

if [[ $attempt -eq $max_attempts ]]; then
    echo "⚠️  Timeout waiting for QloApps installation completion."
    echo "    You can manually install modules later by running:"
    echo "    docker exec <container> php /home/qloapps/www/hotelcommerce/docker/install_modules.php"
    exit 0
fi

# Additional safety delay to ensure everything is fully ready
echo "🔄 Waiting additional 10 seconds for system to stabilize..."
sleep 10

echo "🚀 Starting module installation..."

# Change to QloApps directory
cd "$base_dir"

# Run the PHP module installer
if php "docker/install_modules.php"; then
    echo "✅ Module installation completed successfully"
else
    echo "❌ Module installation failed"
fi

# Set proper ownership
chown -R "$user":"$user" "$base_dir/config" 2>/dev/null || true

echo "=== Module installation script finished ==="