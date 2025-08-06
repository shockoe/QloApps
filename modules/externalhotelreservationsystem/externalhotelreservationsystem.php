<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class ExternalHotelReservationSystem extends Module
{
    public function __construct()
    {
        $this->name = 'externalhotelreservationsystem';
        $this->tab = 'webservice';
        $this->version = '1.0.0';
        $this->author = 'Gemini';
        $this->need_instance = 0;
        parent::__construct();
        $this->displayName = $this->l('External Hotel Reservation System');
        $this->description = $this->l('Provides an external API for the hotel reservation system.');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        require_once(dirname(__FILE__).'/classes/ExternalRoomLockManager.php');
        
        return parent::install() && 
               $this->registerHook('addWebserviceResources') && 
               $this->registerHook('actionCartRoomSearchSqlModifier') &&
               $this->createDatabaseTables() &&
               ExternalRoomLockManager::createLocksTable();
    }

    public function hookAddWebserviceResources()
    {
        require_once(dirname(__FILE__).'/classes/WebserviceSpecificManagementExternal.php');
        return array(
            'external' => array(
                'description' => 'External Hotel Reservation API',
                'specific_management' => true,
                'specific_management_class' => 'WebserviceSpecificManagementExternal',
            ),
        );
    }
    
    public function hookActionCartRoomSearchSqlModifier($params)
    {
        // Check if this is an admin context 
        $context = Context::getContext();
        if (isset($context->employee->id)) {
            // For admin context, search all carts globally to show rooms from ALL carts (including external API carts)
            // This ensures the admin panel sees true room availability across all booking channels
            $allowedIdRoomTypes = isset($params['params']['all_params']['allowedIdRoomTypes']) 
                ? $params['params']['all_params']['allowedIdRoomTypes'] 
                : '0';
            
            $params['where'] = 'WHERE cbd.`id_hotel`= '.(int)$params['params']['id_hotel'].' 
                AND cbd.`is_refunded` = 0 AND cbd.`is_back_order` = 0
                AND IF('.(int)$params['params']['id_product'].' > 0, rf.`id_product` = '.(int)$params['params']['id_product'].', 1) 
                AND rf.`id_product` IN ('.$allowedIdRoomTypes.')';
        }
    }

    private function createDatabaseTables()
    {
        $sql = [];
        
        // Cart token management table
        $sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'htl_external_cart_tokens` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `cart_token` VARCHAR(255) UNIQUE NOT NULL,
            `id_cart` INT NOT NULL,
            `id_customer` INT,
            `expires_at` DATETIME NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_cart_token` (`cart_token`),
            INDEX `idx_cart_id` (`id_cart`),
            INDEX `idx_expires` (`expires_at`)
        ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';
        
        // External booking references table
        $sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'htl_external_booking_refs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `booking_id` VARCHAR(100) UNIQUE NOT NULL,
            `id_order` INT NOT NULL,
            `confirmation_number` VARCHAR(50),
            `external_ref` VARCHAR(255),
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_booking_id` (`booking_id`),
            INDEX `idx_order_id` (`id_order`)
        ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                error_log('ExternalHotelReservationSystem: Failed to create database table. Query: ' . $query . ' Error: ' . Db::getInstance()->getMsgError());
                return false;
            }
        }
        
        return true;
    }

    private function dropDatabaseTables()
    {
        $sql = [
            'DROP TABLE IF EXISTS `'._DB_PREFIX_.'htl_external_cart_tokens`',
            'DROP TABLE IF EXISTS `'._DB_PREFIX_.'htl_external_booking_refs`'
        ];

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                error_log('ExternalHotelReservationSystem: Failed to drop database table. Query: ' . $query . ' Error: ' . Db::getInstance()->getMsgError());
                return false;
            }
        }
        
        return true;
    }

    public function uninstall()
    {
        require_once(dirname(__FILE__).'/classes/ExternalRoomLockManager.php');
        
        return parent::uninstall() && 
               $this->unregisterHook('addWebserviceResources') && 
               $this->unregisterHook('actionCartRoomSearchSqlModifier') &&
               $this->dropDatabaseTables() &&
               ExternalRoomLockManager::dropLocksTable();
    }
}