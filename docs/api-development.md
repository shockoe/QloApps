# Step-by-Step Guide: Creating a Custom Webservice Endpoint

This guide outlines the process to create a new webservice endpoint, for example, `/api/my_custom_endpoint`, which will return a simple JSON response `{"success":true}`.

## 1. Create the Webservice Specific Management Class

This class will contain the logic for your new endpoint.

- File Path: `classes/webservice/WebserviceSpecificManagementMyCustomEndpoint.php`
- Content:

```php

<?php

    class WebserviceSpecificManagementMyCustomEndpoint implements WebserviceSpecificManagementInterface
    {
        protected $objOutput;
        protected $output;
        protected $wsObject;

        public function manage()
        {
            // This is where your endpoint logic goes
            $response = [
                'success' => true,
                'message' => 'This is a mocked response from MyCustomEndpoint.'
            ];

            // Set appropriate headers for JSON response
            header('Content-Type: application/json');
            http_response_code(200); // OK

            $this->output = json_encode($response);
            return $this->output;
        }

        // Required methods for WebserviceSpecificManagementInterface
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
``` 

## 2. Register the Webservice Resource in the Module

You need to inform PrestaShop's webservice about your new endpoint. This is done in your custom module's main PHP file.

- File Path: `modules/availabilityendpoint/availabilityendpoint.php` (assuming you're adding it to an existing module)
- Modification: Locate the hookAddWebserviceResources() method and add an entry for your new endpoint.

```php 
public function hookAddWebserviceResources($params)
{
    return [
        'availability' => [
            'description' => 'Hotel availability search',
            'specific_management' => true,
            'specific_management_class' => 'WebserviceSpecificManagementAvailability',
        ],
        'my_custom_endpoint' => [ // <--- Add this new entry
            'description' => 'My custom endpoint',
            'specific_management' => true,
            'specific_management_class' => 'WebserviceSpecificManagementMyCustomEndpoint',
        ],
    ];
}
```

## 3. Install/Re-install the Module

For PrestaShop to recognize the new webservice resource and its associated class, the module needs to be re-installed.

- **Method 1 (Recommended):** Use the PrestaShop admin panel to uninstall and then install the Availability Endpoint module.
- **Method 2 (CLI - if admin panel is not accessible or for automation):**

1. Create temporary uninstall script:

```php
// uninstall_module.php
<?php
require_once(dirname(__FILE__).'/config/config.inc.php');
if ($module = Module::getInstanceByName('availabilityendpoint')) {
    if ($module->uninstall()) {
        echo "Module uninstalled.";
    } else {
        echo "Failed to uninstall module.";
    }
} else {
    echo "Module not found.";
}
```
2. Create temporary install script:

```php
// install_module.php
<?php
require_once(dirname(__FILE__).'/config/config.inc.php');
if ($module = Module::getInstanceByName('availabilityendpoint')) {
    if ($module->install()) {
        echo "Module installed/re-installed successfully.";
    } else {
        echo "Failed to install/re-install module.";
    }
} else {
    echo "Module not found.";
}
```

3. Execute from Docker container:

```bash
docker exec qloapps-booking-system-dev php /home/qloapps/www/hotelcommerce/uninstall_module.php
docker exec qloapps-booking-system-dev php /home/qloapps/www/hotelcommerce/install_module.php
```

4. Clean up:

```bash
rm uninstall_module.php install_module.php
```

## 4. Clear Cache

It's good practice to clear the Smarty cache after making changes to ensure the latest code is used.

- **Command:**

```bash
docker exec qloapps-booking-system-dev rm -rf /home/qloapps/www/hotelcommerce/cache/smarty/compile/* /home/qloapps/www/hotelcommerce/cache/smarty/cache/*
```

### 5. Test the Endpoint

You can now test your new endpoint using curl from your host machine.

* **Authentication:** Use your webservice key (WEB-KEY-XXXXXXXXXXXXXXXXXXXXXXXX) as the username and an empty password.
* **Command:**

```bash
curl -v -X GET -u WEB-KEY-XXXXXXXXXXXXXXXXXXXXXXXX: http://localhost:8080/api/my_custom_endpoint
```

* **Expected Successful Response:**

```json
{
    "success": true,
    "message": "This is a mocked response from MyCustomEndpoint."
}
```

This process ensures your custom webservice endpoint is properly defined, registered, and accessible.