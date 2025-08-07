<?php
class ExternalMyStayManager
{
    public function getCustomerReservations($customer_id, $secure_key)
    {
        try {
            // Include PrestaShop config and required classes
            require_once(dirname(__FILE__).'/../../../config/config.inc.php');
            require_once(dirname(__FILE__).'/../../hotelreservationsystem/classes/HotelBookingDetail.php');
            require_once(dirname(__FILE__).'/../../hotelreservationsystem/classes/HotelBranchInformation.php');
            require_once(dirname(__FILE__).'/../../hotelreservationsystem/classes/HotelRoomInformation.php');
            require_once(dirname(__FILE__).'/../../hotelreservationsystem/classes/HotelRoomType.php');
            require_once(dirname(__FILE__).'/../../hotelreservationsystem/classes/HotelHelper.php');

            // Validate customer and secure key
            $customer = $this->validateCustomer($customer_id, $secure_key);
            if (!$customer) {
                return array(
                    'success' => false, 
                    'error' => 'Invalid customer credentials', 
                    'error_code' => 'AUTHENTICATION_FAILED'
                );
            }

            // Get all orders for this customer
            $orders = $this->getCustomerOrders($customer_id);
            
            // Get reservations for each order
            $reservations = array();
            foreach ($orders as $order) {
                $booking_details = $this->getBookingDetailsForOrder($order);
                if ($booking_details) {
                    $reservations[] = $booking_details;
                }
            }

            // Filter for active and upcoming reservations
            $activeUpcomingReservations = $this->filterActiveUpcomingReservations($reservations);

            return array(
                'success' => true,
                'timestamp' => date('c'),
                'customer' => array(
                    'customer_id' => (int)$customer_id,
                    'first_name' => $customer->firstname,
                    'last_name' => $customer->lastname,
                    'email' => $customer->email,
                ),
                'reservations' => $activeUpcomingReservations,
                'total_reservations' => count($activeUpcomingReservations)
            );

        } catch (Exception $e) {
            error_log('ExternalMyStayManager Exception: ' . $e->getMessage());
            return array(
                'success' => false, 
                'error' => 'An unexpected error occurred: '.$e->getMessage(), 
                'error_code' => 'INTERNAL_ERROR'
            );
        }
    }

    private function validateCustomer($customer_id, $secure_key)
    {
        // Load and validate customer
        $customer = new Customer($customer_id);
        if (!Validate::isLoadedObject($customer)) {
            return false;
        }

        // Validate secure key
        if ($customer->secure_key !== $secure_key) {
            return false;
        }

        return $customer;
    }

    private function getCustomerOrders($customer_id)
    {
        // Get all orders for the customer, ordered by date descending
        $sql = 'SELECT * FROM `'._DB_PREFIX_.'orders` 
                WHERE `id_customer` = '.(int)$customer_id.' 
                ORDER BY `date_add` DESC';
        
        return Db::getInstance()->executeS($sql);
    }

    private function getBookingDetailsForOrder($orderData)
    {
        try {
            $order = new Order($orderData['id_order']);
            if (!Validate::isLoadedObject($order)) {
                return null;
            }

            // Get booking details from hotel reservation system
            $bookingDetail = (new HotelBookingDetail())->getBookingDataByOrderId($order->id);
            if (empty($bookingDetail)) {
                return null; // Not a hotel reservation
            }

            // Get external booking reference if it exists
            $booking_ref = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'htl_external_booking_refs` WHERE `id_order` = '.(int)$order->id);
            $booking_id = $booking_ref ? $booking_ref['booking_id'] : 'HTL-' . date('Y', strtotime($order->date_add)) . '-' . $order->id;

            // Get hotel and room information
            $hotelInfo = (new HotelBranchInformation())->hotelBranchInfoById($bookingDetail[0]['id_hotel']);
            $roomInfo = new HotelRoomInformation($bookingDetail[0]['id_room']);
            $roomTypeInfo = (new HotelRoomType())->getRoomTypeInfoByIdProduct($roomInfo->id_product);
            
            // Get product information for room type name
            $context = Context::getContext();
            $languageId = isset($context->language->id) ? $context->language->id : Configuration::get('PS_LANG_DEFAULT');
            $product = new Product($roomInfo->id_product, false, $languageId);
            $roomTypeName = '';
            if (Validate::isLoadedObject($product)) {
                $roomTypeName = $product->name;
            }

            // Get hotel images
            $hotelImages = array();
            require_once(dirname(__FILE__).'/../../hotelreservationsystem/classes/HotelImage.php');
            $hotelImageObj = new HotelImage();
            $hotelImagesList = $hotelImageObj->getImagesByHotelId($bookingDetail[0]['id_hotel']);
            if ($hotelImagesList && is_array($hotelImagesList)) {
                foreach ($hotelImagesList as $hotelImg) {
                    $imageObj = new HotelImage($hotelImg['id']);
                    $imageUrl = $imageObj->getImageLink($hotelImg['id']);
                    $hotelImages[] = array(
                        'id' => (int)$hotelImg['id'],
                        'is_cover' => (bool)$hotelImg['cover'],
                        'url' => Context::getContext()->link->getMediaLink($imageUrl),
                    );
                }
            }

            // Get room images (product images)
            $roomImages = array();
            $productImages = Image::getImages($languageId, $roomInfo->id_product);
            if ($productImages) {
                foreach ($productImages as $productImg) {
                    $roomImages[] = array(
                        'id' => (int)$productImg['id_image'],
                        'is_cover' => (bool)$productImg['cover'],
                        'legend' => $productImg['legend'],
                        'url' => Context::getContext()->link->getImageLink($product->link_rewrite, $productImg['id_image'], ImageType::getFormatedName('medium')),
                    );
                }
            }

            // Determine reservation status
            $reservation_status = $this->getReservationStatus($bookingDetail[0], $order);

            return array(
                'booking_id' => $booking_id,
                'order_id' => $order->id,
                'status' => $reservation_status,
                'confirmation_number' => $order->reference,
                'hotel' => array(
                    'id_hotel' => $hotelInfo['id'],
                    'hotel_name' => $hotelInfo['hotel_name'],
                    'email' => isset($hotelInfo['email']) ? $hotelInfo['email'] : '',
                    'phone' => isset($hotelInfo['phone']) ? $hotelInfo['phone'] : '',
                    'images' => $hotelImages
                ),
                'room' => array(
                    'id_room' => $roomInfo->id,
                    'room_num' => $roomInfo->room_num,
                    'room_type' => $roomTypeName,
                    'max_occupancy' => isset($roomTypeInfo['max_guests']) ? (int)$roomTypeInfo['max_guests'] : 0,
                    'images' => $roomImages
                ),
                'dates' => array(
                    'check_in' => $bookingDetail[0]['date_from'],
                    'check_out' => $bookingDetail[0]['date_to'],
                    'nights' => HotelHelper::getNumberOfDays($bookingDetail[0]['date_from'], $bookingDetail[0]['date_to']),
                ),
                'occupancy' => array(
                    'adults' => (int)$bookingDetail[0]['adults'],
                    'children' => (int)$bookingDetail[0]['children'],
                    'total_guests' => (int)$bookingDetail[0]['adults'] + (int)$bookingDetail[0]['children'],
                ),
                'pricing' => array(
                    'total_amount' => round((float)$order->total_paid, 2),
                    'currency' => (new Currency($order->id_currency))->iso_code,
                ),
                'payment' => array(
                    'method' => $order->payment,
                    'status' => $order->hasBeenPaid() ? 'completed' : 'pending',
                ),
                'created_at' => $order->date_add,
                'updated_at' => $order->date_upd,
            );

        } catch (Exception $e) {
            error_log('Error processing order ' . $orderData['id_order'] . ': ' . $e->getMessage());
            return null;
        }
    }

    private function getReservationStatus($bookingDetail, $order)
    {
        // Check if booking is cancelled
        if (isset($bookingDetail['is_cancelled']) && $bookingDetail['is_cancelled']) {
            return 'cancelled';
        }

        $currentDate = date('Y-m-d');
        $checkInDate = date('Y-m-d', strtotime($bookingDetail['date_from']));
        $checkOutDate = date('Y-m-d', strtotime($bookingDetail['date_to']));

        // Determine status based on dates
        if ($checkOutDate < $currentDate) {
            return 'completed';
        } elseif ($checkInDate <= $currentDate && $checkOutDate >= $currentDate) {
            return 'active';
        } elseif ($checkInDate > $currentDate) {
            return 'upcoming';
        }

        return 'pending';
    }

    private function filterActiveUpcomingReservations($reservations)
    {
        $filtered = array();
        
        foreach ($reservations as $reservation) {
            // Only include active, upcoming, or pending reservations
            if (in_array($reservation['status'], ['active', 'upcoming', 'pending'])) {
                $filtered[] = $reservation;
            }
        }

        return $filtered;
    }
}