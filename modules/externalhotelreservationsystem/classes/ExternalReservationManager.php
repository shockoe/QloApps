<?php
require_once(dirname(__FILE__).'/ExternalApiValidator.php');

class ExternalReservationManager
{
    public function makeReservation($params)
    {
        try {
            // 1. Input Validation
            if (empty($params['id_cart'])) {
                throw new InvalidArgumentException('id_cart is required');
            }
            if (empty($params['customer_id'])) {
                throw new InvalidArgumentException('customer_id is required');
            }
            if (empty($params['secure_key'])) {
                throw new InvalidArgumentException('secure_key is required');
            }
            if (empty($params['payment_method'])) {
                throw new InvalidArgumentException('payment_method is required');
            }
            if (empty($params['guest_details'])) {
                throw new InvalidArgumentException('guest_details are required');
            }

            // Validate guest_details
            $guest_details = $params['guest_details'];
            if (empty($guest_details['firstname']) || !Validate::isName($guest_details['firstname'])) {
                throw new InvalidArgumentException('Guest firstname is required and must be valid.');
            }
            if (empty($guest_details['lastname']) || !Validate::isName($guest_details['lastname'])) {
                throw new InvalidArgumentException('Guest lastname is required and must be valid.');
            }
            if (empty($guest_details['email']) || !Validate::isEmail($guest_details['email'])) {
                throw new InvalidArgumentException('Guest email is required and must be valid.');
            }
            if (empty($guest_details['phone']) || !Validate::isPhoneNumber($guest_details['phone'])) {
                throw new InvalidArgumentException('Guest phone is required and must be valid.');
            }
            if (isset($guest_details['birthday']) && !Validate::isBirthDate($guest_details['birthday'])) {
                throw new InvalidArgumentException('Guest birthday must be a valid date (YYYY-MM-DD).');
            }

            // Include PrestaShop config and required classes
            require_once(dirname(__FILE__).'/../../../config/config.inc.php');
            require_once(_PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelCartBookingData.php');
            require_once(_PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelBookingDetail.php');
            require_once(_PS_ROOT_DIR_ . '/classes/CustomerGuestDetail.php');
            require_once(_PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelBranchInformation.php');
            require_once(_PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelRoomInformation.php');
            require_once(_PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelRoomType.php');
            require_once(_PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelHelper.php');
            require_once(_PS_MODULE_DIR_.'bankwire/bankwire.php');

            // 2. Set PrestaShop Context
            $context = Context::getContext();

            $customer = new Customer((int)$params['customer_id']);
            if (!Validate::isLoadedObject($customer) || $customer->secure_key !== $params['secure_key']) {
                throw new Exception('Invalid customer ID or secure key.');
            }

            $cart = new Cart((int)$params['id_cart']);
            if (!Validate::isLoadedObject($cart) || (int)$cart->id_customer !== (int)$customer->id) {
                throw new Exception('Cart not found or does not belong to the provided customer.');
            }

            $context->customer = $customer;
            $context->cart = $cart;
            if (!Validate::isLoadedObject($context->language)) {
                $context->language = new Language(Configuration::get('PS_LANG_DEFAULT'));
            }
            if (!Validate::isLoadedObject($context->shop)) {
                $context->shop = new Shop(Configuration::get('PS_SHOP_DEFAULT'));
            }
            if (!Validate::isLoadedObject($context->currency)) {
                $context->currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
            }

            // 3. Customer Address Handling (Automated)
            $id_address_delivery = (int)Db::getInstance()->getValue(
                'SELECT id_address FROM '._DB_PREFIX_.'address WHERE id_customer = '.(int)$customer->id.' AND `active` = 1 ORDER BY date_add DESC'
            );
            $id_address_invoice = $id_address_delivery; // Default to same for invoice

            if (!$id_address_delivery) {
                $new_address = new Address();
                $new_address->id_customer = (int)$customer->id;
                $new_address->alias = 'Booking Address for ' . substr($customer->firstname, 0, 1) . '. ' . $customer->lastname;
                $new_address->firstname = $customer->firstname;
                $new_address->lastname = $customer->lastname;
                $new_address->address1 = 'N/A'; // Placeholder
                $new_address->postcode = '00000'; // Placeholder
                $new_address->city = 'N/A'; // Placeholder
                $new_address->id_country = (int)Configuration::get('PS_COUNTRY_DEFAULT'); // Use default country
                $new_address->phone = $customer->phone; // Use customer's phone from signup/login if available
                $new_address->phone_mobile = $customer->phone; // Use customer's phone from signup/login if available

                if (!$new_address->add()) {
                    throw new Exception('Failed to create default address for customer.');
                }
                $id_address_delivery = (int)$new_address->id;
                $id_address_invoice = $id_address_delivery;
            }

            $context->cart->id_address_delivery = $id_address_delivery;
            $context->cart->id_address_invoice = $id_address_invoice;
            $context->cart->id_currency = $context->currency->id; // Explicitly set cart currency

            // Set a default carrier for the cart if not already set
            if (!$context->cart->id_carrier) {
                $default_carrier = new Carrier(Configuration::get('PS_CARRIER_DEFAULT'));
                if (Validate::isLoadedObject($default_carrier)) {
                    $context->cart->id_carrier = (int)$default_carrier->id;
                } else {
                    // Fallback if default carrier is not found, try to find any active carrier
                    $carriers = Carrier::getCarriers($context->language->id, true, false, false, null, PS_CARRIER_MODE_ALL);
                    if (!empty($carriers)) {
                        $context->cart->id_carrier = (int)$carriers[0]['id_carrier'];
                    } else {
                        throw new Exception('No active carriers found. Please configure a carrier in PrestaShop.');
                    }
                }
            }
            $context->cart->update(); // Save the updated address IDs and currency to the cart

            // 4. Guest Details Management
            $id_customer_guest_detail = CustomerGuestDetail::getCustomerGuestByEmail($guest_details['email'], $customer->id);
            if ($id_customer_guest_detail) {
                $objCustomerGuestDetail = new CustomerGuestDetail($id_customer_guest_detail);
            } else {
                $objCustomerGuestDetail = new CustomerGuestDetail();
            }

            $objCustomerGuestDetail->id_gender = isset($guest_details['id_gender']) ? (int)$guest_details['id_gender'] : 0;
            $objCustomerGuestDetail->firstname = $guest_details['firstname'];
            $objCustomerGuestDetail->lastname = $guest_details['lastname'];
            $objCustomerGuestDetail->email = $guest_details['email'];
            $objCustomerGuestDetail->phone = $guest_details['phone'];
            $objCustomerGuestDetail->id_customer = (int)$customer->id;
            if (isset($guest_details['birthday'])) {
                $objCustomerGuestDetail->birthday = $guest_details['birthday'];
            }
            if (isset($guest_details['company'])) {
                $objCustomerGuestDetail->company = $guest_details['company'];
            }
            if (isset($guest_details['address1'])) {
                $objCustomerGuestDetail->address1 = $guest_details['address1'];
            }
            if (isset($guest_details['postcode'])) {
                $objCustomerGuestDetail->postcode = $guest_details['postcode'];
            }
            if (isset($guest_details['city'])) {
                $objCustomerGuestDetail->city = $guest_details['city'];
            }
            if (isset($guest_details['id_country'])) {
                $objCustomerGuestDetail->id_country = (int)$guest_details['id_country'];
            } else {
                $objCustomerGuestDetail->id_country = (int)Configuration::get('PS_COUNTRY_DEFAULT');
            }
            if (isset($guest_details['id_state'])) {
                $objCustomerGuestDetail->id_state = (int)$guest_details['id_state'];
            }
            if (isset($guest_details['other'])) {
                $objCustomerGuestDetail->other = $guest_details['other'];
            }

            if (!$objCustomerGuestDetail->save()) {
                throw new Exception('Failed to save guest details.');
            }

            // Link guest details to cart
            CustomerGuestDetail::deleteCustomerGuestInCart($cart->id);
            $objCustomerGuestDetail->saveCustomerGuestInCart($cart->id, $objCustomerGuestDetail->id);

            // 5. Payment Module Integration ("Pay at Location")
            $payment_module = Module::getInstanceByName('bankwire');
            if (!Validate::isLoadedObject($payment_module) || !$payment_module->active) {
                throw new Exception('Bank Wire payment module is not active or installed.');
            }

            $id_order_state = Configuration::get('PS_OS_AWAITING_PAYMENT');
            $total = (float)$context->cart->getOrderTotal(true, Cart::BOTH);
            $payment_method_name = $params['payment_method'];

            $payment_module->validateOrder(
                (int)$context->cart->id,
                (int)$id_order_state,
                $total,
                $payment_method_name,
                null,
                array(),
                (int)$context->cart->id_currency,
                false,
                $context->cart->secure_key
            );

            $order = new Order((int)$payment_module->currentOrder);

            if (!Validate::isLoadedObject($order)) {
                throw new Exception('Failed to create order after payment validation.');
            }

            if (!Validate::isLoadedObject($order)) {
                throw new Exception('Failed to create order after payment validation.');
            }

            // 6. Post-Order Creation: Manually create hotel bookings from cart data
            require_once(_PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelRoomInformation.php');
            require_once(_PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelBranchInformation.php');
            require_once(_PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelRoomType.php');
            require_once(_PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelHelper.php');

            $cart_booking_data = HotelCartBookingData::getBookingDataByCartId($cart->id);

            if (empty($cart_booking_data)) {
                throw new Exception('No hotel booking data found in the cart.');
            }

            foreach ($cart_booking_data as $booking_data) {
                $objBookingDetail = new HotelBookingDetail();

                $objBookingDetail->id_product = (int)$booking_data['id_product'];
                $objBookingDetail->id_order = (int)$order->id;
                $objBookingDetail->id_order_detail = (int)$order->product_list[0]['id_order_detail']; // Assuming single product in cart for simplicity, needs refinement for multiple
                $objBookingDetail->id_cart = (int)$cart->id;
                $objBookingDetail->id_room = (int)$booking_data['id_room'];
                $objBookingDetail->id_hotel = (int)$booking_data['id_hotel'];
                $objBookingDetail->id_customer = (int)$customer->id;
                $objBookingDetail->booking_type = HotelBookingDetail::ALLOTMENT_AUTO; // Assuming auto allotment
                $objBookingDetail->id_status = HotelBookingDetail::STATUS_ALLOTED; // Assuming allotted status
                $objBookingDetail->comment = $booking_data['comment'];
                $objBookingDetail->check_in = $booking_data['date_from'];
                $objBookingDetail->check_out = $booking_data['date_to'];
                $objBookingDetail->date_from = $booking_data['date_from'];
                $objBookingDetail->date_to = $booking_data['date_to'];
                $objBookingDetail->total_price_tax_excl = (float)$booking_data['total_price_tax_excl'];
                $objBookingDetail->total_price_tax_incl = (float)$booking_data['total_price_tax_incl'];
                $objBookingDetail->total_paid_amount = (float)$order->total_paid; // Assuming full payment for now
                $objBookingDetail->is_back_order = 0;
                $objBookingDetail->is_refunded = 0;
                $objBookingDetail->is_cancelled = 0;

                // Populate hotel and room information
                $hotel_info = new HotelBranchInformation((int)$booking_data['id_hotel']);
                $room_info = new HotelRoomInformation((int)$booking_data['id_room']);
                $room_type_info = new HotelRoomType((int)$booking_data['id_product']);

                $objBookingDetail->room_num = $room_info->room_num;
                $objBookingDetail->room_type_name = $room_type_info->room_type_name[$context->language->id];
                $objBookingDetail->hotel_name = $hotel_info->hotel_name;
                $objBookingDetail->city = $hotel_info->city;
                $objBookingDetail->state = (new State($hotel_info->id_state))->name;
                $objBookingDetail->country = (new Country($hotel_info->id_country))->name[$context->language->id];
                $objBookingDetail->zipcode = $hotel_info->postcode;
                $objBookingDetail->phone = $hotel_info->phone;
                $objBookingDetail->email = $hotel_info->email;
                $objBookingDetail->check_in_time = $hotel_info->check_in_time;
                $objBookingDetail->check_out_time = $hotel_info->check_out_time;
                $objBookingDetail->planned_check_out = $booking_data['date_to'];
                $objBookingDetail->adults = (int)$booking_data['adults'];
                $objBookingDetail->children = (int)$booking_data['children'];
                $objBookingDetail->child_ages = $booking_data['child_ages'];

                if (!$objBookingDetail->add()) {
                    throw new Exception('Failed to create hotel booking detail.');
                }
            }

            // Invalidate the old cart token
            Db::getInstance()->delete('htl_external_cart_tokens', 'id_cart = '.(int)$cart->id);

            // Create a new cart for the customer
            $new_cart = new Cart();
            $new_cart->id_shop_group = (int)$context->shop->id_shop_group;
            $new_cart->id_shop = (int)$context->shop->id;
            $new_cart->id_customer = (int)$customer->id;
            $new_cart->id_currency = (int)$context->currency->id;
            $new_cart->id_lang = (int)$context->language->id;
            $new_cart->secure_key = $customer->secure_key;
            if (!$new_cart->add()) {
                throw new Exception('Failed to create a new cart after order creation.');
            }

            // Update context and cookie with the new cart
            $context->cart = $new_cart;
            $context->cookie->id_cart = (int)$new_cart->id;
            $context->cookie->write(); // Ensure cookie is updated

            // Generate a new cart token for the new cart
            $new_cart_token = md5($new_cart->id . time() . rand());
            Db::getInstance()->insert('htl_external_cart_tokens', array(
                'cart_token' => pSQL($new_cart_token),
                'id_cart' => (int)$new_cart->id,
                'expires_at' => date('Y-m-d H:i:s', time() + (60 * 60 * 24)) // Token valid for 24 hours
            ));

            // 7. Response
            $response = $this->formatResponse($order, $booking_id);
            $response['new_cart_token'] = $new_cart_token;

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
