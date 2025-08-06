<?php
/**
 * Check module and webservice status
 * Use this script to verify that modules are properly installed and webservice resources are available
 */

// Include QloApps configuration
$config_file = dirname(__DIR__) . '/config/config.inc.php';
if (file_exists($config_file)) {
    require_once($config_file);
} else {
    echo "ERROR: QloApps not installed yet. Configuration file not found.\n";
    exit(1);
}

echo "=== QloApps Module & Webservice Status Check ===\n";

// Check external module status
echo "\n--- External Hotel Reservation System Module ---\n";
$module = Module::getInstanceByName('externalhotelreservationsystem');
if ($module) {
    echo "Module found: " . $module->displayName . "\n";
    echo "Module ID: " . ($module->id ?: 'NOT SET') . "\n";
    echo "Module Version: " . $module->version . "\n";
    echo "Module Active: " . ($module->active ? 'Yes' : 'No') . "\n";
    echo "Module Installed: " . (Module::isInstalled('externalhotelreservationsystem') ? 'Yes' : 'No') . "\n";
    echo "Module Enabled: " . (Module::isEnabled('externalhotelreservationsystem') ? 'Yes' : 'No') . "\n";
    
    // Check webservice hook
    if (method_exists($module, 'hookAddWebserviceResources')) {
        echo "Webservice Hook: Available\n";
        $resources = $module->hookAddWebserviceResources();
        if (is_array($resources) && !empty($resources)) {
            echo "Webservice Resources: " . implode(', ', array_keys($resources)) . "\n";
            foreach ($resources as $resource_name => $resource_config) {
                echo "  - $resource_name: " . $resource_config['description'] . "\n";
                echo "    Specific Management: " . ($resource_config['specific_management'] ? 'Yes' : 'No') . "\n";
                if (isset($resource_config['specific_management_class'])) {
                    echo "    Management Class: " . $resource_config['specific_management_class'] . "\n";
                }
            }
        } else {
            echo "Webservice Resources: NONE RETURNED\n";
        }
    } else {
        echo "Webservice Hook: NOT AVAILABLE\n";
    }
} else {
    echo "❌ Module 'externalhotelreservationsystem' not found!\n";
}

// Check all installed modules
echo "\n--- All Installed Modules ---\n";
$modules = Module::getModulesInstalled();
echo "Total installed modules: " . count($modules) . "\n";

foreach ($modules as $mod) {
    if (strpos($mod['name'], 'external') !== false || strpos($mod['name'], 'hotel') !== false) {
        echo "- " . $mod['name'] . " (ID: " . $mod['id_module'] . ")\n";
    }
}

// Check webservice configuration
echo "\n--- Webservice Configuration ---\n";
$webservice_enabled = Configuration::get('PS_WEBSERVICE');
echo "Webservice Enabled: " . ($webservice_enabled ? 'Yes' : 'No') . "\n";

if ($webservice_enabled) {
    // Check webservice keys
    $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'webservice_account WHERE active = 1';
    $keys = Db::getInstance()->executeS($sql);
    echo "Active Webservice Keys: " . count($keys) . "\n";
    
    foreach ($keys as $key) {
        echo "  - Key: " . substr($key['key'], 0, 8) . "... (ID: " . $key['id_webservice_account'] . ")\n";
    }
}

// Check hooks registration
echo "\n--- Hook Registration ---\n";
$hooks = Hook::getHooks();
$webservice_hooks = array();
foreach ($hooks as $hook) {
    if (stripos($hook['name'], 'webservice') !== false) {
        $webservice_hooks[] = $hook;
        echo "Hook: " . $hook['name'] . " (ID: " . $hook['id'] . ")\n";
    }
}

// Check if our module is registered for webservice hooks
if ($module && $module->id) {
    foreach ($webservice_hooks as $hook) {
        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'hook_module 
                WHERE id_module = ' . (int)$module->id . ' 
                AND id_hook = ' . (int)$hook['id'];
        $hook_registration = Db::getInstance()->getRow($sql);
        
        if ($hook_registration) {
            echo "✅ Module registered for hook: " . $hook['name'] . "\n";
        } else {
            echo "❌ Module NOT registered for hook: " . $hook['name'] . "\n";
        }
    }
}

echo "\n=== Status Check Complete ===\n";
?>