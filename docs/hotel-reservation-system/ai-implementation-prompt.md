# AI Assistant Implementation Prompt for Hotel Reservation External API

## Implementation Task Overview

You are tasked with implementing the External Hotel Reservation API endpoints based on the comprehensive analysis and specifications provided. This prompt will guide you through the step-by-step implementation of each endpoint with specific code examples and implementation patterns.

## Project Setup

### Working Directory
```
/Users/cristinaavila/Developer/Shockoe/booking-poc/booking-engine/
```

### Implementation Order
1. Create the new module structure
2. Implement the WebService integration
3. Implement each endpoint in order:
   - Availability Search (refactor existing)
   - Add to Cart
   - Make Reservation
   - Get Booking Details

## Step 1: Create Module Structure

### 1.1 Main Module File
**Create:** `modules/externalhotelreservationsystem/externalhotelreservationsystem.php`

```php
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
        $this->author = 'Hotel Booking API';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.6',
            'max' => _PS_VERSION_,
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('External Hotel Reservation API');
        $this->description = $this->l('Provides external REST API endpoints for hotel reservations.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function install()
    {
        return parent::install() && 
               $this->registerHook('addWebserviceResources') &&
               $this->createDatabaseTables();
    }

    public function uninstall()
    {
        return parent::uninstall() && $this->dropDatabaseTables();
    }

    public function hookAddWebserviceResources($params)
    {
        return [
            'external' => [
                'description' => 'External Hotel Reservation API',
                'specific_management' => true,
                'specific_management_class' => 'WebserviceSpecificManagementExternal',
            ],
        ];
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
                return false;
            }
        }
        
        return true;
    }
}
```

### 1.2 WebService Management Class
**Create:** `modules/externalhotelreservationsystem/classes/WebserviceSpecificManagementExternal.php`

```php
<?php

require_once(_PS_MODULE_DIR_.'hotelreservationsystem/define.php');

class WebserviceSpecificManagementExternal implements WebserviceSpecificManagementInterface
{
    protected $objOutput;
    protected $output;
    protected $wsObject;

    public function manage()
    {
        try {
            $method = $_SERVER['REQUEST_METHOD'];
            $pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
            
            // Parse route: /api/external/{endpoint}
            $pathParts = explode('/', trim($pathInfo, '/'));
            
            if (count($pathParts) < 3 || $pathParts[1] !== 'external') {
                return $this->errorResponse('Invalid API endpoint', 'INVALID_ENDPOINT', 404);
            }
            
            $endpoint = $pathParts[2];
            
            switch ($endpoint) {
                case 'availability':
                    return $this->handleAvailability($method);
                case 'add-to-cart':
                    return $this->handleAddToCart($method);
                case 'make-reservation':
                    return $this->handleMakeReservation($method);
                case 'booking':
                    return $this->handleBookingDetails($method, $pathParts);
                default:
                    return $this->errorResponse('Unknown endpoint: ' . $endpoint, 'UNKNOWN_ENDPOINT', 404);
            }
        } catch (Exception $e) {
            return $this->errorResponse('Internal server error: ' . $e->getMessage(), 'INTERNAL_ERROR', 500);
        }
    }

    private function handleAvailability($method)
    {
        if ($method !== 'GET') {
            return $this->errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }
        
        require_once(_PS_MODULE_DIR_.'externalhotelreservationsystem/classes/ExternalAvailabilityManager.php');
        $manager = new ExternalAvailabilityManager();
        return $manager->searchAvailability();
    }

    private function handleAddToCart($method)
    {
        if ($method !== 'POST') {
            return $this->errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }
        
        require_once(_PS_MODULE_DIR_.'externalhotelreservationsystem/classes/ExternalCartManager.php');
        $manager = new ExternalCartManager();
        return $manager->addToCart();
    }

    private function handleMakeReservation($method)
    {
        if ($method !== 'POST') {
            return $this->errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }
        
        require_once(_PS_MODULE_DIR_.'externalhotelreservationsystem/classes/ExternalReservationManager.php');
        $manager = new ExternalReservationManager();
        return $manager->makeReservation();
    }

    private function handleBookingDetails($method, $pathParts)
    {
        if ($method !== 'GET') {
            return $this->errorResponse('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
        }
        
        if (!isset($pathParts[3])) {
            return $this->errorResponse('Booking ID required', 'MISSING_BOOKING_ID', 400);
        }
        
        require_once(_PS_MODULE_DIR_.'externalhotelreservationsystem/classes/ExternalBookingManager.php');
        $manager = new ExternalBookingManager();
        return $manager->getBookingDetails($pathParts[3]);
    }

    private function errorResponse($message, $code = 'GENERAL_ERROR', $httpStatus = 400)
    {
        $response = array(
            'success' => false,
            'error' => $message,
            'error_code' => $code,
            'timestamp' => date('c')
        );
        
        http_response_code($httpStatus);
        $this->output = json_encode($response);
        return $this->output;
    }

    private function successResponse($data, $httpStatus = 200)
    {
        $response = array(
            'success' => true,
            'timestamp' => date('c')
        );
        
        $response = array_merge($response, $data);
        
        http_response_code($httpStatus);
        $this->output = json_encode($response);
        return $this->output;
    }

    // Required interface methods
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
```

## Step 2: Implement Availability Search Endpoint

**Create:** `modules/externalhotelreservationsystem/classes/ExternalAvailabilityManager.php`

```php
<?php

class ExternalAvailabilityManager
{
    public function searchAvailability()
    {
        try {
            $params = Tools::getAllValues();
            
            // Validate parameters
            $validation = $this->validateSearchParameters($params);
            if (!$validation['valid']) {
                return $this->errorResponse(implode(', ', $validation['errors']));
            }
            
            // Prepare search parameters using existing logic
            $searchParams = $this->prepareSearchParameters($params);
            
            // Use existing availability search logic
            $objBookingDetail = new HotelBookingDetail();
            $bookingData = $objBookingDetail->getBookingData($searchParams);
            
            // Format response with pricing information
            $responseData = $this->formatAvailabilityResponse($bookingData, $searchParams);
            
            return $this->successResponse($responseData);
            
        } catch (Exception $e) {
            return $this->errorResponse('Internal error: ' . $e->getMessage(), 'INTERNAL_ERROR');
        }
    }

    private function validateSearchParameters($params)
    {
        $errors = array();
        
        // Required parameters
        $hotelId = isset($params['hotel_id']) ? $params['hotel_id'] : '';
        $checkIn = isset($params['check_in']) ? $params['check_in'] : '';
        $checkOut = isset($params['check_out']) ? $params['check_out'] : '';
        
        if (empty($hotelId) || !Validate::isUnsignedInt($hotelId)) {
            $errors[] = 'hotel_id is required and must be a valid integer';
        }
        
        if (empty($checkIn) || !Validate::isDate($checkIn)) {
            $errors[] = 'check_in is required and must be in Y-m-d format';
        }
        
        if (empty($checkOut) || !Validate::isDate($checkOut)) {
            $errors[] = 'check_out is required and must be in Y-m-d format';
        }
        
        // Date logic validation
        if (!empty($checkIn) && !empty($checkOut)) {
            $currentDate = date('Y-m-d');
            if ($checkIn < $currentDate) {
                $errors[] = 'check_in cannot be in the past';
            }
            if ($checkOut <= $checkIn) {
                $errors[] = 'check_out must be after check_in';
            }
        }
        
        // Optional parameter validation
        if (isset($params['adults']) && (!Validate::isUnsignedInt($params['adults']) || $params['adults'] < 1)) {
            $errors[] = 'adults must be a positive integer';
        }
        
        if (isset($params['children']) && !Validate::isUnsignedInt($params['children'])) {
            $errors[] = 'children must be a non-negative integer';
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }

    private function prepareSearchParameters($params)
    {
        // Normalize dates
        $dateFrom = date('Y-m-d H:i:s', strtotime($params['check_in']));
        $dateTo = date('Y-m-d H:i:s', strtotime($params['check_out']));
        
        // Prepare occupancy
        $occupancy = array();
        if (isset($params['adults']) || isset($params['children'])) {
            $adults = isset($params['adults']) ? (int)$params['adults'] : 2;
            $children = isset($params['children']) ? (int)$params['children'] : 0;
            $occupancy[] = array(
                'adults' => $adults,
                'children' => $children,
                'child_ages' => array()
            );
        }
        
        return array(
            'hotel_id' => (int)$params['hotel_id'],
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'id_room_type' => isset($params['room_type']) ? (int)$params['room_type'] : 0,
            'occupancy' => $occupancy,
            'search_available' => 1,
            'search_partial' => 0,
            'search_booked' => 0,
            'search_unavai' => 0,
            'search_cart_rms' => 0,
            'only_search_data' => 0,
            'only_active_roomtype' => 1,
            'only_active_hotel' => 1
        );
    }

    private function formatAvailabilityResponse($bookingData, $searchParams)
    {
        $responseData = array(
            'search_criteria' => array(
                'hotel_id' => $searchParams['hotel_id'],
                'check_in' => date('Y-m-d', strtotime($searchParams['date_from'])),
                'check_out' => date('Y-m-d', strtotime($searchParams['date_to'])),
                'occupancy' => $searchParams['occupancy'],
                'room_type' => $searchParams['id_room_type']
            ),
            'hotels' => array()
        );
        
        if ($bookingData && isset($bookingData['stats']['num_avail']) && $bookingData['stats']['num_avail'] > 0) {
            // Get hotel information
            $objHotelBranchInformation = new HotelBranchInformation();
            $hotel = $objHotelBranchInformation->hotelBranchInfoById($searchParams['hotel_id']);
            
            $hotelData = array(
                'id_hotel' => $hotel['id'],
                'hotel_name' => $hotel['hotel_name'],
                'email' => isset($hotel['email']) ? $hotel['email'] : '',
                'check_in' => isset($hotel['check_in']) ? $hotel['check_in'] : '',
                'check_out' => isset($hotel['check_out']) ? $hotel['check_out'] : '',
                'rating' => isset($hotel['rating']) ? (int)$hotel['rating'] : 0,
                'available_rooms' => array()
            );
            
            // Process available rooms with pricing
            if (isset($bookingData['rm_data']) && is_array($bookingData['rm_data'])) {
                foreach ($bookingData['rm_data'] as $idProduct => $roomTypeData) {
                    if (isset($roomTypeData['data']['available'])) {
                        foreach ($roomTypeData['data']['available'] as $idRoom => $roomData) {
                            // Calculate pricing for this room type and date range
                            $priceData = HotelRoomTypeFeaturePricing::getRoomTypeTotalPrice(
                                $idProduct,
                                $searchParams['date_from'],
                                $searchParams['date_to']
                            );
                            
                            $availableRoom = array(
                                'id_room' => $roomData['id_room'],
                                'id_product' => $roomData['id_product'],
                                'id_room_type' => $roomData['id_product'],
                                'room_type_name' => $roomTypeData['name'],
                                'room_num' => $roomData['room_num'],
                                'room_comment' => isset($roomData['room_comment']) ? $roomData['room_comment'] : '',
                                'adults' => $roomTypeData['adults'],
                                'children' => $roomTypeData['children'],
                                'max_adults' => isset($roomTypeData['max_adults']) ? $roomTypeData['max_adults'] : $roomTypeData['adults'],
                                'max_children' => isset($roomTypeData['max_children']) ? $roomTypeData['max_children'] : $roomTypeData['children'],
                                'max_guests' => $roomTypeData['max_guests'],
                                'max_occupancy' => $roomTypeData['max_guests'],
                                'price' => array(
                                    'base_price' => $priceData['total_price_tax_excl'],
                                    'tax_included' => $priceData['total_price_tax_incl'],
                                    'currency' => Context::getContext()->currency->iso_code
                                )
                            );
                            
                            $hotelData['available_rooms'][] = $availableRoom;
                        }
                    }
                }
            }
            
            $responseData['hotels'][] = $hotelData;
            $responseData['total_available_rooms'] = count($hotelData['available_rooms']);
        } else {
            $responseData['total_available_rooms'] = 0;
            $responseData['message'] = 'No available rooms found for the specified criteria';
        }
        
        return $responseData;
    }

    private function errorResponse($message, $code = 'AVAILABILITY_ERROR', $httpStatus = 400)
    {
        $response = array(
            'success' => false,
            'error' => $message,
            'error_code' => $code,
            'timestamp' => date('c')
        );
        
        http_response_code($httpStatus);
        return json_encode($response);
    }

    private function successResponse($data, $httpStatus = 200)
    {
        $response = array(
            'success' => true,
            'timestamp' => date('c')
        );
        
        $response = array_merge($response, $data);
        
        http_response_code($httpStatus);
        return json_encode($response);
    }
}
```

## Step 3: Implement Add to Cart Endpoint

**Create:** `modules/externalhotelreservationsystem/classes/ExternalCartManager.php`

```php
<?php

class ExternalCartManager
{
    public function addToCart()
    {
        try {
            // Get JSON input for POST request
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                return $this->errorResponse('Invalid JSON input', 'INVALID_JSON');
            }
            
            // Validate required parameters
            $validation = $this->validateCartParameters($input);
            if (!$validation['valid']) {
                return $this->errorResponse(implode(', ', $validation['errors']));
            }
            
            // Check room availability before adding to cart
            $availabilityCheck = $this->checkRoomAvailability($input);
            if (!$availabilityCheck['available']) {
                return $this->errorResponse($availabilityCheck['message'], 'ROOM_NOT_AVAILABLE');
            }
            
            // Create or get customer
            $customerId = $this->getOrCreateCustomer($input);
            
            // Create or get cart
            $cartId = $this->getOrCreateCart($customerId);
            
            // Add room to hotel cart
            $cartBookingResult = $this->addRoomToHotelCart($cartId, $customerId, $input);
            if (!$cartBookingResult['success']) {
                return $this->errorResponse($cartBookingResult['message'], 'CART_ADD_FAILED');
            }
            
            // Update PrestaShop cart
            $this->updatePrestaShopCart($cartId, $input['id_product']);
            
            // Generate cart token
            $cartToken = $this->generateCartToken($cartId, $customerId);
            
            // Prepare response
            $responseData = $this->formatCartResponse($cartId, $customerId, $input, $cartToken);
            
            return $this->successResponse($responseData, 201);
            
        } catch (Exception $e) {
            return $this->errorResponse('Internal error: ' . $e->getMessage(), 'INTERNAL_ERROR');
        }
    }

    private function validateCartParameters($input)
    {
        $errors = array();
        
        $required = array('hotel_id', 'room_id', 'check_in', 'check_out', 'adults');
        foreach ($required as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                $errors[] = $field . ' is required';
            }
        }
        
        if (isset($input['hotel_id']) && !Validate::isUnsignedInt($input['hotel_id'])) {
            $errors[] = 'hotel_id must be a valid integer';
        }
        
        if (isset($input['room_id']) && !Validate::isUnsignedInt($input['room_id'])) {
            $errors[] = 'room_id must be a valid integer';
        }
        
        if (isset($input['check_in']) && !Validate::isDate($input['check_in'])) {
            $errors[] = 'check_in must be in Y-m-d format';
        }
        
        if (isset($input['check_out']) && !Validate::isDate($input['check_out'])) {
            $errors[] = 'check_out must be in Y-m-d format';
        }
        
        if (isset($input['adults']) && (!Validate::isUnsignedInt($input['adults']) || $input['adults'] < 1)) {
            $errors[] = 'adults must be a positive integer';
        }
        
        if (isset($input['children']) && !Validate::isUnsignedInt($input['children'])) {
            $errors[] = 'children must be a non-negative integer';
        }
        
        if (isset($input['customer_email']) && !Validate::isEmail($input['customer_email'])) {
            $errors[] = 'customer_email must be a valid email address';
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }

    private function checkRoomAvailability($input)
    {
        // Get room information
        $objRoomInfo = new HotelRoomInformation($input['room_id']);
        if (!$objRoomInfo->id) {
            return array('available' => false, 'message' => 'Room not found');
        }
        
        // Check if room is active
        if ($objRoomInfo->id_status != HotelRoomInformation::STATUS_ACTIVE) {
            return array('available' => false, 'message' => 'Room is not active');
        }
        
        // Check for existing bookings
        $objBookingDetail = new HotelBookingDetail();
        $existingBookings = $objBookingDetail->getBookingsByRoomAndDateRange(
            $input['room_id'],
            $input['check_in'],
            $input['check_out']
        );
        
        if (!empty($existingBookings)) {
            return array('available' => false, 'message' => 'Room is already booked for these dates');
        }
        
        // Get product ID for this room
        $objRoomType = new HotelRoomType();
        $roomTypeInfo = $objRoomType->getRoomTypeInfoByIdRoom($input['room_id']);
        $input['id_product'] = $roomTypeInfo['id_product'];
        
        return array('available' => true, 'message' => 'Room is available');
    }

    private function getOrCreateCustomer($input)
    {
        if (isset($input['customer_id']) && Validate::isUnsignedInt($input['customer_id'])) {
            $customer = new Customer($input['customer_id']);
            if ($customer->id) {
                return $customer->id;
            }
        }
        
        // Create guest customer if email provided
        if (isset($input['customer_email'])) {
            $email = $input['customer_email'];
            
            // Check if customer already exists
            $existingCustomerId = Customer::customerExists($email, true);
            if ($existingCustomerId) {
                return $existingCustomerId;
            }
            
            // Create new customer
            $customer = new Customer();
            $customer->email = $email;
            $customer->firstname = isset($input['first_name']) ? $input['first_name'] : 'Guest';
            $customer->lastname = isset($input['last_name']) ? $input['last_name'] : 'User';
            $customer->passwd = Tools::passwdGen();
            $customer->is_guest = 1;
            
            if ($customer->save()) {
                return $customer->id;
            }
        }
        
        return 0; // Guest checkout
    }

    private function getOrCreateCart($customerId)
    {
        $context = Context::getContext();
        
        if ($customerId > 0) {
            // Get existing cart for customer
            $cartId = Cart::lastNonOrderedCart($customerId);
            if ($cartId) {
                $cart = new Cart($cartId);
                if ($cart->id) {
                    return $cart->id;
                }
            }
        }
        
        // Create new cart
        $cart = new Cart();
        $cart->id_customer = $customerId;
        $cart->id_address_delivery = 0;
        $cart->id_address_invoice = 0;
        $cart->id_lang = $context->language->id;
        $cart->id_currency = $context->currency->id;
        $cart->id_carrier = 0;
        $cart->recyclable = 0;
        $cart->gift = 0;
        
        if ($cart->save()) {
            return $cart->id;
        }
        
        throw new Exception('Failed to create cart');
    }

    private function addRoomToHotelCart($cartId, $customerId, $input)
    {
        try {
            $objCartBookingData = new HotelCartBookingData();
            
            $cartBookingData = array(
                'id_cart' => $cartId,
                'id_customer' => $customerId,
                'id_product' => $input['id_product'],
                'id_room' => $input['room_id'],
                'id_hotel' => $input['hotel_id'],
                'room_num' => $this->getRoomNumber($input['room_id']),
                'date_from' => date('Y-m-d H:i:s', strtotime($input['check_in'])),
                'date_to' => date('Y-m-d H:i:s', strtotime($input['check_out'])),
                'adults' => $input['adults'],
                'children' => isset($input['children']) ? $input['children'] : 0,
                'child_ages' => isset($input['child_ages']) ? json_encode($input['child_ages']) : json_encode(array()),
                'extra_demands' => isset($input['extra_demands']) ? json_encode($input['extra_demands']) : json_encode(array()),
                'is_refunded' => 0,
                'is_back_order' => 0
            );
            
            $result = $objCartBookingData->addCartBookingData($cartBookingData);
            
            return array('success' => true, 'cart_booking_id' => $result);
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Failed to add room to hotel cart: ' . $e->getMessage());
        }
    }

    private function updatePrestaShopCart($cartId, $productId)
    {
        $cart = new Cart($cartId);
        $cart->updateQty(1, $productId, null, false, 'up', 0, null, true);
    }

    private function generateCartToken($cartId, $customerId)
    {
        $token = 'cart_' . $cartId . '_' . uniqid() . '_' . $customerId;
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $sql = 'INSERT INTO `'._DB_PREFIX_.'htl_external_cart_tokens` 
                (cart_token, id_cart, id_customer, expires_at) 
                VALUES ("'.pSQL($token).'", '.(int)$cartId.', '.(int)$customerId.', "'.pSQL($expiresAt).'")';
        
        Db::getInstance()->execute($sql);
        
        return $token;
    }

    private function getRoomNumber($roomId)
    {
        $objRoomInfo = new HotelRoomInformation($roomId);
        return $objRoomInfo->room_num;
    }

    private function formatCartResponse($cartId, $customerId, $input, $cartToken)
    {
        // Get hotel information
        $objHotel = new HotelBranchInformation($input['hotel_id']);
        
        // Get room information
        $objRoomInfo = new HotelRoomInformation($input['room_id']);
        $objRoomType = new HotelRoomType();
        $roomTypeInfo = $objRoomType->getRoomTypeInfoByIdRoom($input['room_id']);
        
        // Calculate pricing
        $priceData = HotelRoomTypeFeaturePricing::getRoomTypeTotalPrice(
            $input['id_product'],
            $input['check_in'],
            $input['check_out']
        );
        
        $nights = (strtotime($input['check_out']) - strtotime($input['check_in'])) / (24 * 60 * 60);
        
        $extraDemandsTotal = 0;
        $extraDemands = array();
        if (isset($input['extra_demands']) && is_array($input['extra_demands'])) {
            foreach ($input['extra_demands'] as $demand) {
                $extraDemandsTotal += isset($demand['total_price']) ? $demand['total_price'] : 0;
                $extraDemands[] = $demand;
            }
        }
        
        return array(
            'cart_id' => $cartToken,
            'customer_id' => $customerId,
            'booking_details' => array(
                'hotel' => array(
                    'id_hotel' => $objHotel->id,
                    'hotel_name' => $objHotel->hotel_name
                ),
                'room' => array(
                    'id_room' => $objRoomInfo->id,
                    'room_num' => $objRoomInfo->room_num,
                    'room_type_name' => $roomTypeInfo['name']
                ),
                'dates' => array(
                    'check_in' => $input['check_in'],
                    'check_out' => $input['check_out'],
                    'nights' => $nights
                ),
                'occupancy' => array(
                    'adults' => $input['adults'],
                    'children' => isset($input['children']) ? $input['children'] : 0,
                    'child_ages' => isset($input['child_ages']) ? $input['child_ages'] : array()
                ),
                'pricing' => array(
                    'room_total' => $priceData['total_price_tax_excl'],
                    'extra_demands_total' => $extraDemandsTotal,
                    'tax_amount' => $priceData['total_price_tax_incl'] - $priceData['total_price_tax_excl'],
                    'total_tax_included' => $priceData['total_price_tax_incl'] + $extraDemandsTotal,
                    'currency' => Context::getContext()->currency->iso_code
                ),
                'extra_demands' => $extraDemands
            ),
            'cart_token' => $cartToken,
            'expires_at' => date('c', strtotime('+1 hour'))
        );
    }

    private function errorResponse($message, $code = 'CART_ERROR', $httpStatus = 400)
    {
        $response = array(
            'success' => false,
            'error' => $message,
            'error_code' => $code,
            'timestamp' => date('c')
        );
        
        http_response_code($httpStatus);
        return json_encode($response);
    }

    private function successResponse($data, $httpStatus = 200)
    {
        $response = array(
            'success' => true,
            'timestamp' => date('c')
        );
        
        $response = array_merge($response, $data);
        
        http_response_code($httpStatus);
        return json_encode($response);
    }
}
```

## Step 4: Implementation Instructions for Remaining Endpoints

### For Make Reservation Endpoint (`ExternalReservationManager.php`)
1. **Validate cart token** and retrieve cart data
2. **Process mock payment** using a simple payment processor
3. **Create PrestaShop order** from the cart
4. **Convert cart bookings to hotel bookings** using `HotelBookingDetail`
5. **Send confirmation email** using PrestaShop's mail system
6. **Generate booking ID** and store in external references table
7. **Return comprehensive booking details**

### For Get Booking Details Endpoint (`ExternalBookingManager.php`)
1. **Validate booking ID** format and existence
2. **Retrieve order information** from external references table
3. **Get hotel booking details** from `htl_booking_detail` table
4. **Compile complete booking information** including hotel, room, customer, pricing
5. **Format response** with current booking status and history
6. **Include cancellation policy information**

## Key Implementation Notes

### Database Queries
- Always use PrestaShop's `Db::getInstance()` for database operations
- Use `pSQL()` for string sanitization
- Use parameterized queries where possible

### Error Handling
- Catch all exceptions and return proper JSON error responses
- Use appropriate HTTP status codes
- Include detailed error messages for debugging

### Integration Points
- **Always use existing classes** like `HotelBookingDetail`, `HotelCartBookingData`
- **Never modify existing code** - only extend functionality
- **Maintain PrestaShop conventions** for cart and order management
- **Use existing validation methods** from PrestaShop and hotel modules

### Testing Strategy
1. **Test each endpoint individually** with valid and invalid parameters
2. **Verify backward compatibility** - ensure existing system still works
3. **Test complete booking flow** from availability to confirmation
4. **Validate database consistency** after each operation
5. **Test error scenarios** and edge cases

### Security Considerations
- **Validate all input parameters** thoroughly
- **Sanitize database inputs** using PrestaShop methods
- **Implement rate limiting** if needed for production
- **Log API access** for monitoring and debugging

This implementation guide provides the foundational structure and detailed code examples for building the external hotel reservation API while maintaining full compatibility with the existing system.