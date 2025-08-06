<?php
/**
 * Auto-install required modules for QloApps Docker container
 * This script runs after QloApps installation is complete to ensure
 * essential modules are installed and enabled by default.
 */

// Include QloApps configuration
$config_file = dirname(__DIR__) . '/config/config.inc.php';
if (file_exists($config_file)) {
    require_once($config_file);
} else {
    echo "ERROR: QloApps not installed yet. Configuration file not found.\n";
    exit(1);
}

// Verify database connection
try {
    $db = Db::getInstance();
    $result = $db->executeS('SELECT COUNT(*) as count FROM ' . _DB_PREFIX_ . 'module LIMIT 1');
    echo "✅ Database connection verified (found " . $result[0]['count'] . " existing modules)\n";
} catch (Exception $e) {
    echo "❌ Database not ready: " . $e->getMessage() . "\n";
    exit(1);
}

// List of modules that should be installed by default
$required_modules = array(
    'externalhotelreservationsystem' => 'External Hotel Reservation System API'
);

echo "=== Auto-installing required modules ===\n";

$success_count = 0;
$total_count = count($required_modules);

foreach ($required_modules as $module_name => $module_description) {
    echo "Processing module: $module_name ($module_description)\n";
    
    try {
        // Check if module directory exists
        $module_path = _PS_MODULE_DIR_ . $module_name;
        if (!is_dir($module_path)) {
            echo "❌ Module directory not found: $module_path\n";
            continue;
        }
        
        // Get module instance
        $module = Module::getInstanceByName($module_name);
        
        if (!$module) {
            echo "❌ Failed to get module instance for '$module_name'\n";
            continue;
        }
        
        // Check if already installed
        if ($module->id && Module::isInstalled($module_name)) {
            echo "✅ Module '$module_name' already installed (ID: {$module->id})\n";
            
            // Ensure it's enabled
            if (!Module::isEnabled($module_name)) {
                echo "⚠️  Module '$module_name' is disabled, enabling...\n";
                if ($module->enable()) {
                    echo "✅ Module '$module_name' enabled successfully\n";
                } else {
                    echo "❌ Failed to enable module '$module_name'\n";
                }
            } else {
                echo "✅ Module '$module_name' is already enabled\n";
            }
            
            $success_count++;
        } else {
            // Install the module
            echo "📦 Installing module '$module_name'...\n";
            
            if ($module->install()) {
                echo "✅ Module '$module_name' installed successfully (ID: {$module->id})\n";
                $success_count++;
                
                // Verify hooks are registered
                if (method_exists($module, 'hookAddWebserviceResources')) {
                    echo "✅ Module has webservice hook - checking registration...\n";
                    
                    // Force hook registration verification
                    $webservice_resources = $module->hookAddWebserviceResources();
                    if (is_array($webservice_resources) && !empty($webservice_resources)) {
                        echo "✅ Webservice resources registered: " . implode(', ', array_keys($webservice_resources)) . "\n";
                    }
                }
            } else {
                echo "❌ Failed to install module '$module_name'\n";
                if (isset($module->_errors) && $module->_errors) {
                    foreach ($module->_errors as $error) {
                        echo "   Error: $error\n";
                    }
                }
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Exception installing module '$module_name': " . $e->getMessage() . "\n";
        echo "   Stack trace: " . $e->getTraceAsString() . "\n";
    }
    
    echo "---\n";
}

echo "=== Module installation completed ===\n";
echo "Successfully processed: $success_count/$total_count modules\n";

// Final verification
echo "\n=== Final Module Status Verification ===\n";
foreach ($required_modules as $module_name => $module_description) {
    $module = Module::getInstanceByName($module_name);
    if ($module && $module->id) {
        $installed = Module::isInstalled($module_name) ? 'Yes' : 'No';
        $enabled = Module::isEnabled($module_name) ? 'Yes' : 'No';
        echo "$module_name: ID={$module->id}, Installed=$installed, Enabled=$enabled\n";
    } else {
        echo "$module_name: NOT INSTALLED\n";
    }
}

echo "\n✨ Module installation script finished\n";
?>