<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class ExternalEmptyCartManager
{
    public function emptyCart($params)
    {
        try {
            // 1. Input Validation
            if (empty($params['cart_id'])) {
                throw new InvalidArgumentException('cart_id is required');
            }
            if (empty($params['customer_id'])) {
                throw new InvalidArgumentException('customer_id is required');
            }
            if (empty($params['secure_key'])) {
                throw new InvalidArgumentException('secure_key is required');
            }

            // Load required classes
            require_once(_PS_MODULE_DIR_.'hotelreservationsystem/classes/HotelCartBookingData.php');

            $context = Context::getContext();

            // 2. Security Validation
            $customer = new Customer((int)$params['customer_id']);
            if (!Validate::isLoadedObject($customer) || $customer->secure_key !== $params['secure_key']) {
                throw new Exception('Invalid customer ID or secure key.');
            }

            $cart = new Cart((int)$params['cart_id']);
            if (!Validate::isLoadedObject($cart) || (int)$cart->id_customer !== (int)$customer->id) {
                throw new Exception('Cart not found or does not belong to the provided customer.');
            }

            // 3. Delete Hotel Booking Data and PS Cart Products
            $objCartBookingData = new HotelCartBookingData();
            if (!$objCartBookingData->deleteCartBookingData($cart->id)) {
                throw new Exception('Failed to delete hotel booking data from cart.');
            }

            // 4. Delete the Cart itself
            if (!$cart->delete()) {
                throw new Exception('Failed to delete the cart.');
            }

            // 5. Create a new, empty cart for the user to continue
            if (!Validate::isLoadedObject($context->shop)) {
                $context->shop = new Shop(Configuration::get('PS_SHOP_DEFAULT'));
            }
            $new_cart = new Cart();
            $new_cart->id_shop_group = (int)$context->shop->id_shop_group;
            $new_cart->id_shop = (int)$context->shop->id;
            $new_cart->id_customer = (int)$customer->id;
            $new_cart->id_currency = (int)$context->currency->id;
            $new_cart->id_lang = (int)$context->language->id;
            $new_cart->secure_key = $customer->secure_key;
            if (!$new_cart->add()) {
                throw new Exception('Failed to create a new cart for the customer.');
            }
            
            // Update context with the new cart
            $context->cart = $new_cart;
            $context->cookie->id_cart = (int)$new_cart->id;
            $context->cookie->write();

            return [
                'success' => true,
                'timestamp' => date('c'),
                'message' => 'Cart has been successfully emptied.',
                'new_cart_id' => $new_cart->id,
            ];

        } catch (InvalidArgumentException $e) {
            error_log('ExternalEmptyCartManager InvalidArgumentException: ' . $e->getMessage());
            return array('success' => false, 'error' => $e->getMessage(), 'error_code' => 'INVALID_PARAMETER');
        } catch (Exception $e) {
            error_log('ExternalEmptyCartManager Exception: ' . $e->getMessage());
            return array('success' => false, 'error' => 'An unexpected error occurred: '.$e->getMessage(), 'error_code' => 'INTERNAL_ERROR');
        }
    }
}
