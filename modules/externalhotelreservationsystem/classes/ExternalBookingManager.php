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
            'booking' => array(
                'booking_id' => $booking_id,
                'order_id' => $order->id,
                'status' => 'confirmed',
                'booking_status' => 'alloted', // To be implemented
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
                    'policies' => array(
                        'check_in_time' => $hotelInfo['check_in'],
                        'check_out_time' => $hotelInfo['check_out'],
                        'cancellation_policy' => '', // To be implemented
                    )
                ),
                'room' => array(
                    'id_room' => $roomInfo->id,
                    'room_num' => $roomInfo->room_num,
                    'room_type' => $roomTypeInfo['room_type_name'],
                    'description' => $roomTypeInfo['description'],
                    'amenities' => [], // To be implemented
                    'bed_type' => '', // To be implemented
                    'max_occupancy' => $roomInfo->max_occupancy,
                ),
                'dates' => array(
                    'check_in' => $bookingDetail[0]['date_from'],
                    'check_out' => $bookingDetail[0]['date_to'],
                    'nights' => HotelHelper::getNumberOfDays($bookingDetail[0]['date_from'], $bookingDetail[0]['date_to']),
                    'actual_check_in' => null, // To be implemented
                    'actual_check_out' => null, // To be implemented
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
                        'nightly_rate' => $order->total_products / HotelHelper::getNumberOfDays($bookingDetail[0]['date_from'], $bookingDetail[0]['date_to']),
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
                'booking_history' => [], // To be implemented
                'cancellation_info' => [], // To be implemented
                'created_at' => $order->date_add,
                'updated_at' => $order->date_upd,
            ),
        );
    }
}
