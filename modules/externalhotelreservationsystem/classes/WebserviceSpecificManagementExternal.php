<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class WebserviceSpecificManagementExternal implements WebserviceSpecificManagementInterface
{
    protected $wsObject;
    protected $output;
    protected $objOutput;

    public function manage()
    {
        try {
            error_log('WebserviceSpecificManagementExternal::manage() called.');
            $method = $_SERVER['REQUEST_METHOD'];
            $this->logMessage('Entering manage() method. Request Method: ' . $method);
            
            // The WebService system calls this for the "external" resource
            // Based on the existing availability endpoint pattern, we'll handle different sub-endpoints
            // via URL parameters instead of PATH_INFO
            
            $params = Tools::getAllValues();
            $this->logMessage('Request params: ' . json_encode($params));
            
            // Determine endpoint from URL structure or parameters
            // For now, default to availability (like the existing system)
            $endpoint = isset($params['url']) ? str_replace('external/', '', $params['url']) : 'availability';
            
            $this->logMessage('Detected endpoint: ' . $endpoint);
            
            switch ($endpoint) {
                case 'availability':
                    return $this->handleAvailability($method);
                case 'add-to-cart':
                    return $this->handleAddToCart($method);
                case 'make-reservation':
                    return $this->handleMakeReservation($method);
                case 'booking':
                    return $this->handleBookingDetails($method, $params);
                case 'customer-signup':
                    return $this->handleCustomerSignup($method);
                case 'customer-login':
                    return $this->handleCustomerLogin($method);
                case 'cart-details':
                    return $this->handleCartDetails($method);
                case 'empty-cart':
                    return $this->handleEmptyCart($method);
                default:
                    // Default to availability for backward compatibility
                    return $this->handleAvailability($method);
            }
        } catch (Exception $e) {
            $this->logMessage('Exception in manage(): ' . $e->getMessage(), 'error');
            return $this->errorResponse('Internal server error: ' . $e->getMessage(), 500);
        }
    }

    protected function handleAvailability($method)
    {
        $this->logMessage('Entering handleAvailability() method. Method: ' . $method);
        if ($method !== 'GET') {
            $this->logMessage('Method Not Allowed for availability: ' . $method, 'error');
            return $this->errorResponse('Method Not Allowed', 405);
        }
        
        try {
            require_once(dirname(__FILE__).'/ExternalAvailabilityManager.php');
            $manager = new ExternalAvailabilityManager();
            $result = $manager->searchAvailability($_GET);
            $this->logMessage('Availability result: ' . json_encode($result));
            
            $this->output = json_encode($result);
            return $this->output;
        } catch (Exception $e) {
            $this->logMessage('Exception in handleAvailability: ' . $e->getMessage(), 'error');
            return $this->errorResponse('Internal error: ' . $e->getMessage(), 500);
        }
    }

    public function getContent()
    {
        return $this->output;
    }

    protected function handleAddToCart($method)
    {
        $this->logMessage('Entering handleAddToCart() method. Method: ' . $method);
        if ($method !== 'POST') {
            $this->logMessage('Method Not Allowed for add-to-cart: ' . $method, 'error');
            return $this->errorResponse('Method Not Allowed', 405);
        }
        
        try {
            require_once(dirname(__FILE__).'/ExternalCartManager.php');
            $manager = new ExternalCartManager();
            $input = Tools::file_get_contents('php://input');
            $this->logMessage('Add to Cart Raw Input: ' . $input);
            $result = $manager->addOrUpdateCart(json_decode($input, true));
            $this->logMessage('Exiting handleAddToCart() method. Result: ' . json_encode($result));
            
            $this->output = json_encode($result);
            return $this->output;
        } catch (Exception $e) {
            $this->logMessage('Exception in handleAddToCart: ' . $e->getMessage(), 'error');
            return $this->errorResponse('Internal error: ' . $e->getMessage(), 500);
        }
    }

    protected function handleMakeReservation($method)
    {
        $this->logMessage('Entering handleMakeReservation() method. Method: ' . $method);
        if ($method !== 'POST') {
            $this->logMessage('Method Not Allowed for make-reservation: ' . $method, 'error');
            return $this->errorResponse('Method Not Allowed', 405);
        }
        
        try {
            require_once(dirname(__FILE__).'/ExternalReservationManager.php');
            $manager = new ExternalReservationManager();
            $input = Tools::file_get_contents('php://input');
            $this->logMessage('Make Reservation Raw Input: ' . $input);
            $result = $manager->makeReservation(json_decode($input, true));
            $this->logMessage('Exiting handleMakeReservation() method. Result: ' . json_encode($result));
            
            $this->output = json_encode($result);
            return $this->output;
        } catch (Exception $e) {
            $this->logMessage('Exception in handleMakeReservation: ' . $e->getMessage(), 'error');
            return $this->errorResponse('Internal error: ' . $e->getMessage(), 500);
        }
    }

    protected function handleBookingDetails($method, $params)
    {
        $this->logMessage('Entering handleBookingDetails() method. Method: ' . $method);
        if ($method !== 'GET') {
            $this->logMessage('Method Not Allowed for booking-details: ' . $method, 'error');
            return $this->errorResponse('Method Not Allowed', 405);
        }
        
        try {
            require_once(dirname(__FILE__).'/ExternalBookingManager.php');
            $manager = new ExternalBookingManager();
            $booking_id = isset($params['booking_id']) ? $params['booking_id'] : '';
            $this->logMessage('Booking ID: ' . $booking_id);
            $result = $manager->getBookingDetails($booking_id);
            $this->logMessage('Exiting handleBookingDetails() method. Result: ' . json_encode($result));
            
            $this->output = json_encode($result);
            return $this->output;
        } catch (Exception $e) {
            $this->logMessage('Exception in handleBookingDetails: ' . $e->getMessage(), 'error');
            return $this->errorResponse('Internal error: ' . $e->getMessage(), 500);
        }
    }

    protected function handleCustomerSignup($method)
    {
        $this->logMessage('Entering handleCustomerSignup() method. Method: ' . $method);
        if ($method !== 'POST') {
            $this->logMessage('Method Not Allowed for customer-signup: ' . $method, 'error');
            return $this->errorResponse('Method Not Allowed', 405);
        }
        
        try {
            require_once(dirname(__FILE__).'/ExternalCustomerManager.php');
            $manager = new ExternalCustomerManager();
            $input = Tools::file_get_contents('php://input');
            $this->logMessage('Customer Signup Raw Input: ' . $input);
            $decoded_input = json_decode($input, true);
            $_POST = $decoded_input; // Populate $_POST for PrestaShop internal functions
            $this->logMessage('$_POST (after decode): ' . json_encode($_POST));
            $this->logMessage('$_REQUEST: ' . json_encode($_REQUEST));
            $result = $manager->createCustomer($decoded_input);
            $this->logMessage('Exiting handleCustomerSignup() method. Result: ' . json_encode($result));
            
            $this->output = json_encode($result);
            return $this->output;
        } catch (Exception $e) {
            $this->logMessage('Exception in handleCustomerSignup: ' . $e->getMessage(), 'error');
            return $this->errorResponse('Internal error: ' . $e->getMessage(), 500);
        }
    }

    protected function handleCustomerLogin($method)
    {
        $this->logMessage('Entering handleCustomerLogin() method. Method: ' . $method);
        if ($method !== 'POST') {
            $this->logMessage('Method Not Allowed for customer-login: ' . $method, 'error');
            return $this->errorResponse('Method Not Allowed', 405);
        }
        
        try {
            require_once(dirname(__FILE__).'/ExternalCustomerLoginManager.php');
            $manager = new ExternalCustomerLoginManager();
            $input = Tools::file_get_contents('php://input');
            $this->logMessage('Customer Login Raw Input: ' . $input);
            $decoded_input = json_decode($input, true);
            $_POST = $decoded_input; // Populate $_POST for PrestaShop internal functions
            $this->logMessage('$_POST (after decode): ' . json_encode($_POST));
            $this->logMessage('$_REQUEST: ' . json_encode($_REQUEST));
            $result = $manager->loginCustomer($decoded_input);
            $this->logMessage('Exiting handleCustomerLogin() method. Result: ' . json_encode($result));
            
            $this->output = json_encode($result);
            return $this->output;
        } catch (Exception $e) {
            $this->logMessage('Exception in handleCustomerLogin: ' . $e->getMessage(), 'error');
            return $this->errorResponse('Internal error: ' . $e->getMessage(), 500);
        }
    }

    protected function handleCartDetails($method)
    {
        $this->logMessage('Entering handleCartDetails() method. Method: ' . $method);
        if ($method !== 'POST') {
            $this->logMessage('Method Not Allowed for cart-details: ' . $method, 'error');
            return $this->errorResponse('Method Not Allowed', 405);
        }
        
        try {
            require_once(dirname(__FILE__).'/ExternalCartDetailsManager.php');
            $manager = new ExternalCartDetailsManager();
            $input = Tools::file_get_contents('php://input');
            $this->logMessage('Cart Details Raw Input: ' . $input);
            $result = $manager->getCartDetails(json_decode($input, true));
            $this->logMessage('Exiting handleCartDetails() method. Result: ' . json_encode($result));
            
            $this->output = json_encode($result);
            return $this->output;
        } catch (Exception $e) {
            $this->logMessage('Exception in handleCartDetails: ' . $e->getMessage(), 'error');
            return $this->errorResponse('Internal error: ' . $e->getMessage(), 500);
        }
    }

    protected function handleEmptyCart($method)
    {
        $this->logMessage('Entering handleEmptyCart() method. Method: ' . $method);
        if ($method !== 'POST') {
            $this->logMessage('Method Not Allowed for empty-cart: ' . $method, 'error');
            return $this->errorResponse('Method Not Allowed', 405);
        }
        
        try {
            require_once(dirname(__FILE__).'/ExternalEmptyCartManager.php');
            $manager = new ExternalEmptyCartManager();
            $input = Tools::file_get_contents('php://input');
            $this->logMessage('Empty Cart Raw Input: ' . $input);
            $result = $manager->emptyCart(json_decode($input, true));
            $this->logMessage('Exiting handleEmptyCart() method. Result: ' . json_encode($result));
            
            $this->output = json_encode($result);
            return $this->output;
        } catch (Exception $e) {
            $this->logMessage('Exception in handleEmptyCart: ' . $e->getMessage(), 'error');
            return $this->errorResponse('Internal error: ' . $e->getMessage(), 500);
        }
    }

    protected function errorResponse($message, $httpStatus = 400)
    {
        $errorResponse = array(
            'success' => false,
            'error' => $message,
            'error_code' => 'API_ERROR',
            'timestamp' => date('c')
        );
        
        $this->output = json_encode($errorResponse);
        return $this->output;
    }

    public function getWsObject()
    {
        return $this->wsObject;
    }

    public function getObjectOutput()
    {
        return $this->objOutput;
    }

    public function setWsObject(WebserviceRequestCore $obj)
    {
        $this->wsObject = $obj;
        return $this;
    }

    public function setObjectOutput(WebserviceOutputBuilderCore $obj)
    {
        $this->objOutput = $obj;
        return $this;
    }

    protected function logMessage($message, $level = 'info')
    {
        $logDir = dirname(__FILE__).'/../logs/';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $logFile = $logDir.'debug.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp][$level] $message\n", FILE_APPEND);
    }
}