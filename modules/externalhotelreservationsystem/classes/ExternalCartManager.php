<?php
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

            // Include PrestaShop config and required classes
            require_once(dirname(__FILE__).'/../../../config/config.inc.php');
            require_once(dirname(__FILE__).'/../../hotelreservationsystem/classes/HotelCartBookingData.php');
            require_once(dirname(__FILE__).'/../../hotelreservationsystem/classes/HotelRoomType.php');
            require_once(dirname(__FILE__).'/../../hotelreservationsystem/classes/HotelBranchInformation.php');
            require_once(dirname(__FILE__).'/../../hotelreservationsystem/classes/HotelRoomTypeFeaturePricing.php');
            require_once(dirname(__FILE__).'/../../hotelreservationsystem/classes/HotelHelper.php');

            $context = Context::getContext();

            // Create a new cart if one doesn't exist
            if (!$context->cart->id) {
                if (Context::getContext()->cookie->id_guest)
                {
                    $guest = new Guest(Context::getContext()->cookie->id_guest);
                    $context->cart->mobile_theme = $guest->mobile_theme;
                }
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
                throw new Exception('Failed to add room to cart');
            }

            $cart_token = bin2hex(random_bytes(32));
            Db::getInstance()->insert('htl_external_cart_tokens', array(
                'cart_token' => $cart_token,
                'id_cart' => (int)$context->cart->id,
                'id_customer' => (int)$context->customer->id,
                'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            ));

            $response = $this->formatResponse($params, $context, $cart_token);

            return $response;

        } catch (InvalidArgumentException $e) {
            error_log('ExternalCartManager InvalidArgumentException: ' . $e->getMessage());
            return array('success' => false, 'error' => $e->getMessage(), 'error_code' => 'INVALID_PARAMETER');
        } catch (Exception $e) {
            error_log('ExternalCartManager Exception: ' . $e->getMessage());
            return array('success' => false, 'error' => 'An unexpected error occurred: '.$e->getMessage(), 'error_code' => 'INTERNAL_ERROR');
        }
    }

    private function formatResponse($params, $context, $cart_token)
    {
        $hotelInfo = (new HotelBranchInformation())->hotelBranchInfoById((int)$params['hotel_id']);
        $roomTypeInfo = (new HotelRoomType())->getRoomTypeInfoByIdProduct((new HotelRoomInformation($params['room_id']))->id_product);

        $roomPrice = HotelRoomTypeFeaturePricing::getRoomTypeTotalPrice(
            $roomTypeInfo['id_product'],
            $params['check_in'],
            $params['check_out']
        );

        return array(
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
                    'room_type_name' => $roomTypeInfo['room_type_name'],
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
    }
}
