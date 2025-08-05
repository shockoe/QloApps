<?php
// Simple installation script to create the room locks table
require_once(dirname(__FILE__).'/../../config/config.inc.php');
require_once(dirname(__FILE__).'/classes/ExternalRoomLockManager.php');

echo "Creating room booking locks table...\n";

if (ExternalRoomLockManager::createLocksTable()) {
    echo "✅ Table created successfully!\n";
} else {
    echo "❌ Failed to create table\n";
}