<?php
require_once(dirname(__FILE__).'/config/config.inc.php');
require_once(dirname(__FILE__).'/init.php');

require_once(dirname(__FILE__).'/modules/externalhotelreservationsystem/externalhotelreservationsystem.php');

$module = new ExternalHotelReservationSystem();
if ($module->install()) {
    echo 'Module installed successfully';
} else {
    echo 'Failed to install module';
}
