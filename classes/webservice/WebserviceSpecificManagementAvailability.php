<?php

class WebserviceSpecificManagementAvailability implements WebserviceSpecificManagementInterface
{
    protected $objOutput;
    protected $output;
    protected $wsObject;

    public function manage()
    {
        // Return simple JSON response
        $params = Tools::getAllValues();
        
        $response = array(
            'success' => true,
            'message' => 'Availability endpoint is working correctly',
            'timestamp' => date('Y-m-d H:i:s'),
            'received_params' => $params,
            'endpoint' => 'availability'
        );

        // Set the response
        $this->output = json_encode($response);
        return $this->output;
    }

    public function setObjectOutput(WebserviceOutputBuilderCore $obj)
    {
        $this->objOutput = $obj;
        return $this;
    }

    public function getObjectOutput()
    {
        return $this->objOutput;
    }

    public function getContent()
    {
        return $this->output;
    }

    public function getWsObject()
    {
        return $this->wsObject;
    }

    public function setWsObject(WebserviceRequestCore $obj)
    {
        $this->wsObject = $obj;
        return $this;
    }
}