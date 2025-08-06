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
            if (empty($params['customer_id'])) {
                throw new InvalidArgumentException('customer_id is required');
            }
            if (empty($params['secure_key'])) {
                throw new InvalidArgumentException('secure_key is required');
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

            // Validate customer
            $customer = new Customer((int)$params['customer_id']);
            if (!Validate::isLoadedObject($customer) || $customer->secure_key !== $params['secure_key']) {
                throw new Exception('Invalid customer ID or secure key.');
            }

            // Set customer in context
            $context->customer = $customer;
            $context->cookie->id_customer = (int)$customer->id;
            $context->cookie->customer_lastname = $customer->lastname;
            $context->cookie->customer_firstname = $customer->firstname;
            $context->cookie->logged = 1;
            $context->cookie->passwd = $customer->passwd;
            $context->cookie->email = $customer->email;
            $context->cookie->is_guest = $customer->is_guest;
            $context->cookie->write();

            // Load or create cart for the validated customer
            $id_cart = (int)Db::getInstance()->getValue(
                'SELECT id_cart FROM '._DB_PREFIX_.'cart WHERE id_customer = '.(int)$customer->id.' ORDER BY date_add DESC'
            );
            error_log('ExternalCartManager: Initial id_cart from DB for customer ' . $customer->id . ': ' . $id_cart);

            $cart_is_valid = false;
            if ($id_cart) {
                $temp_cart = new Cart($id_cart);
                error_log('ExternalCartManager: Loaded temp_cart ID: ' . $temp_cart->id . ' id_order: ' . (int)Order::getOrderByCartId($temp_cart->id));
                // Check if the cart is loaded and not already associated with an order
                if (Validate::isLoadedObject($temp_cart) && !Order::getOrderByCartId($temp_cart->id)) {
                    $context->cart = $temp_cart;
                    $cart_is_valid = true;
                }
            }

            if (!$cart_is_valid || $context->cart->id_customer != $customer->id) {
                $context->cart = new Cart();
                $context->cart->id_shop_group = (int)$context->shop->id_shop_group;
                $context->cart->id_shop = (int)$context->shop->id;
                $context->cart->id_customer = (int)$customer->id;
                $context->cart->id_currency = (int)$context->currency->id;
                $context->cart->id_lang = (int)$context->language->id;
                $context->cart->secure_key = $customer->secure_key;
                $context->cart->add();
                error_log('ExternalCartManager: Created new cart with ID: ' . $context->cart->id);
            }
            $context->cart->update();

            error_log('ExternalCartManager: Cart ID before CART_ALREADY_FULL check: ' . $context->cart->id);
            // Enforce single booking per cart rule
            $cartBookingData = new HotelCartBookingData();
            $cart_has_bookings = $cartBookingData->getCartCurrentDataByCartId($context->cart->id);
            error_log('ExternalCartManager: Result of getCartCurrentDataByCartId: ' . ($cart_has_bookings ? 'true' : 'false'));
            if ($cart_has_bookings) {
                return [
                    'success' => false,
                    'error' => 'Your cart already contains a booking. Only one room can be booked per order.',
                    'error_code' => 'CART_ALREADY_FULL'
                ];
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

        // Acquire lock before checking availability and adding to cart
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
            
            // Release lock immediately after successful cart addition
            ExternalRoomLockManager::releaseLock($roomLockId);
            
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
        $cart_bookings = (new HotelCartBookingData())->getCartCurrentDataByCartId($context->cart->id);
        $booking_details = [];

        foreach ($cart_bookings as $booking) {
            $hotelInfo = (new HotelBranchInformation())->hotelBranchInfoById((int)$booking['id_hotel']);
            $roomInfo = new HotelRoomInformation((int)$booking['id_room']);
            $roomTypeInfo = (new HotelRoomType())->getRoomTypeInfoByIdProduct($roomInfo->id_product);

            $roomPrice = HotelRoomTypeFeaturePricing::getRoomTypeTotalPrice(
                $roomInfo->id_product,
                $booking['date_from'],
                $booking['date_to']
            );

            $booking_details[] = array(
                'hotel' => array(
                    'id_hotel' => (int)$booking['id_hotel'],
                    'hotel_name' => $hotelInfo['hotel_name'],
                ),
                'room' => array(
                    'id_room' => (int)$booking['id_room'],
                    'room_num' => $roomInfo->room_num,
                    'room_type_name' => isset($roomTypeInfo['room_type_name']) ? $roomTypeInfo['room_type_name'] : '',
                ),
                'dates' => array(
                    'check_in' => $booking['date_from'],
                    'check_out' => $booking['date_to'],
                    'nights' => HotelHelper::getNumberOfDays($booking['date_from'], $booking['date_to']),
                ),
                'occupancy' => array(
                    'adults' => (int)$booking['adults'],
                    'children' => (int)$booking['children'],
                    'child_ages' => json_decode($booking['child_ages']),
                ),
                'pricing' => array(
                    'room_total' => $roomPrice['total_price_tax_excl'],
                    'extra_demands_total' => 0, // To be implemented
                    'tax_amount' => $roomPrice['total_price_tax_incl'] - $roomPrice['total_price_tax_excl'],
                    'total_tax_included' => $roomPrice['total_price_tax_incl'],
                    'currency' => $context->currency->iso_code,
                ),
                'extra_demands' => json_decode($booking['extra_demands']), // To be implemented
            );
        }

        $response = array(
            'success' => true,
            'timestamp' => date('c'),
            'cart_id' => $context->cart->id,
            'customer_id' => $context->customer->id,
            'booking_details' => $booking_details,
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
