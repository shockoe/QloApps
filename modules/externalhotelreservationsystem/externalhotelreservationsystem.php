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
        return parent::install() && $this->registerHook('addWebserviceResources');
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
}