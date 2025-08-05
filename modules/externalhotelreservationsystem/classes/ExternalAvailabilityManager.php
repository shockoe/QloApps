<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(dirname(__FILE__).'/ExternalApiValidator.php');

class ExternalAvailabilityManager
{
    public function searchAvailability($params)
    {
        try {
            // Validation
            if (empty($params['hotel_id'])) {
                throw new InvalidArgumentException('hotel_id is required');
            }
            if (empty($params['check_in'])) {
                throw new InvalidArgumentException('check_in is required');
            }
            if (empty($params['check_out'])) {
                throw new InvalidArgumentException('check_out is required');
            }

            ExternalApiValidator::validateDateRange($params['check_in'], $params['check_out']);

            $adults = isset($params['adults']) ? $params['adults'] : 2;
            $children = isset($params['children']) ? $params['children'] : 0;
            ExternalApiValidator::validateOccupancy($adults, $children);

            // Load required classes (PrestaShop config should already be loaded)
            require_once(_PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelBookingDetail.php');
            require_once(_PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelRoomType.php');
            require_once(_PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelBranchInformation.php');
            require_once(_PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelRoomTypeFeaturePricing.php');
            require_once(_PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelHelper.php');

            $context = Context::getContext();
            
            // Ensure context has currency set (may be null in webservice calls)
            if (!$context->currency) {
                $context->currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
            }

            // Prepare params for getBookingData
            $bookingParams = array(
                'date_from' => $params['check_in'],
                'date_to' => $params['check_out'],
                'hotel_id' => (int)$params['hotel_id'],
                'id_room_type' => isset($params['room_type']) ? (int)$params['room_type'] : 0,
                'search_available' => 1,
                'search_partial' => 0,
                'search_booked' => 0,
                'search_unavai' => 0,
                'occupancy' => array(
                    array(
                        'adults' => $adults,
                        'children' => $children,
                    )
                )
            );

            $bookingDetail = new HotelBookingDetail();
            $bookingData = $bookingDetail->getBookingData($bookingParams);

            if (empty($bookingData) || empty($bookingData['rm_data'])) {
                 return array(
                    'success' => true,
                    'timestamp' => date('c'),
                    'search_criteria' => $this->formatSearchCriteria($params),
                    'hotels' => [],
                    'total_available_rooms' => 0,
                );
            }

            $hotelInfo = (new HotelBranchInformation())->hotelBranchInfoById((int)$params['hotel_id']);

            $availableRooms = [];
            $totalAvailableRooms = 0;

            foreach ($bookingData['rm_data'] as $roomTypeData) {
                if (isset($roomTypeData['data']['available']) && !empty($roomTypeData['data']['available'])) {
                    foreach ($roomTypeData['data']['available'] as $room) {
                        $totalAvailableRooms++;
                        // Calculate pricing using hotel-specific pricing system
                        $priceData = HotelRoomTypeFeaturePricing::getRoomTypeTotalPrice(
                            $room['id_product'],
                            $params['check_in'],
                            $params['check_out']
                        );
                        
                        $priceWithoutTax = isset($priceData['total_price_tax_excl']) ? $priceData['total_price_tax_excl'] : 0;
                        $price = isset($priceData['total_price_tax_incl']) ? $priceData['total_price_tax_incl'] : 0;


                        $availableRooms[] = array(
                            'id_room' => $room['id_room'],
                            'id_product' => $room['id_product'],
                            'id_room_type' => $room['id_product'],
                            'room_type_name' => $roomTypeData['name'],
                            'room_num' => $room['room_num'],
                            'room_comment' => $room['room_comment'],
                            'adults' => $adults,
                            'children' => $children,
                            'max_adults' => $room['max_adult'],
                            'max_children' => $room['max_children'],
                            'max_guests' => $room['max_occupancy'],
                            'max_occupancy' => $room['max_occupancy'],
                            'price' => array(
                                'base_price' => $priceWithoutTax,
                                'tax_included' => $price,
                                'currency' => $context->currency->iso_code,
                            ),
                        );
                    }
                }
            }

            $response = array(
                'success' => true,
                'timestamp' => date('c'),
                'search_criteria' => $this->formatSearchCriteria($params),
                'hotels' => [
                    array(
                        'id_hotel' => (int)$params['hotel_id'],
                        'hotel_name' => $hotelInfo['hotel_name'],
                        'email' => $hotelInfo['email'],
                        'check_in' => $hotelInfo['check_in'],
                        'check_out' => $hotelInfo['check_out'],
                        'rating' => $hotelInfo['rating'],
                        'available_rooms' => $availableRooms,
                    ),
                ],
                'total_available_rooms' => $totalAvailableRooms,
            );

            return $response;

        } catch (InvalidArgumentException $e) {
            error_log('ExternalAvailabilityManager InvalidArgumentException: ' . $e->getMessage());
            return array('success' => false, 'error' => $e->getMessage(), 'error_code' => 'INVALID_PARAMETER');
        } catch (Exception $e) {
            error_log('ExternalAvailabilityManager Exception: ' . $e->getMessage());
            return array('success' => false, 'error' => 'An unexpected error occurred: '.$e->getMessage(), 'error_code' => 'INTERNAL_ERROR');
        }
    }

    private function formatSearchCriteria($params)
    {
        return array(
            'hotel_id' => (int)$params['hotel_id'],
            'check_in' => $params['check_in'],
            'check_out' => $params['check_out'],
            'occupancy' => [
                array(
                    'adults' => isset($params['adults']) ? (int)$params['adults'] : 2,
                    'children' => isset($params['children']) ? (int)$params['children'] : 0,
                )
            ],
            'room_type' => isset($params['room_type']) ? (int)$params['room_type'] : 0,
        );
    }
}
