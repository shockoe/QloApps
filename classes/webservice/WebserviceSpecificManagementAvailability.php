<?php
require_once _PS_MODULE_DIR_.'hotelreservationsystem/define.php';

class WebserviceSpecificManagementAvailability implements WebserviceSpecificManagementInterface
{
    /**
     * @var WebserviceOutputBuilder
     */
    protected $objOutput;
    protected $output;
    /**
     * @var Db
     */
    protected $db;

    public function __construct(WebserviceOutputBuilderCore $obj_output, DbCore $db)
    {
        $this->objOutput = $obj_output;
        $this->db = $db;
    }

    public function manage()
    {
        $this->wsObject = $this->objOutput->getObjectRender();
        $this->output = $this->objOutput->getContent();
        $this->executeSpecificManagedMethod();
        return $this->wsObject->render();
    }

    public function getObjectOutput()
    {
        return $this->objOutput;
    }

    public function getWsObject()
    {
        return $this->wsObject;
    }

    public function setWsObject(WebserviceOutputBuilderCore $obj)
    {
        $this->wsObject = $obj;
        return $this;
    }

    protected function executeSpecificManagedMethod()
    {
        $params = Tools::getAllValues();

        // Validate search fields
        require_once _PS_MODULE_DIR_.'wkroomsearchblock/classes/WkRoomSearchHelper.php';
        $searchHelper = new WkRoomSearchHelper();
        $errors = $this->validateSearchFields($params);

        if (count($errors) > 0) {
            $this->wsObject->setError(400, implode('\n', $errors), 101);
            return;
        }

        // Prepare parameters for getBookingData
        $bookingParams = $this->prepareBookingParams($params);

        // Call getBookingData
        require_once _PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelBookingDetail.php';
        $bookingDetail = new HotelBookingDetail();
        $result = $bookingDetail->getBookingData($bookingParams);

        // Set the output
        $this->wsObject->setObjectList($result);
    }

    private function validateSearchFields($params)
    {
        $errors = array();
        $objModule = Module::getInstanceByName('wkroomsearchblock');

        if (empty($params['hotel_cat_id'])) {
            $errors[] = $objModule->l('Please enter a location', 'WkRoomSearchHelper');
        }
        if (empty($params['date_from']) || !Validate::isDate($params['date_from'])) {
            $errors[] = $objModule->l('Please select a valid Check-In', 'WkRoomSearchHelper');
        }
        if (empty($params['date_to']) || !Validate::isDate($params['date_to'])) {
            $errors[] = $objModule->l('Please select a valid Check-Out', 'WkRoomSearchHelper');
        }
        if (!empty($params['date_from']) && !empty($params['date_to'])) {
            $currentDate = date('Y-m-d');
            $maxOrderDate = isset($params['max_order_date']) ? $params['max_order_date'] : date('Y-m-d', strtotime('+1 year'));

            if (($params['date_from'] < $currentDate)
                || ($params['date_to'] <= $params['date_from'])
                || ($maxOrderDate < $params['date_from'] || $maxOrderDate < $params['date_to'])
            ) {
                $errors[] = $objModule->l('Please select a valid date range', 'WkRoomSearchHelper');
            }
        }

        if (Configuration::get('PS_FRONT_SEARCH_TYPE') == HotelBookingDetail::SEARCH_TYPE_OWS) {
            if (empty($params['occupancy'])) {
                $errors[] = $objModule->l('Invalid occupancy', 'WkRoomSearchHelper');
            } else {
                $guestOccupancy = $params['occupancy'];
                $adultTypeErr = 0;
                $childTypeErr = 0;
                $childAgeErr = 0;
                foreach ($guestOccupancy as $occupancy) {
                    if (!isset($occupancy['adults']) || !Validate::isUnsignedInt($occupancy['adults'])) {
                        $adultTypeErr = 1;
                    }
                    if (!isset($occupancy['children']) || !Validate::isUnsignedInt($occupancy['children'])) {
                        $childTypeErr = 1;
                    } elseif ($occupancy['children']) {
                        if (!isset($occupancy['child_ages']) || ($occupancy['children'] != count($occupancy['child_ages']))) {
                            $childAgeErr = 1;
                        } else {
                            foreach ($occupancy['child_ages'] as $childAge) {
                                if (!Validate::isUnsignedInt($childAge)) {
                                    $childAgeErr = 1;
                                }
                            }
                        }
                    }
                }
                if ($adultTypeErr) {
                    $errors[] = $objModule->l('Invalid adults', 'WkRoomSearchHelper');
                }
                if ($childTypeErr) {
                    $errors[] = $objModule->l('Invalid children', 'WkRoomSearchHelper');
                }
                if ($childAgeErr) {
                    $errors[] = $objModule->l('Invalid children ages', 'WkRoomSearchHelper');
                }
            }
        }

        return $errors;
    }

    private function prepareBookingParams($params)
    {
        return array(
            'date_from' => $params['date_from'],
            'date_to' => $params['date_to'],
            'hotel_id' => $params['hotel_cat_id'],
            'id_room_type' => isset($params['id_room_type']) ? $params['id_room_type'] : 0,
            'occupancy' => isset($params['occupancy']) ? $params['occupancy'] : array(),
            'only_search_data' => isset($params['only_search_data']) ? $params['only_search_data'] : 0,
            'search_available' => isset($params['search_available']) ? $params['search_available'] : 1,
            'search_partial' => isset($params['search_partial']) ? $params['search_partial'] : 0,
            'search_booked' => isset($params['search_booked']) ? $params['search_booked'] : 0,
            'search_unavai' => isset($params['search_unavai']) ? $params['search_unavai'] : 0,
            'hotel_cat_id' => isset($params['hotel_cat_id']) ? $params['hotel_cat_id'] : 0,
            'location_category_id' => isset($params['location_category_id']) ? $params['location_category_id'] : 0,
            'max_order_date' => isset($params['max_order_date']) ? $params['max_order_date'] : null,
        );
    }
}