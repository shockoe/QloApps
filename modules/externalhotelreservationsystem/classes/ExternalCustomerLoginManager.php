<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(dirname(__FILE__).'/ExternalApiValidator.php');

class ExternalCustomerLoginManager
{
    public function loginCustomer($params)
    {
        error_log('ExternalCustomerLoginManager: Starting loginCustomer method.');
        try {
            // Set up context for PrestaShop functions
            $context = Context::getContext();
            if (!isset($context->customer) || !Validate::isLoadedObject($context->customer)) {
                $context->customer = new Customer();
            }
            if (!isset($context->cart) || !Validate::isLoadedObject($context->cart)) {
                $context->cart = new Cart();
            }
            if (!isset($context->language) || !Validate::isLoadedObject($context->language)) {
                $context->language = new Language(Configuration::get('PS_LANG_DEFAULT'));
            }
            if (!isset($context->shop) || !Validate::isLoadedObject($context->shop)) {
                $context->shop = new Shop(Configuration::get('PS_SHOP_DEFAULT'));
            }
            error_log('ExternalCustomerLoginManager: Context initialized.');

            // Basic validation
            if (empty($params['email']) || !Validate::isEmail($params['email'])) {
                throw new InvalidArgumentException('Invalid email address.');
            }
            if (empty($params['passwd']) || !Validate::isPasswd($params['passwd'])) {
                throw new InvalidArgumentException('Invalid password.');
            }
            error_log('ExternalCustomerLoginManager: Initial validation passed.');

            $customer = new Customer();
            $authentication = $customer->getByEmail(trim($params['email']), trim($params['passwd']));

            if (isset($authentication->active) && !$authentication->active) {
                throw new Exception('Your account isn\'t available at this time, please contact us.');
            } elseif (!$authentication || !$customer->id) {
                throw new Exception('Authentication failed.');
            }
            error_log('ExternalCustomerLoginManager: Customer authenticated successfully. ID: ' . $customer->id);

            // Update context and cookie for the logged-in customer
            $context->updateCustomer($customer, 1);
            Hook::exec('actionAuthentication', array('customer' => $context->customer));
            error_log('ExternalCustomerLoginManager: Context and cookie updated.');

            // Gather information for booking reservation
            $customer_info = array(
                'customer_id' => (int)$customer->id,
                'email' => $customer->email,
                'firstname' => $customer->firstname,
                'lastname' => $customer->lastname,
                'secure_key' => $customer->secure_key,
            );

            error_log('ExternalCustomerLoginManager: Gathered customer info.');

            return array(
                'success' => true,
                'message' => 'Customer logged in successfully.',
                'customer_info' => $customer_info,
                'timestamp' => date('c'),
            );

        } catch (InvalidArgumentException $e) {
            error_log('ExternalCustomerLoginManager InvalidArgumentException: ' . $e->getMessage());
            return array('success' => false, 'error' => $e->getMessage(), 'error_code' => 'INVALID_PARAMETER');
        } catch (Exception $e) {
            error_log('ExternalCustomerLoginManager Exception: ' . $e->getMessage());
            return array('success' => false, 'error' => 'An unexpected error occurred: '.$e->getMessage(), 'error_code' => 'INTERNAL_ERROR');
        }
    }
}
