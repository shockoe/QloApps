<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(dirname(__FILE__).'/ExternalApiValidator.php');

class ExternalCartDetailsManager
{
    public function getCartDetails($params)
    {
        try {
            // 1. Input Validation
            if (empty($params['cart_id'])) {
                throw new InvalidArgumentException('cart_id is required');
            }
            if (empty($params['customer_id'])) {
                throw new InvalidArgumentException('customer_id is required');
            }
            if (empty($params['secure_key'])) {
                throw new InvalidArgumentException('secure_key is required');
            }

            // Load required classes
            require_once(_PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelCartBookingData.php');
            require_once(_PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelRoomType.php');
            require_once(_PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelBranchInformation.php');
            require_once(_PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelRoomTypeFeaturePricing.php');
            require_once(_PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelHelper.php');
            require_once(_PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelRoomInformation.php');

            $context = Context::getContext();
            
            // Ensure context has currency set
            if (!$context->currency) {
                $context->currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
            }

            // 2. Security Validation
            $customer = new Customer((int)$params['customer_id']);
            if (!Validate::isLoadedObject($customer) || $customer->secure_key !== $params['secure_key']) {
                throw new Exception('Invalid customer ID or secure key.');
            }

            $cart = new Cart((int)$params['cart_id']);
            if (!Validate::isLoadedObject($cart) || (int)$cart->id_customer !== (int)$customer->id) {
                throw new Exception('Cart not found or does not belong to the provided customer.');
            }
            
            // Set customer and cart in context for consistency
            $context->customer = $customer;
            $context->cart = $cart;

            // 3. Data Retrieval and Formatting
            $response = $this->formatResponse($context);

            return $response;

        } catch (InvalidArgumentException $e) {
            error_log('ExternalCartDetailsManager InvalidArgumentException: ' . $e->getMessage());
            return array('success' => false, 'error' => $e->getMessage(), 'error_code' => 'INVALID_PARAMETER');
        } catch (Exception $e) {
            error_log('ExternalCartDetailsManager Exception: ' . $e->getMessage());
            return array('success' => false, 'error' => 'An unexpected error occurred: '.$e->getMessage(), 'error_code' => 'INTERNAL_ERROR');
        }
    }

    private function formatResponse($context)
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

            $tax_amount = $roomPrice['total_price_tax_incl'] - $roomPrice['total_price_tax_excl'];

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
                    'tax_amount' => $tax_amount,
                    'total_tax_included' => $roomPrice['total_price_tax_incl'],
                    'currency' => $context->currency->iso_code,
                ),
                'extra_demands' => json_decode($booking['extra_demands']),
            );
        }

        $response = array(
            'success' => true,
            'timestamp' => date('c'),
            'cart_id' => $context->cart->id,
            'customer_id' => $context->customer->id,
            'booking_details' => $booking_details,
        );
        
        return $response;
    }
}