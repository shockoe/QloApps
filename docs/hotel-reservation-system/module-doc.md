# External Hotel Reservation System Module Architecture

This document outlines the architecture and key functionalities of the `externalhotelreservationsystem` PrestaShop module, which extends the core booking functionality with an external API webservice.

## 1. Module Overview

The `externalhotelreservationsystem` module provides a robust API interface for external systems to interact with the hotel reservation system. It focuses on managing room availability, cart operations, reservation creation, and retrieving booking details, while ensuring data consistency and preventing conflicts through a dedicated room locking mechanism.

## 2. Key Components and Their Responsibilities

### 2.1. `externalhotelreservationsystem.php` (Main Module File)

*   **Module Definition:** Defines the module's basic information (name, version, author, display name, description).
*   **Installation:**
    *   Registers necessary hooks (`addWebserviceResources`, `actionCartRoomSearchSqlModifier`).
    *   Initiates the creation of the `htl_room_booking_locks` database table via `ExternalRoomLockManager::createLocksTable()`.
*   **Webservice Integration:** Implements `hookAddWebserviceResources` to expose a new webservice resource named `external`, delegating its management to `WebserviceSpecificManagementExternal`.
*   **Room Search Modification:** Implements `hookActionCartRoomSearchSqlModifier` to modify SQL queries for room availability searches, ensuring that admin views consider all carts (including those from the external API) for accurate availability.

### 2.2. `WebserviceSpecificManagementExternal.php` (API Dispatcher)

*   **Central API Router:** Acts as the primary entry point for all external API requests.
*   **Endpoint Routing:** The `manage()` method parses the request URL (specifically the `url` parameter) to identify the target API endpoint (e.g., `availability`, `add-to-cart`, `make-reservation`, `booking`).
*   **Delegation to Managers:** Routes requests to specialized manager classes for processing:
    *   `ExternalAvailabilityManager` for `availability` requests.
    *   `ExternalCartManager` for `add-to-cart` requests.
    *   `ExternalReservationManager` for `make-reservation` requests.
    *   `ExternalBookingManager` for `booking` details requests.
    *   `ExternalCustomerManager` for `customer-signup` requests.
    *   `ExternalCustomerLoginManager` for `customer-login` requests.
*   **HTTP Method Enforcement:** Ensures that each endpoint is accessed with the correct HTTP method (GET or POST).
*   **Error Handling:** Provides a standardized `errorResponse()` method for consistent API error reporting.
*   **Logging:** Includes a `logMessage()` utility for debugging and tracking API requests and responses.

### 2.3. `ExternalAvailabilityManager.php` (Room Availability)

*   **API Endpoint:** Handles the `availability` endpoint.
*   **Input Validation:** Validates `hotel_id`, `check_in`, `check_out`, and optional `adults`, `children`, `room_type`.
*   **Core Availability Logic:**
    *   Utilizes PrestaShop's `HotelBookingDetail` to query room availability.
    *   **Crucially, it includes logic to exclude rooms that are currently in *any* shopping cart across the system (`getRoomsInAllCarts` and `filterOutCartRooms` methods) to provide real-time, accurate availability and prevent double-bookings.**
    *   Calculates pricing using `HotelRoomTypeFeaturePricing`.
*   **Response:** Returns a structured JSON response with search criteria, hotel information, and a list of available rooms including their details and pricing.

### 2.4. `ExternalCartManager.php` (Add to Cart)

*   **API Endpoint:** Handles the `add-to-cart` endpoint.
*   **Input Validation:** Validates `hotel_id`, `room_id`, `check_in`, `check_out`, `adults`, **`customer_id`**, and **`secure_key`**.
*   **Customer & Cart Management:**
    *   **Validates the provided `customer_id` and `secure_key` against an existing customer account.**
    *   Sets the PrestaShop `Context` to the authenticated customer.
    *   Loads an existing cart for the customer or creates a new one, ensuring it's associated with the validated customer.
    *   **Anonymous or guest cart creation is no longer permitted.**
*   **Room Locking Integration:**
    *   **Enforces a single booking per cart by checking if the cart is empty before adding a new room.**
*   **Integrates directly with `ExternalRoomLockManager` to acquire a temporary lock on the selected room *before* adding it to the cart.** This is vital for preventing race conditions in a multi-channel booking environment.
    *   Releases the lock immediately upon successful cart addition or if an error occurs.
*   **Cart Addition:** Uses `HotelCartBookingData::addCartBookingData()` to add the room to the PrestaShop cart.
*   **Response:** Provides a JSON response with cart ID, customer ID, booking details, a `cart_token`, and room lock information.

### 2.5. `ExternalReservationManager.php` (Reservation Creation)

*   **API Endpoint:** Handles the `make-reservation` endpoint.
*   **Input Validation:** Requires `id_cart`, `customer_id`, `secure_key`, `payment_method`, and `guest_details`.
*   **Customer & Cart Management:**
    *   Validates the provided `customer_id` and `secure_key`.
    *   Loads the `Cart` and `Customer` objects and sets them in the PrestaShop `Context`.
    *   **Automated Address Handling:** Automatically retrieves the customer's default address. If no default address exists, a minimal one is created and assigned to the cart.
*   **Guest Details Management:**
    *   Validates and processes `guest_details` (title, first name, last name, phone, email, etc.).
    *   Loads or creates a `CustomerGuestDetail` object and links it to the cart.
*   **Order Creation:** Uses the `bankwire` module (as a proxy for "Pay at Location") to validate the order and create a new PrestaShop order. If no active carriers are found, a default "Hotel Reservation" carrier is automatically created and assigned to the cart. This action triggers the original `hotelreservationsystem` module's hooks to create the final booking records, ensuring a consistent and stable workflow.
*   **Hotel Booking Detail Creation:** After the PrestaShop order is created, the system generates the specific hotel booking records. It correctly maps each booking to its corresponding order line item to ensure data integrity, even when multiple room types are booked in a single order.
*   **External Reference:** Generates a unique `booking_id` and stores it in `htl_external_booking_refs` for external tracking.
*   **Response:** Returns a comprehensive JSON response with all reservation details, including hotel, room, dates, occupancy, customer, pricing, and payment status.

### 2.6. `ExternalBookingManager.php` (Booking Details Retrieval)

*   **API Endpoint:** Handles the `booking` endpoint.
*   **Input Validation:** Requires a `booking_id`.
*   **Booking Retrieval:** Queries `htl_external_booking_refs` to find the corresponding PrestaShop order and loads its details.
*   **Response:** Formats and returns a detailed JSON response containing all relevant booking information (hotel, room, dates, occupancy, customer, pricing, payment, etc.).

### 2.7. `ExternalCustomerManager.php` (Customer Signup)

*   **API Endpoint:** Handles the `customer-signup` endpoint.
*   **Input Validation:** Validates `email`, `passwd`, `firstname`, `lastname`, and other optional customer fields.
*   **Customer Creation:** Creates a new PrestaShop customer account, reusing logic from `AuthController.php`.
*   **Context and Cart Update:** Updates the PrestaShop `Context` with the newly created customer and associates any existing cart with this customer.
*   **Confirmation Email:** Sends a confirmation email to the customer if configured in PrestaShop.
*   **Response:** Returns a JSON response indicating success or failure, along with the new customer's ID and basic details upon successful creation.

### 2.8. `ExternalCustomerLoginManager.php` (Customer Login)

*   **API Endpoint:** Handles the `customer-login` endpoint.
*   **Input Validation:** Validates `email` and `passwd`.
*   **Customer Authentication:** Authenticates the customer against PrestaShop's user database.
*   **Context and Cart Management:** Updates the PrestaShop `Context` with the logged-in customer and ensures a cart is associated with them (either loads an existing one or creates a new one).
*   **Information Retrieval:** Gathers essential customer and cart information (e.g., customer ID, email, cart ID, cart totals, products in cart) relevant for subsequent booking operations.
*   **Response:** Returns a JSON response with success status, customer details, and cart information.

### 2.9. `ExternalRoomLockManager.php` (Concurrency Control)

*   **Purpose:** Prevents double-bookings and manages concurrency across all booking channels (admin, frontend, external API).
*   **Locking Mechanism:**
    *   `lockRoom()`: Acquires a temporary lock on a specific room for a given date range.
    *   `lockMultipleRooms()`: Atomically locks multiple rooms using database transactions.
    *   `checkRoomLock()`: Checks if a room is currently locked.
*   **Lock Management:**
    *   `releaseLock()`: Explicitly releases a room lock.
    *   `cleanupExpiredLocks()`: Automatically deactivates expired locks.
*   **Lock Duration:** Defines `LOCK_DURATION` (5 minutes) and `ADMIN_LOCK_DURATION` (10 minutes).
*   **Lock Tracking:** Records `locked_by_type` (admin, frontend, external_api) and `locked_by_identifier` (user ID, API key).
*   **Database Table:** Manages the `htl_room_booking_locks` table to store lock information.

### 2.8. `ExternalApiValidator.php` (API Input Validation)

*   **Purpose:** Provides reusable validation methods for common API input parameters.
*   **Key Methods:**
    *   `validateDateRange()`: Ensures check-in and check-out dates are valid and in the correct order.
    *   `validateOccupancy()`: Validates the number of adults and children.

## 3. Database Tables

The module introduces custom database tables to support its functionality:

*   `ps_htl_room_booking_locks`: Stores information about active and expired room locks.
*   `ps_htl_external_cart_tokens`: Stores temporary tokens linking external cart operations to PrestaShop carts.
*   `ps_htl_external_booking_refs`: Stores references between external booking IDs and PrestaShop order IDs.

## 4. Interaction Flow (Example: External Booking)

1.  **Customer Signup (Optional):** An external system can call the `customer-signup` endpoint (handled by `ExternalCustomerManager`) to create a new customer account. This allows users to register before proceeding with booking.
2.  **Customer Login (Optional):** An external system can call the `customer-login` endpoint (handled by `ExternalCustomerLoginManager`) to authenticate an existing customer. Upon successful login, relevant customer and cart information is returned, which can be used for subsequent booking operations.
3.  **Search Availability:** An external system calls the `availability` endpoint (handled by `ExternalAvailabilityManager`) to find available rooms. The system ensures that rooms currently in *any* cart are not shown as available.
4.  **Add to Cart:** The external system selects a room and calls the `add-to-cart` endpoint (handled by `ExternalCartManager`).
    *   `ExternalCartManager` first attempts to acquire a lock on the specific room for the given dates using `ExternalRoomLockManager`.
    *   If successful, the room is added to a PrestaShop cart, and the lock is immediately released.
    *   A `cart_token` is returned to the external system.
5.  **Make Reservation:** The external system proceeds to finalize the booking by calling the `make-reservation` endpoint (handled by `ExternalReservationManager`), providing the `cart_token` and payment details.
    *   `ExternalReservationManager` retrieves the cart and creates a PrestaShop order. This triggers the standard `hotelreservationsystem` hooks, which then create the final hotel booking records.
    *   An external `booking_id` is generated and stored for future reference.
6.  **Retrieve Booking Details:** The external system can later query the `booking` endpoint (handled by `ExternalBookingManager`) using the `booking_id` to retrieve the full details of the confirmed reservation.

This architecture ensures a clear separation of concerns, robust validation, and effective concurrency management for external integrations with the PrestaShop hotel reservation system.
