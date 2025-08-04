<?php
require_once(dirname(__FILE__).'/../../config/config.inc.php');
require_once(dirname(__FILE__).'/../../init.php');

$module = Module::getInstanceByName('availabilityendpoint');
if (Validate::isLoadedObject($module) && !$module->install()) {
    die('Could not install module availabilityendpoint');
}

echo 'Module installed successfully';
