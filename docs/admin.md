# Admin Directory Summary

The `admin` directory contains the core files for the PrestaShop back office. Here's a summary of the key highlights:

*   **Initialization and Configuration:**
    *   `init.php`: This is a crucial file that initializes the back-office environment. It handles employee authentication, sets up the database connection, aconfigsures the context (shop, language, currency), and loads necessary translations.
    *   `config.inc.php` (included from the root): This file contains the main configuration settings for the PrestaShop installation, including database credentials and other critical parameters.
    *   `functions.php`: A collection of utility functions used throughout the admin panel, such as date formatting, path generation, and version checking.

*   **Routing and Controllers:**
    *   `index.php`: The main entry point for the admin panel. It includes the necessary files, handles some basic checks, and then dispatches the request to the appropriate controller using the `Dispatcher` class.
    *   `ajax-tab.php` and `ajax.php`: These files handle AJAX requests from the back office. They are responsible for processing asynchronous actions like updating product information, fetching data for dynamic elements, and handling notifications.
    *   `GetFileController`: This controller is responsible for handling file downloads from the admin panel.

*   **User Authentication and Security:**
    *   `login.php` and `password.php`: These files redirect to the `AdminLogin` controller, which manages the login process and password recovery for employees.
    *   The `init.php` file ensures that only logged-in employees can access the back office. It checks for a valid cookie and redirects to the login page if the user is not authenticated.
    *   `backup.php`: This file handles the creation and download of database backups, with security checks to ensure that only authorized users can access them.

*   **Product and Data Management:**
    *   `ajax_products_list.php`: This file is used to search for products asynchronously, for example, when adding products to a pack or a category. It supports various filters and exclusions.
    *   `cron_currency_rates.php`: A script that can be run as a cron job to automatically update currency exchange rates.
    *   `searchcron.php`: A script for re-indexing the product search, which can also be run as a cron job.

*   **View and Template Rendering:**
    *   `header.inc.php` and `footer.inc.php`: These files are responsible for rendering the header and footer of the admin panel pages. They use the Smarty templating engine to generate the HTML.
    *   `displayImage.php`: This file is used to display images that have been uploaded to the store, such as product images.
    *   `drawer.php` and `grider.php`: These files are used to generate dynamic content, such as graphs and grids, for modules in the back office.

*   **PDF Generation:**
    *   `pdf.php`: This file is responsible for generating various PDF documents, such as invoices, delivery slips, and order slips. It uses the `AdminPdfController` to handle the PDF generation process.

In summary, the `admin` directory is the heart of the PrestaShop back office, providing the functionality for store management, data processing, and user interaction. It follows a classic PHP application structure with clear separation of concerns for initialization, routing, data handling, and view rendering.
