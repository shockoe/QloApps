<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(dirname(__FILE__).'/ExternalApiValidator.php');

class ExternalCustomerManager
{
    public function createCustomer($params)
    {
        error_log('ExternalCustomerManager: Starting createCustomer method.');
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
            error_log('ExternalCustomerManager: Context initialized.');

            $errors = [];

            // Basic validation
            if (empty($params['email']) || !Validate::isEmail($params['email'])) {
                $errors[] = 'Invalid email address.';
            }
            if (empty($params['passwd']) || !Validate::isPasswd($params['passwd'])) {
                $errors[] = 'Invalid password.';
            }
            if (empty($params['firstname']) || !Validate::isName($params['firstname'])) {
                $errors[] = 'Invalid first name.';
            }
            if (empty($params['lastname']) || !Validate::isName($params['lastname'])) {
                $errors[] = 'Invalid last name.';
            }

            if (Customer::customerExists($params['email'])) {
                $errors[] = 'An account using this email address has already been registered.';
            }

            if (!empty($errors)) {
                error_log('ExternalCustomerManager: Initial validation failed: ' . implode(', ', $errors));
                throw new InvalidArgumentException(implode(', ', $errors));
            }
            error_log('ExternalCustomerManager: Initial validation passed.');

            $customer = new Customer();
            $customer->email = $params['email'];
            $customer->passwd = Tools::encrypt($params['passwd']);
            $customer->firstname = $params['firstname'];
            $customer->lastname = $params['lastname'];
            $customer->active = 1;
            $customer->is_guest = 0; // Always create a full customer account via API

            // Optional fields
            if (isset($params['birthday']) && Validate::isBirthDate($params['birthday'])) {
                $customer->birthday = $params['birthday'];
            }
            if (isset($params['newsletter'])) {
                $customer->newsletter = (bool)$params['newsletter'];
                if ($customer->newsletter) {
                    $customer->ip_registration_newsletter = pSQL(Tools::getRemoteAddr());
                    $customer->newsletter_date_add = date('Y-m-d H:i:s');
                }
            }
            if (isset($params['optin'])) {
                $customer->optin = (bool)$params['optin'];
            }
            if (isset($params['id_gender']) && Validate::isUnsignedId($params['id_gender'])) {
                $customer->id_gender = (int)$params['id_gender'];
            }
            error_log('ExternalCustomerManager: Customer object populated.');

            // Validate customer fields using PrestaShop's internal validation
            $validationErrors = $customer->validateController();
            if (!empty($validationErrors)) {
                error_log('ExternalCustomerManager: validateController failed: ' . implode(', ', $validationErrors));
                throw new Exception(implode(', ', $validationErrors));
            }
            error_log('ExternalCustomerManager: validateController passed.');

            error_log('ExternalCustomerManager: Attempting to add customer.');
            if (!$customer->add()) {
                error_log('ExternalCustomerManager: Failed to add customer to database.');
                throw new Exception('Failed to create customer account.');
            }
            error_log('ExternalCustomerManager: Customer added successfully. ID: ' . $customer->id);

            // Log in the newly created customer to the context
            error_log('ExternalCustomerManager: Updating context and cookie.');
            $context->customer = $customer;
            $context->cookie->id_customer = (int)$customer->id;
            $context->cookie->customer_lastname = $customer->lastname;
            $context->cookie->customer_firstname = $customer->firstname;
            $context->cookie->logged = 1;
            $context->cookie->passwd = $customer->passwd;
            $context->cookie->email = $customer->email;
            $context->cookie->is_guest = $customer->is_guest;
            $context->cookie->write();
            error_log('ExternalCustomerManager: Context and cookie updated.');

            // Update cart with new customer ID if a cart exists
            if (Validate::isLoadedObject($context->cart)) {
                error_log('ExternalCustomerManager: Updating cart with new customer ID.');
                $context->cart->id_customer = (int)$customer->id;
                $context->cart->secure_key = $customer->secure_key;
                $context->cart->update();
                error_log('ExternalCustomerManager: Cart updated.');
            }

            // Send confirmation email (optional, based on PS configuration)
            // Temporarily disabled for debugging
            /*
            if (Configuration::get('PS_CUSTOMER_CREATION_EMAIL')) {
                Mail::Send(
                    (int)$context->language->id,
                    'account',
                    Mail::l('Welcome!', (int)$context->language->id),
                    array(
                        '{firstname}' => $customer->firstname,
                        '{lastname}' => $customer->lastname,
                        '{email}' => $customer->email,
                    ),
                    $customer->email,
                    $customer->firstname.' '.$customer->lastname
                );
            }
            */
            error_log('ExternalCustomerManager: Returning success response.');
            return array(
                'success' => true,
                'message' => 'Customer account created successfully.',
                'customer_id' => (int)$customer->id,
                'email' => $customer->email,
                'firstname' => $customer->firstname,
                'lastname' => $customer->lastname,
                'timestamp' => date('c'),
            );

        } catch (InvalidArgumentException $e) {
            // Log the specific error for debugging
            error_log('ExternalCustomerManager InvalidArgumentException: ' . $e->getMessage());
            return array('success' => false, 'error' => $e->getMessage(), 'error_code' => 'INVALID_PARAMETER');
        } catch (Exception $e) {
            // Log the specific error for debugging
            error_log('ExternalCustomerManager Exception: ' . $e->getMessage());
            return array('success' => false, 'error' => 'An unexpected error occurred: '.$e->getMessage(), 'error_code' => 'INTERNAL_ERROR');
        }
    }
}
