<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class AvailabilityEndpoint extends Module
{
    public function __construct()
    {
        $this->name = 'availabilityendpoint';
        $this->tab = 'webservice';
        $this->version = '1.0.0';
        $this->author = 'Gemini';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.6',
            'max' => _PS_VERSION_,
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Availability Endpoint');
        $this->description = $this->l('Adds a webservice endpoint for hotel availability search.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function install()
    {
        return parent::install() && $this->registerHook('addWebserviceResources');
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    public function hookAddWebserviceResources($params)
    {
        return [
            'availability' => [
                'description' => 'Hotel availability search',
                'specific_management' => true,
            ],
        ];
    }
}
