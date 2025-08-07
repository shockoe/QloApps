<?php

require_once(dirname(__FILE__).'/../../../config/config.inc.php');
require_once(dirname(__FILE__).'/../../hotelreservationsystem/classes/HotelBookingDetail.php');
require_once(dirname(__FILE__).'/../../hotelreservationsystem/classes/HotelOrderRefundRules.php');
require_once(dirname(__FILE__).'/ExternalApiValidator.php');

class ExternalCancellationManager
{
    public function processCancellationRequest($params)
    {
        try {
            $confirmation_number = $params['confirmation_number'];
            $customer_id = (int)$params['customer_id'];
            $secure_key = $params['secure_key'];
            $cancellation_reason = isset($params['cancellation_reason']) ? $params['cancellation_reason'] : '';

            // 1. Validate customer credentials
            $customer = new Customer($customer_id);
            if (!Validate::isLoadedObject($customer) || $customer->secure_key !== $secure_key) {
                return $this->errorResponse('Invalid customer credentials or secure key.', 'AUTHENTICATION_FAILED', 401);
            }

            // 2. Retrieve booking details using confirmation_number and customer_id
            $booking_ref = Db::getInstance()->getRow(
                'SELECT * FROM `'._DB_PREFIX_.'htl_external_booking_refs` 
                WHERE `confirmation_number` = \''.pSQL($confirmation_number).'\''
            );

            if (!$booking_ref) {
                return $this->errorResponse('Booking not found.', 'BOOKING_NOT_FOUND', 404);
            }

            $order = new Order($booking_ref['id_order']);
            if (!Validate::isLoadedObject($order) || $order->id_customer != $customer_id) {
                return $this->errorResponse('Booking not found or does not belong to customer.', 'BOOKING_NOT_FOUND', 404);
            }

            $hotel_booking_details = (new HotelBookingDetail())->getBookingDataByOrderId($order->id);
            if (empty($hotel_booking_details)) {
                return $this->errorResponse('No hotel booking details found for this order.', 'BOOKING_NOT_FOUND', 404);
            }

            // Assuming one booking detail per order for simplicity, or iterate if multiple rooms per order
            $main_booking_detail = $hotel_booking_details[0];

            // 3. Check if already cancelled
            if ($main_booking_detail['is_cancelled'] || $order->getCurrentState() == Configuration::get('PS_OS_CANCELED')) {
                return $this->errorResponse('This booking has already been cancelled.', 'ALREADY_CANCELLED', 409);
            }

            // 4. Check cancellation deadline (simplified for now, using check-in date)
            $check_in_date = strtotime($main_booking_detail['date_from']);
            $current_date = time();

            if ($current_date > $check_in_date) {
                return $this->errorResponse(
                    'Cancellation deadline has passed.',
                    'CANCELLATION_DEADLINE_PASSED',
                    400,
                    [
                        'check_in_date' => $main_booking_detail['date_from'],
                        'current_time' => date('Y-m-d H:i:s'),
                    ]
                );
            }

            // 5. Process cancellation
            // Update order status to cancelled
            $order_history = new OrderHistory();
            $order_history->id_order = (int)$order->id;
            $order_history->changeIdOrderState(Configuration::get('PS_OS_CANCELED'), (int)$order->id, true);
            $order_history->add();

            // Update hotel booking detail as cancelled
            $objHtlBookingDetail = new HotelBookingDetail($main_booking_detail['id']);
            $objHtlBookingDetail->is_cancelled = 1;
            $objHtlBookingDetail->id_status = HotelBookingDetail::STATUS_ALLOTED; // Or a specific cancelled status if available
            $objHtlBookingDetail->update();

            // 6. Delete the cart
            $cart = new Cart($order->id_cart);
            if (Validate::isLoadedObject($cart)) {
                Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'htl_cart_booking_data` WHERE `id_cart` = '.(int)$cart->id);
                $cart->delete();
            }

            // Generate a cancellation ID (simple example)
            $cancellation_id = 'CANCEL-' . $order->id . '-' . time();

            // Prepare response
            return $this->successResponse(
                [
                    'cancellation' => [
                        'booking_id' => $confirmation_number,
                        'cancellation_id' => $cancellation_id,
                        'order_id' => (int)$order->id,
                        'status' => 'confirmed',
                        'cancellation_date' => date('Y-m-d H:i:s'),
                        'cancellation_reason' => $cancellation_reason,
                        'booking_summary' => [
                            'hotel' => [
                                'hotel_name' => $main_booking_detail['hotel_name'],
                            ],
                            'room' => [
                                'room_num' => $main_booking_detail['room_num'],
                                'room_type' => $main_booking_detail['room_type_name'],
                            ],
                            'dates' => [
                                'check_in' => $main_booking_detail['date_from'],
                                'check_out' => $main_booking_detail['date_to'],
                            ],
                            'original_amount' => [
                                'total' => (float)$order->total_paid,
                                'currency' => (new Currency($order->id_currency))->iso_code,
                            ],
                        ],
                        'cancellation_details' => [
                            'refund_amount' => [
                                'amount' => (float)$order->total_paid, // For now, full refund. Implement refund rules later.
                                'currency' => (new Currency($order->id_currency))->iso_code,
                                'refund_method' => 'original_payment_method',
                            ],
                        ],
                    ],
                ],
                200
            );

        } catch (Exception $e) {
            // Log the error for debugging
            error_log('ExternalCancellationManager Exception: ' . $e->getMessage());
            return $this->errorResponse('Internal server error: ' . $e->getMessage(), 'INTERNAL_ERROR', 500);
        }
    }

    protected function errorResponse($message, $code = 'GENERAL_ERROR', $httpStatus = 400, $details = null)
    {
        $response = [
            'success' => false,
            'error' => $message,
            'error_code' => $code,
            'timestamp' => date('c'),
        ];
        if ($details) {
            $response['details'] = $details;
        }
        http_response_code($httpStatus);
        header('Content-Type: application/json');
        return json_encode($response);
    }

    protected function successResponse($data, $httpStatus = 200)
    {
        $response = [
            'success' => true,
            'timestamp' => date('c'),
        ];
        $response = array_merge($response, $data);
        http_response_code($httpStatus);
        header('Content-Type: application/json');
        return json_encode($response);
    }
}
