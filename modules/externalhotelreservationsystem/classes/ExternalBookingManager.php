<?php
class ExternalBookingManager
{
    public function getBookingDetails($booking_id)
    {
        try {
            // Validation
            if (empty($booking_id)) {
                throw new InvalidArgumentException('booking_id is required');
            }

            // Include PrestaShop config and required classes
            require_once(dirname(__FILE__).'/../../../config/config.inc.php');
            require_once(dirname(__FILE__).'/../../hotelreservationsystem/classes/HotelBookingDetail.php');
            require_once(dirname(__FILE__).'/../../hotelreservationsystem/classes/HotelBranchInformation.php');
            require_once(dirname(__FILE__).'/../../hotelreservationsystem/classes/HotelRoomInformation.php');
            require_once(dirname(__FILE__).'/../../hotelreservationsystem/classes/HotelRoomType.php');
            require_once(dirname(__FILE__).'/../../hotelreservationsystem/classes/HotelHelper.php');

            // Get order from booking_id
            $booking_ref = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'htl_external_booking_refs` WHERE `booking_id` = "'.pSQL($booking_id).'"');
            if (!$booking_ref) {
                throw new Exception('Booking not found');
            }

            $order = new Order((int)$booking_ref['id_order']);
            if (!Validate::isLoadedObject($order)) {
                throw new Exception('Order not found');
            }

            $response = $this->formatResponse($order, $booking_id);

            return $response;

        } catch (InvalidArgumentException $e) {
            error_log('ExternalBookingManager InvalidArgumentException: ' . $e->getMessage());
            return array('success' => false, 'error' => $e->getMessage(), 'error_code' => 'INVALID_PARAMETER');
        } catch (Exception $e) {
            error_log('ExternalBookingManager Exception: ' . $e->getMessage());
            return array('success' => false, 'error' => 'An unexpected error occurred: '.$e->getMessage(), 'error_code' => 'INTERNAL_ERROR');
        }
    }

    public function getBookingDetailsByConfirmation($confirmation_number)
    {
        try {
            // Validation
            if (empty($confirmation_number)) {
                throw new InvalidArgumentException('confirmation_number is required');
            }

            // Include PrestaShop config and required classes
            require_once(dirname(__FILE__).'/../../../config/config.inc.php');
            require_once(dirname(__FILE__).'/../../hotelreservationsystem/classes/HotelBookingDetail.php');
            require_once(dirname(__FILE__).'/../../hotelreservationsystem/classes/HotelBranchInformation.php');
            require_once(dirname(__FILE__).'/../../hotelreservationsystem/classes/HotelRoomInformation.php');
            require_once(dirname(__FILE__).'/../../hotelreservationsystem/classes/HotelRoomType.php');
            require_once(dirname(__FILE__).'/../../hotelreservationsystem/classes/HotelHelper.php');

            // Get order from confirmation_number (order reference)
            $order = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'orders` WHERE `reference` = "'.pSQL($confirmation_number).'"');
            if (!$order) {
                throw new Exception('Booking not found with confirmation number: ' . $confirmation_number);
            }

            $order_obj = new Order((int)$order['id_order']);
            if (!Validate::isLoadedObject($order_obj)) {
                throw new Exception('Order not found');
            }

            // Get the booking_id from the external booking refs table
            $booking_ref = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'htl_external_booking_refs` WHERE `id_order` = '.(int)$order['id_order']);
            $booking_id = $booking_ref ? $booking_ref['booking_id'] : 'HTL-' . date('Y', strtotime($order_obj->date_add)) . '-' . $order_obj->id;

            $response = $this->formatResponse($order_obj, $booking_id);

            return $response;

        } catch (InvalidArgumentException $e) {
            error_log('ExternalBookingManager InvalidArgumentException: ' . $e->getMessage());
            return array('success' => false, 'error' => $e->getMessage(), 'error_code' => 'INVALID_PARAMETER');
        } catch (Exception $e) {
            error_log('ExternalBookingManager Exception: ' . $e->getMessage());
            return array('success' => false, 'error' => 'An unexpected error occurred: '.$e->getMessage(), 'error_code' => 'INTERNAL_ERROR');
        }
    }

    private function formatResponse($order, $booking_id)
    {
        // Check if booking details exist and booking is active
        $bookingDetail = (new HotelBookingDetail())->getBookingDataByOrderId($order->id);
        if (empty($bookingDetail)) {
            throw new Exception('No booking details found for order ID: ' . $order->id);
        }
        
        // Check if booking is active (not cancelled)
        if (isset($bookingDetail[0]['is_cancelled']) && $bookingDetail[0]['is_cancelled']) {
            throw new Exception('Booking has been cancelled');
        }

        // Load customer information properly from the order
        $customer = new Customer($order->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            throw new Exception('Customer not found for order');
        }

        // Load delivery address for phone and address details
        $deliveryAddress = new Address($order->id_address_delivery);
        
        // Get hotel information
        $hotelInfo = (new HotelBranchInformation())->hotelBranchInfoById($bookingDetail[0]['id_hotel']);
        if (empty($hotelInfo)) {
            throw new Exception('Hotel information not found');
        }

        // Get room information
        $roomInfo = new HotelRoomInformation($bookingDetail[0]['id_room']);
        if (!Validate::isLoadedObject($roomInfo)) {
            throw new Exception('Room information not found');
        }

        // Get room type information with proper language handling
        $roomTypeInfo = (new HotelRoomType())->getRoomTypeInfoByIdProduct($roomInfo->id_product);
        $context = Context::getContext();
        $languageId = isset($context->language->id) ? $context->language->id : Configuration::get('PS_LANG_DEFAULT');
        
        // Get product information for room type name and description
        $product = new Product($roomInfo->id_product, false, $languageId);
        $roomTypeName = '';
        $roomTypeDescription = '';
        
        if (Validate::isLoadedObject($product)) {
            $roomTypeName = $product->name;
            $roomTypeDescription = $product->description_short ? $product->description_short : $product->description;
        }

        return array(
            'success' => true,
            'timestamp' => date('c'),
            'booking' => array(
                'booking_id' => $booking_id,
                'order_id' => $order->id,
                'status' => $order->hasBeenPaid() ? 'confirmed' : 'pending',
                'booking_status' => isset($bookingDetail[0]['booking_status']) ? $bookingDetail[0]['booking_status'] : 'alloted',
                'confirmation_number' => $order->reference,
                'hotel' => array(
                    'id_hotel' => $hotelInfo['id'],
                    'hotel_name' => $hotelInfo['hotel_name'],
                    'address' => array(
                        'street' => isset($hotelInfo['address']) ? $hotelInfo['address'] : '',
                        'city' => isset($hotelInfo['city']) ? $hotelInfo['city'] : '',
                        'state' => isset($hotelInfo['state_name']) ? $hotelInfo['state_name'] : '',
                        'postal_code' => isset($hotelInfo['zipcode']) ? $hotelInfo['zipcode'] : '',
                        'country' => isset($hotelInfo['country_name']) ? $hotelInfo['country_name'] : '',
                    ),
                    'contact' => array(
                        'phone' => isset($hotelInfo['phone']) ? $hotelInfo['phone'] : '',
                        'email' => isset($hotelInfo['email']) ? $hotelInfo['email'] : '',
                    ),
                    'policies' => array(
                        'check_in_time' => $hotelInfo['check_in'],
                        'check_out_time' => $hotelInfo['check_out'],
                        'cancellation_policy' => isset($hotelInfo['policies']) ? $hotelInfo['policies'] : '',
                    )
                ),
                'room' => array(
                    'id_room' => $roomInfo->id,
                    'room_num' => $roomInfo->room_num,
                    'room_type' => $roomTypeName,
                    'description' => strip_tags($roomTypeDescription),
                    'amenities' => [], // To be implemented with room type amenities
                    'bed_type' => isset($roomTypeInfo->bed_type) ? $roomTypeInfo->bed_type : '',
                    'max_occupancy' => isset($roomTypeInfo['max_guests']) ? (int)$roomTypeInfo['max_guests'] : 0,
                ),
                'dates' => array(
                    'check_in' => $bookingDetail[0]['date_from'],
                    'check_out' => $bookingDetail[0]['date_to'],
                    'nights' => HotelHelper::getNumberOfDays($bookingDetail[0]['date_from'], $bookingDetail[0]['date_to']),
                    'actual_check_in' => isset($bookingDetail[0]['check_in']) ? $bookingDetail[0]['check_in'] : null,
                    'actual_check_out' => isset($bookingDetail[0]['check_out']) ? $bookingDetail[0]['check_out'] : null,
                ),
                'occupancy' => array(
                    'adults' => (int)$bookingDetail[0]['adults'],
                    'children' => (int)$bookingDetail[0]['children'],
                    'child_ages' => !empty($bookingDetail[0]['child_ages']) ? json_decode($bookingDetail[0]['child_ages'], true) : [],
                    'total_guests' => (int)$bookingDetail[0]['adults'] + (int)$bookingDetail[0]['children'],
                ),
                'customer' => array(
                    'customer_id' => (int)$order->id_customer,
                    'first_name' => $customer->firstname,
                    'last_name' => $customer->lastname,
                    'email' => $customer->email,
                    'phone' => Validate::isLoadedObject($deliveryAddress) ? $deliveryAddress->phone : '',
                ),
                'pricing' => array(
                    'room_charges' => array(
                        'nightly_rate' => round($order->total_products / HotelHelper::getNumberOfDays($bookingDetail[0]['date_from'], $bookingDetail[0]['date_to']), 2),
                        'nights' => HotelHelper::getNumberOfDays($bookingDetail[0]['date_from'], $bookingDetail[0]['date_to']),
                        'subtotal' => round((float)$order->total_products_wt, 2),
                    ),
                    'extra_demands' => [], // To be implemented with room demands
                    'taxes' => array(
                        'room_tax' => round((float)$order->total_paid_tax_incl - (float)$order->total_paid_tax_excl, 2),
                        'service_tax' => 0, // To be implemented
                        'total_tax' => round((float)$order->total_paid_tax_incl - (float)$order->total_paid_tax_excl, 2),
                    ),
                    'totals' => array(
                        'subtotal' => round((float)$order->total_paid_tax_excl, 2),
                        'tax_amount' => round((float)$order->total_paid_tax_incl - (float)$order->total_paid_tax_excl, 2),
                        'total_amount' => round((float)$order->total_paid, 2),
                        'currency' => (new Currency($order->id_currency))->iso_code,
                    ),
                ),
                'payment' => array(
                    'method' => $order->payment,
                    'status' => $order->hasBeenPaid() ? 'completed' : 'pending',
                    'transaction_id' => $order->reference,
                    'amount_paid' => round((float)$order->total_paid, 2),
                    'payment_date' => $order->date_add,
                ),
                'special_requests' => isset($bookingDetail[0]['comment']) ? $bookingDetail[0]['comment'] : '',
                'booking_history' => [], // To be implemented
                'cancellation_info' => array(
                    'is_cancellable' => !$order->hasBeenPaid() || strtotime($bookingDetail[0]['date_from']) > strtotime('+24 hours'),
                    'cancellation_deadline' => date('c', strtotime($bookingDetail[0]['date_from'] . ' -24 hours')),
                ), 
                'created_at' => $order->date_add,
                'updated_at' => $order->date_upd,
            ),
        );
    }
}
