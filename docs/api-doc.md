# Prestashop API Documentation

This document provides a summary of the RESTful API functionality, managed entities, and the integration flow of the Prestashop web service.

## RESTful API Functionality

The Prestashop web service provides a RESTful API that allows for Create, Read, Update, and Delete (CRUD) operations on various store resources. The API supports the following HTTP methods:

*   **GET:** Retrieve a list of resources or a specific resource.
*   **POST:** Create a new resource.
*   **PUT:** Update an existing resource.
*   **DELETE:** Delete a resource.
*   **HEAD:** Retrieve the headers for a resource.

### Authentication

Authentication is handled via HTTP Basic Authentication. A 32-character authentication key must be provided as the username for each request. No password is required. The key can also be sent as a `ws_key` GET parameter.

### Data Format

The API supports both XML and JSON data formats. The desired format can be specified using one of the following methods:

*   **GET parameter:** `output_format=JSON` or `io_format=JSON`
*   **HTTP Header:** `Output-Format: JSON` or `Io-Format: JSON`

If no format is specified, the API defaults to XML.

## Entities Managed by the API

The following is a list of entities (resources) that can be managed through the API. The availability of each resource and the allowed methods depend on the permissions configured for the authentication key being used.

*   `addresses`
*   `bookings`
*   `carriers`
*   `carts`
*   `cart_rules`
*   `categories`
*   `combinations`
*   `configurations`
*   `contacts`
*   `countries`
*   `currencies`
*   `customers`
*   `customer_threads`
*   `customer_messages`
*   `deliveries`
*   `groups`
*   `guests`
*   `images`
*   `image_types`
*   `languages`
*   `manufacturers`
*   `order_carriers`
*   `order_details`
*   `order_discounts`
*   `order_histories`
*   `order_invoices`
*   `orders`
*   `order_payments`
*   `order_states`
*   `order_slip`
*   `price_ranges`
*   `room_type_features`
*   `room_type_feature_values`
*   `room_type_options`
*   `room_type_option_values`
*   `room_types`
*   `services`
*   `states`
*   `stores`
*   `suppliers`
*   `tags`
*   `translated_configurations`
*   `weight_ranges`
*   `zones`
*   `employees`
*   `search`
*   `content_management_system`
*   `shops`
*   `shop_groups`
*   `taxes`
*   `stock_movements`
*   `stock_movement_reasons`
*   `warehouses`
*   `stocks`
*   `stock_availables`
*   `warehouse_product_locations`
*   `supply_orders`
*   `supply_order_details`
*   `supply_order_states`
*   `supply_order_histories`
*   `supply_order_receipt_histories`
*   `product_suppliers`
*   `tax_rules`
*   `tax_rule_groups`
*   `specific_prices`
*   `specific_price_rules`
*   `shop_urls`
*   `product_customization_fields`
*   `customizations`

## API Integration Summary

### What it Receives

*   **URL:** The API endpoint, including the resource name (e.g., `/api/products/1`).
*   **HTTP Method:** `GET`, `POST`, `PUT`, `DELETE`, or `HEAD`.
*   **Authentication:** A valid 32-character API key.
*   **Headers:** Optional headers to specify the data format (e.g., `Output-Format: JSON`).
*   **Parameters:** GET parameters for filtering, sorting, and pagination (e.g., `?display=[id,name]&sort=id_ASC&limit=10`).
*   **Body:** For `POST` and `PUT` requests, a payload in XML or JSON format containing the resource data.

### Flow Description

1.  An HTTP request is sent to the web service endpoint (e.g., `https://your-shop.com/api/<resource>`).
2.  The `webservice/dispatcher.php` script receives the request.
3.  The system authenticates the request using the provided API key.
4.  It verifies that the web service is enabled, the key is active, and the key has the necessary permissions for the requested resource and method.
5.  The URL is parsed to identify the resource and any specific resource ID.
6.  The `WebserviceRequest` class is instantiated to handle the request.
7.  Based on the HTTP method, the corresponding action is taken:
    *   **GET/HEAD:** Retrieves the requested resource(s) and applies any specified filters, sorting, or pagination.
    *   **POST:** Creates a new resource using the data from the request body.
    *   **PUT:** Updates an existing resource with the data from the request body.
    *   **DELETE:** Deletes the specified resource(s).
8.  For certain resources like `images` and `search`, a specific management class handles the request.
9.  The system generates a response in the requested format (XML or JSON).

### What it Returns

*   **Success:**
    *   For `GET` requests, it returns the requested data in XML or JSON format.
    *   For `POST` requests, it returns the newly created resource with a `201 Created` status.
    *   For `PUT` requests, it returns the updated resource with a `200 OK` status.
    *   For `DELETE` requests, it returns an empty response with a `200 OK` status.
*   **Error:**
    *   If an error occurs (e.g., authentication failure, invalid resource, missing permissions), it returns an error message in XML or JSON format with an appropriate HTTP status code (e.g., `401 Unauthorized`, `404 Not Found`).
