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
            
            // Get all hotels
            require_once _PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelBranchInformation.php';
            require_once _PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelRoomType.php';
            
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
                                'min_los' => $roomTypeDetails->min_los, // minimum length of stay
                                'max_los' => $roomTypeDetails->max_los  // maximum length of stay
                            );
                            
                            $hotelData['rooms'][] = $roomData;
                        }
                    }
                    
                    $responseData['hotels'][] = $hotelData;
                }
            }
            
            // Set the response
            $this->output = json_encode($responseData);
            return $this->output;
            
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