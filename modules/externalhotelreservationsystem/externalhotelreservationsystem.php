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
}