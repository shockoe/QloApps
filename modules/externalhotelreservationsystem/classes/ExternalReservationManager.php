<?php
require_once(dirname(__FILE__).'/ExternalApiValidator.php');

class ExternalReservationManager
{
    public function makeReservation($params)
    {
        try {
            // Validation
            if (empty($params['cart_token'])) {
                throw new InvalidArgumentException('cart_token is required');
            }
            if (empty($params['payment_method'])) {
                throw new InvalidArgumentException('payment_method is required');
            }

            // Include PrestaShop config and required classes
            require_once(dirname(__FILE__).'/../../../config/config.inc.php');
            require_once(dirname(__FILE__).'/../../hotelreservationsystem/classes/HotelCartBookingData.php');
            require_once(dirname(__FILE__).'/../../hotelreservationsystem/classes/HotelBookingDetail.php');

            $context = Context::getContext();

            // Get cart from token
            $cart_data = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'htl_external_cart_tokens` WHERE `cart_token` = "'.pSQL($params['cart_token']).'" AND `expires_at` > NOW()');
            if (!$cart_data) {
                throw new Exception('Invalid or expired cart token');
            }

            $cart = new Cart((int)$cart_data['id_cart']);
            if (!Validate::isLoadedObject($cart)) {
                throw new Exception('Cart not found');
            }

            $context->cart = $cart;
            $context->customer = new Customer((int)$cart->id_customer);

            // Create order
            $payment_module = new MockPaymentModule();
            $payment_module->active = true;

            $total = (float)$cart->getOrderTotal(true, Cart::BOTH);

            $payment_module->validateOrder(
                (int)$cart->id,
                Configuration::get('PS_OS_PAYMENT'),
                $total,
                $params['payment_method'],
                null,
                array(),
                (int)$cart->id_currency,
                false,
                $cart->secure_key
            );

            $order = new Order($payment_module->currentOrder);

            if (!Validate::isLoadedObject($order)) {
                throw new Exception('Failed to create order');
            }

            // Create booking detail
            $objBookingDetail = new HotelBookingDetail();
            $objBookingDetail->createHotelBookingsFromOrder($order->id, $cart->id);

            $booking_id = 'HTL-'.date('Y').'-'.sprintf('%06d', $order->id);
            Db::getInstance()->insert('htl_external_booking_refs', array(
                'booking_id' => $booking_id,
                'id_order' => (int)$order->id,
                'confirmation_number' => $order->reference,
            ));

            $response = $this->formatResponse($order, $booking_id);

            return $response;

        } catch (InvalidArgumentException $e) {
            error_log('ExternalReservationManager InvalidArgumentException: ' . $e->getMessage());
            return array('success' => false, 'error' => $e->getMessage(), 'error_code' => 'INVALID_PARAMETER');
        } catch (Exception $e) {
            error_log('ExternalReservationManager Exception: ' . $e->getMessage());
            return array('success' => false, 'error' => 'An unexpected error occurred: '.$e->getMessage(), 'error_code' => 'INTERNAL_ERROR');
        }
    }

    private function formatResponse($order, $booking_id)
    {
        $context = Context::getContext();
        $bookingDetail = (new HotelBookingDetail())->getBookingDataByOrderId($order->id);
        $hotelInfo = (new HotelBranchInformation())->hotelBranchInfoById($bookingDetail[0]['id_hotel']);
        $roomInfo = new HotelRoomInformation($bookingDetail[0]['id_room']);
        $roomTypeInfo = (new HotelRoomType())->getRoomTypeInfoByIdProduct($roomInfo->id_product);

        return array(
            'success' => true,
            'timestamp' => date('c'),
            'reservation' => array(
                'booking_id' => $booking_id,
                'order_id' => $order->id,
                'status' => 'confirmed',
                'confirmation_number' => $order->reference,
                'hotel' => array(
                    'id_hotel' => $hotelInfo['id'],
                    'hotel_name' => $hotelInfo['hotel_name'],
                    'address' => array(
                        'street' => $hotelInfo['address1'],
                        'city' => $hotelInfo['city'],
                        'state' => (new State($hotelInfo['id_state']))->name,
                        'postal_code' => $hotelInfo['postcode'],
                        'country' => (new Country($hotelInfo['id_country']))->name[$context->language->id],
                    ),
                    'contact' => array(
                        'phone' => $hotelInfo['phone'],
                        'email' => $hotelInfo['email'],
                    ),
                    'check_in_time' => $hotelInfo['check_in'],
                    'check_out_time' => $hotelInfo['check_out'],
                ),
                'room' => array(
                    'id_room' => $roomInfo->id,
                    'room_num' => $roomInfo->room_num,
                    'room_type' => $roomTypeInfo['room_type_name'],
                    'description' => $roomTypeInfo['description'],
                    'amenities' => [], // To be implemented
                ),
                'dates' => array(
                    'check_in' => $bookingDetail[0]['date_from'],
                    'check_out' => $bookingDetail[0]['date_to'],
                    'nights' => HotelHelper::getNumberOfDays($bookingDetail[0]['date_from'], $bookingDetail[0]['date_to']),
                ),
                'occupancy' => array(
                    'adults' => $bookingDetail[0]['adults'],
                    'children' => $bookingDetail[0]['children'],
                    'child_ages' => json_decode($bookingDetail[0]['child_ages']),
                    'total_guests' => $bookingDetail[0]['adults'] + $bookingDetail[0]['children'],
                ),
                'customer' => array(
                    'customer_id' => $order->id_customer,
                    'first_name' => $context->customer->firstname,
                    'last_name' => $context->customer->lastname,
                    'email' => $context->customer->email,
                    'phone' => (new Address($order->id_address_delivery))->phone,
                ),
                'pricing' => array(
                    'room_charges' => array(
                        'base_rate' => $order->total_products,
                        'nights' => HotelHelper::getNumberOfDays($bookingDetail[0]['date_from'], $bookingDetail[0]['date_to']),
                        'subtotal' => $order->total_products_wt,
                    ),
                    'extra_demands' => [], // To be implemented
                    'taxes' => array(
                        'room_tax' => $order->total_paid_tax_incl - $order->total_paid_tax_excl,
                        'service_tax' => 0, // To be implemented
                        'total_tax' => $order->total_paid_tax_incl - $order->total_paid_tax_excl,
                    ),
                    'totals' => array(
                        'subtotal' => $order->total_paid_tax_excl,
                        'tax_amount' => $order->total_paid_tax_incl - $order->total_paid_tax_excl,
                        'total_amount' => $order->total_paid,
                        'currency' => (new Currency($order->id_currency))->iso_code,
                    ),
                ),
                'payment' => array(
                    'method' => $order->payment,
                    'status' => 'completed',
                    'transaction_id' => $order->reference,
                    'amount_paid' => $order->total_paid,
                    'payment_date' => $order->date_add,
                ),
                'special_requests' => $bookingDetail[0]['comment'],
                'cancellation_policy' => [], // To be implemented
                'created_at' => $order->date_add,
            ),
        );
    }
}

class MockPaymentModule extends PaymentModule
{
    public $active = true;
}
