<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(dirname(__FILE__).'/ExternalApiValidator.php');

class ExternalCartManager
{
    public function addOrUpdateCart($params)
    {
        try {
            // Validation
            if (empty($params['hotel_id'])) {
                throw new InvalidArgumentException('hotel_id is required');
            }
            if (empty($params['room_id'])) {
                throw new InvalidArgumentException('room_id is required');
            }
            if (empty($params['check_in'])) {
                throw new InvalidArgumentException('check_in is required');
            }
            if (empty($params['check_out'])) {
                throw new InvalidArgumentException('check_out is required');
            }
            if (empty($params['adults'])) {
                throw new InvalidArgumentException('adults is required');
            }

            ExternalApiValidator::validateDateRange($params['check_in'], $params['check_out']);
            ExternalApiValidator::validateOccupancy($params['adults'], isset($params['children']) ? $params['children'] : 0);

            // Load required classes (PrestaShop config should already be loaded)
            require_once(_PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelCartBookingData.php');
            require_once(_PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelRoomType.php');
            require_once(_PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelBranchInformation.php');
            require_once(_PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelRoomTypeFeaturePricing.php');
            require_once(_PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelHelper.php');
            require_once(_PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelRoomInformation.php');
            require_once(dirname(__FILE__).'/ExternalRoomLockManager.php');

            $context = Context::getContext();
            
            // Ensure context has currency set (may be null in webservice calls)
            if (!$context->currency) {
                $context->currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
            }

            // Create a new cart if one doesn't exist
            if (!$context->cart->id) {
                if (Context::getContext()->cookie->id_guest)
                {
                    $guest = new Guest(Context::getContext()->cookie->id_guest);
                    $context->cart->mobile_theme = $guest->mobile_theme;
                }
                // Set currency for the cart
                $context->cart->id_currency = $context->currency->id;
                $context->cart->add();
                if ($context->cart->id)
                    $context->cookie->id_cart = (int)$context->cart->id;
            }

            // Get customer
            $customer = null;
            if (!empty($params['customer_id'])) {
                $customer = new Customer((int)$params['customer_id']);
            }
            if (!Validate::isLoadedObject($customer) && !empty($params['customer_email'])) {
                $customer = Customer::getCustomersByEmail($params['customer_email']);
                if (!empty($customer)) {
                    $customer = new Customer((int)$customer[0]['id_customer']);
                } else {
                    $customer = new Customer();
                    $customer->email = $params['customer_email'];
                    $customer->firstname = 'Guest';
                    $customer->lastname = 'Guest';
                    $customer->passwd = Tools::encrypt(Tools::passwdGen());
                    $customer->add();
                }
            }
            if (Validate::isLoadedObject($customer)) {
                $context->customer = $customer;
                $context->cart->id_customer = $customer->id;
                $context->cart->secure_key = $customer->secure_key;
                $context->cart->update();
            }

            $objBooking = new HotelCartBookingData();

            $occupancy = array(
                array(
                    'adults' => $params['adults'],
                    'children' => isset($params['children']) ? $params['children'] : 0,
                    'child_ages' => isset($params['child_ages']) ? $params['child_ages'] : [],
                )
            );

            $roomDemand = isset($params['extra_demands']) ? json_encode($params['extra_demands']) : '';

            // REAL-TIME ROOM AVAILABILITY VALIDATION
            // Check if room is currently locked by any booking process (admin, frontend, other API calls)
            $lockCheck = ExternalRoomLockManager::checkRoomLock(
                $params['room_id'], 
                $params['check_in'], 
                $params['check_out']
            );
            
            if ($lockCheck['is_locked']) {
                throw new Exception('Room is currently unavailable: ' . $lockCheck['message']);
            }
            
            // Lock the room to prevent race conditions
            $lockResult = ExternalRoomLockManager::lockRoom(
                $params['room_id'],
                $params['check_in'],
                $params['check_out'],
                'external_api',
                'api_key_' . (isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : 'unknown')
            );
            
            if (!$lockResult['success']) {
                throw new Exception('Unable to secure room booking: ' . $lockResult['error']);
            }
            
            $roomLockId = $lockResult['lock_id'];
            
            try {
                $objRoom = new HotelRoomInformation($params['room_id']);

                $add_result = $objBooking->addCartBookingData(
                    $objRoom->id_product,
                    $occupancy,
                    $params['hotel_id'],
                    $params['check_in'],
                    $params['check_out'],
                    $roomDemand,
                    [],
                    [['id_room' => $params['room_id']]],
                    $context->cart->id
                );
                
                if (!$add_result) {
                    // Release lock if cart addition fails
                    ExternalRoomLockManager::releaseLock($roomLockId);
                    throw new Exception('Failed to add room to cart');
                }
                
                // Keep lock active until cart timeout or conversion to order
                // Lock will auto-expire based on LOCK_DURATION
                
            } catch (Exception $e) {
                // Release lock on any error
                ExternalRoomLockManager::releaseLock($roomLockId);
                throw $e;
            }

            // Generate a simple cart token based on cart ID and timestamp for now
            $cart_token = md5($context->cart->id . time());

            $response = $this->formatResponse($params, $context, $cart_token, $roomLockId);

            return $response;

        } catch (InvalidArgumentException $e) {
            error_log('ExternalCartManager InvalidArgumentException: ' . $e->getMessage());
            return array('success' => false, 'error' => $e->getMessage(), 'error_code' => 'INVALID_PARAMETER');
        } catch (Exception $e) {
            error_log('ExternalCartManager Exception: ' . $e->getMessage());
            return array('success' => false, 'error' => 'An unexpected error occurred: '.$e->getMessage(), 'error_code' => 'INTERNAL_ERROR');
        }
    }

    private function formatResponse($params, $context, $cart_token, $roomLockId = null)
    {
        $hotelInfo = (new HotelBranchInformation())->hotelBranchInfoById((int)$params['hotel_id']);
        $roomTypeInfo = (new HotelRoomType())->getRoomTypeInfoByIdProduct((new HotelRoomInformation($params['room_id']))->id_product);

        $roomPrice = HotelRoomTypeFeaturePricing::getRoomTypeTotalPrice(
            $roomTypeInfo['id_product'],
            $params['check_in'],
            $params['check_out']
        );

        $response = array(
            'success' => true,
            'timestamp' => date('c'),
            'cart_id' => $context->cart->id,
            'customer_id' => $context->customer->id,
            'booking_details' => array(
                'hotel' => array(
                    'id_hotel' => (int)$params['hotel_id'],
                    'hotel_name' => $hotelInfo['hotel_name'],
                ),
                'room' => array(
                    'id_room' => (int)$params['room_id'],
                    'room_num' => (new HotelRoomInformation($params['room_id']))->room_num,
                    'room_type_name' => isset($roomTypeInfo['room_type_name']) ? $roomTypeInfo['room_type_name'] : '',
                ),
                'dates' => array(
                    'check_in' => $params['check_in'],
                    'check_out' => $params['check_out'],
                    'nights' => HotelHelper::getNumberOfDays($params['check_in'], $params['check_out']),
                ),
                'occupancy' => array(
                    'adults' => (int)$params['adults'],
                    'children' => isset($params['children']) ? (int)$params['children'] : 0,
                    'child_ages' => isset($params['child_ages']) ? $params['child_ages'] : [],
                ),
                'pricing' => array(
                    'room_total' => $roomPrice['total_price_tax_excl'],
                    'extra_demands_total' => 0, // To be implemented
                    'tax_amount' => $roomPrice['total_price_tax_incl'] - $roomPrice['total_price_tax_excl'],
                    'total_tax_included' => $roomPrice['total_price_tax_incl'],
                    'currency' => $context->currency->iso_code,
                ),
                'extra_demands' => [], // To be implemented
            ),
            'cart_token' => $cart_token,
            'expires_at' => date('c', strtotime('+1 hour')),
        );
        
        // Add room lock information for tracking
        if ($roomLockId) {
            $response['room_lock'] = array(
                'lock_id' => $roomLockId,
                'locked_until' => date('c', time() + ExternalRoomLockManager::LOCK_DURATION),
                'message' => 'Room is temporarily reserved for your booking'
            );
        }
        
        return $response;
    }
}
