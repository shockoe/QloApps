<?php

class WebserviceSpecificManagementAvailability implements WebserviceSpecificManagementInterface
{
    protected $objOutput;
    protected $output;
    protected $wsObject;

    public function manage()
    {
        try {
            $params = Tools::getAllValues();
            
            // Load required classes
            require_once _PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelBranchInformation.php';
            require_once _PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelRoomType.php';
            require_once _PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelBookingDetail.php';
            
            // Check if this is a search request or list all request
            $isSearchRequest = $this->hasSearchParameters($params);
            
            if ($isSearchRequest) {
                return $this->handleSearchRequest($params);
            } else {
                return $this->handleListAllRequest();
            }
            
        } catch (Exception $e) {
            $errorResponse = array(
                'success' => false,
                'error' => 'Internal error: ' . $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            );
            
            $this->output = json_encode($errorResponse);
            return $this->output;
        }
    }
    
    private function hasSearchParameters($params)
    {
        return isset($params['check_in']) || isset($params['date_from']) || 
               isset($params['check_out']) || isset($params['date_to']) || 
               isset($params['hotel_id']) || isset($params['id_hotel']);
    }
    
    private function handleSearchRequest($params)
    {
        // Validate search parameters
        $validationResult = $this->validateSearchParameters($params);
        if (!$validationResult['valid']) {
            $errorResponse = array(
                'success' => false,
                'error' => implode(', ', $validationResult['errors']),
                'timestamp' => date('Y-m-d H:i:s')
            );
            $this->output = json_encode($errorResponse);
            return $this->output;
        }
        
        // Prepare search parameters following Admin "Book Now" pattern
        $searchParams = $this->prepareSearchParameters($params);
        
        // Execute search using HotelBookingDetail::getBookingData()
        $objBookingDetail = new HotelBookingDetail();
        $bookingData = $objBookingDetail->getBookingData($searchParams);
        
        // Format the response
        $responseData = $this->formatSearchResponse($bookingData, $searchParams);
        
        $this->output = json_encode($responseData);
        return $this->output;
    }
    
    private function validateSearchParameters($params)
    {
        $errors = array();
        
        // Check required parameters
        $checkIn = isset($params['check_in']) ? $params['check_in'] : (isset($params['date_from']) ? $params['date_from'] : '');
        $checkOut = isset($params['check_out']) ? $params['check_out'] : (isset($params['date_to']) ? $params['date_to'] : '');
        $hotelId = isset($params['hotel_id']) ? $params['hotel_id'] : (isset($params['id_hotel']) ? $params['id_hotel'] : '');
        
        if (empty($hotelId)) {
            $errors[] = 'hotel_id is required';
        } elseif (!Validate::isUnsignedInt($hotelId)) {
            $errors[] = 'hotel_id must be a valid integer';
        }
        
        if (empty($checkIn)) {
            $errors[] = 'check_in (or date_from) is required';
        } elseif (!Validate::isDate($checkIn)) {
            $errors[] = 'check_in must be a valid date (Y-m-d format)';
        }
        
        if (empty($checkOut)) {
            $errors[] = 'check_out (or date_to) is required';
        } elseif (!Validate::isDate($checkOut)) {
            $errors[] = 'check_out must be a valid date (Y-m-d format)';
        }
        
        // Validate date logic
        if (!empty($checkIn) && !empty($checkOut)) {
            $currentDate = date('Y-m-d');
            if ($checkIn < $currentDate) {
                $errors[] = 'check_in cannot be in the past';
            }
            if ($checkOut <= $checkIn) {
                $errors[] = 'check_out must be after check_in';
            }
        }
        
        // Validate occupancy if provided
        if (isset($params['adults']) && !Validate::isUnsignedInt($params['adults'])) {
            $errors[] = 'adults must be a valid positive integer';
        }
        if (isset($params['children']) && !Validate::isUnsignedInt($params['children'])) {
            $errors[] = 'children must be a valid positive integer';
        }
        if (isset($params['room_type']) && !Validate::isUnsignedInt($params['room_type'])) {
            $errors[] = 'room_type must be a valid positive integer';
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
    
    private function prepareSearchParameters($params)
    {
        // Normalize parameter names
        $checkIn = isset($params['check_in']) ? $params['check_in'] : $params['date_from'];
        $checkOut = isset($params['check_out']) ? $params['check_out'] : $params['date_to'];
        $hotelId = isset($params['hotel_id']) ? $params['hotel_id'] : $params['id_hotel'];
        
        // Format dates
        $dateFrom = date('Y-m-d H:i:s', strtotime($checkIn));
        $dateTo = date('Y-m-d H:i:s', strtotime($checkOut));
        
        // Prepare occupancy array (following OWS format)
        $occupancy = array();
        if (isset($params['adults']) || isset($params['children'])) {
            $adults = isset($params['adults']) ? (int)$params['adults'] : 2;
            $children = isset($params['children']) ? (int)$params['children'] : 0;
            $occupancy[] = array(
                'adults' => $adults,
                'children' => $children,
                'child_ages' => array() // Could be enhanced to accept child ages
            );
        }
        
        // Prepare search parameters following AdminHotelRoomsBookingController pattern
        $searchParams = array(
            'hotel_id' => $hotelId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'id_room_type' => isset($params['room_type']) ? (int)$params['room_type'] : 0,
            'occupancy' => $occupancy,
            'search_available' => 1, // Only search available rooms
            'search_partial' => 0,   // Don't include partial rooms
            'search_booked' => 0,    // Don't include booked rooms  
            'search_unavai' => 0,    // Don't include unavailable rooms
            'search_cart_rms' => 0,  // Don't include cart rooms
            'only_search_data' => 0,
            'only_active_roomtype' => 1,
            'only_active_hotel' => 1
        );
        
        return $searchParams;
    }
    
    private function formatSearchResponse($bookingData, $searchParams)
    {
        $responseData = array(
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'search_criteria' => array(
                'hotel_id' => $searchParams['hotel_id'],
                'check_in' => date('Y-m-d', strtotime($searchParams['date_from'])),
                'check_out' => date('Y-m-d', strtotime($searchParams['date_to'])),
                'occupancy' => $searchParams['occupancy'],
                'room_type' => $searchParams['id_room_type']
            ),
            'hotels' => array()
        );
        
        if ($bookingData && isset($bookingData['stats']['num_avail'])) {
            // Get hotel information
            $objHotelBranchInformation = new HotelBranchInformation();
            $hotel = $objHotelBranchInformation->hotelBranchInfoById($searchParams['hotel_id']);
            
            $hotelData = array(
                'id_hotel' => $hotel['id'],
                'hotel_name' => $hotel['hotel_name'],
                'email' => isset($hotel['email']) ? $hotel['email'] : '',
                'check_in' => isset($hotel['check_in']) ? $hotel['check_in'] : '',
                'check_out' => isset($hotel['check_out']) ? $hotel['check_out'] : '',
                'rating' => isset($hotel['rating']) ? $hotel['rating'] : 0,
                'available_rooms' => array()
            );
            
            // Process available rooms
            if (isset($bookingData['rm_data']) && is_array($bookingData['rm_data'])) {
                foreach ($bookingData['rm_data'] as $idProduct => $roomTypeData) {
                    if (isset($roomTypeData['data']['available'])) {
                        foreach ($roomTypeData['data']['available'] as $idRoom => $roomData) {
                            $availableRoom = array(
                                'id_room' => $roomData['id_room'],
                                'id_product' => $roomData['id_product'],
                                'id_room_type' => $roomData['id_product'], // room type ID
                                'room_type_name' => $roomTypeData['name'],
                                'room_num' => $roomData['room_num'],
                                'room_comment' => isset($roomData['room_comment']) ? $roomData['room_comment'] : '',
                                'adults' => $roomTypeData['adults'],
                                'children' => $roomTypeData['children'],
                                'max_adults' => isset($roomData['max_adult']) ? $roomData['max_adult'] : $roomTypeData['max_adults'],
                                'max_children' => isset($roomData['max_children']) ? $roomData['max_children'] : $roomTypeData['max_children'],
                                'max_guests' => $roomTypeData['max_guests'],
                                'max_occupancy' => isset($roomData['max_occupancy']) ? $roomData['max_occupancy'] : $roomTypeData['max_guests']
                            );
                            
                            $hotelData['available_rooms'][] = $availableRoom;
                        }
                    }
                }
            }
            
            $responseData['hotels'][] = $hotelData;
            $responseData['total_available_rooms'] = count($hotelData['available_rooms']);
        } else {
            $responseData['total_available_rooms'] = 0;
            $responseData['message'] = 'No available rooms found for the specified criteria';
        }
        
        return $responseData;
    }
    
    private function handleListAllRequest()
    {
        // Original functionality for listing all hotels and rooms
        $objHotelBranchInformation = new HotelBranchInformation();
        $objHotelRoomType = new HotelRoomType();
        
        // Get all active hotels
        $hotels = $objHotelBranchInformation->hotelBranchesInfo(false, 1);
        
        $responseData = array(
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'hotels' => array()
        );
        
        if ($hotels) {
            foreach ($hotels as $hotel) {
                // Get room types for this hotel
                $roomTypes = $objHotelRoomType->getRoomTypeByHotelId(
                    $hotel['id'], 
                    Configuration::get('PS_LANG_DEFAULT'), 
                    1 // only active room types
                );
                
                $hotelData = array(
                    'id_hotel' => $hotel['id'],
                    'hotel_name' => $hotel['hotel_name'],
                    'email' => isset($hotel['email']) ? $hotel['email'] : '',
                    'check_in' => isset($hotel['check_in']) ? $hotel['check_in'] : '',
                    'check_out' => isset($hotel['check_out']) ? $hotel['check_out'] : '',
                    'rating' => isset($hotel['rating']) ? $hotel['rating'] : 0,
                    'active' => $hotel['active'],
                    'description' => isset($hotel['description']) ? $hotel['description'] : '',
                    'short_description' => isset($hotel['short_description']) ? $hotel['short_description'] : '',
                    'rooms' => array()
                );
                
                if ($roomTypes) {
                    foreach ($roomTypes as $roomType) {
                        // Get detailed room type information
                        $roomTypeDetails = new HotelRoomType($roomType['id_room_type']);
                        
                        $roomData = array(
                            'id_product' => $roomType['id_product'],
                            'id_room_type' => $roomType['id_room_type'],
                            'room_type_name' => $roomType['room_type'],
                            'active' => $roomType['active'],
                            'adults' => $roomTypeDetails->adults,
                            'children' => $roomTypeDetails->children,
                            'max_adults' => $roomTypeDetails->max_adults,
                            'max_children' => $roomTypeDetails->max_children,
                            'max_guests' => $roomTypeDetails->max_guests,
                            'min_los' => $roomTypeDetails->min_los,
                            'max_los' => $roomTypeDetails->max_los
                        );
                        
                        $hotelData['rooms'][] = $roomData;
                    }
                }
                
                $responseData['hotels'][] = $hotelData;
            }
        }
        
        $this->output = json_encode($responseData);
        return $this->output;
    }

    public function setObjectOutput(WebserviceOutputBuilderCore $obj)
    {
        $this->objOutput = $obj;
        return $this;
    }

    public function getObjectOutput()
    {
        return $this->objOutput;
    }

    public function getContent()
    {
        return $this->output;
    }

    public function getWsObject()
    {
        return $this->wsObject;
    }

    public function setWsObject(WebserviceRequestCore $obj)
    {
        $this->wsObject = $obj;
        return $this;
    }
}