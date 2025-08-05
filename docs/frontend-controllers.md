# Frontend Controllers Research

This document summarizes the key functionalities of several frontend controllers related to room booking within the PrestaShop application.

## 1. `CartController.php`

*   **Path:** `/Users/cristinaavila/Developer/Shockoe/booking-poc/booking-engine/controllers/front/CartController.php`
*   **Key Features:**
    *   **Adding/Updating Booking Products:** Manages adding rooms to the cart, including validation of occupancy (adults, children, child ages), check-in/check-out dates, and availability checks using `HotelBookingDetail::dataForFrontSearch()`. It updates booking-specific data in the cart via `HotelCartBookingData::updateCartBooking()`.
    *   **Deleting Booking Products:** Handles the removal of rooms from the cart, utilizing `HotelCartBookingData::deleteCartBookingData()` and updating room availability.
    *   **Hotel-Specific Logic:** Uses `id_hotel` and `booking_product` flags to differentiate and process hotel/room-specific products.

## 2. `OrderController.php`

*   **Path:** `/Users/cristinaavila/Developer/Shockoe/booking-poc/booking-engine/controllers/front/OrderController.php`
*   **Key Features:**
    *   **Checkout Flow Management:** Orchestrates the entire checkout process, guiding the user through multiple steps (summary, addresses, delivery, payment).
    *   **Cart to Order Conversion:** Responsible for validating the cart content (which would include booking products) and preparing it for order creation.
    *   **Address and Delivery Handling:** Manages the selection and processing of delivery and invoice addresses, and delivery options.
    *   **Payment Integration:** Assigns payment methods for the order.
    *   **Customer Authentication Check:** Ensures the customer is logged in or redirects them to authentication.

## 3. `OrderConfirmationController.php`

*   **Path:** `/Users/cristinaavila/Developer/Shockoe/booking-poc/booking-engine/controllers/front/OrderConfirmationController.php`
*   **Key Features:**
    *   **Order Details Retrieval:** Fetches and validates order details using `id_cart`, `id_module`, `id_order`, and `secure_key`.
    *   **Hotel Booking Data Display:** Contains extensive logic to retrieve and process hotel-specific booking information, including room types, dates, adults, children, additional services, and pricing, for display on the confirmation page.
    *   **Guest/Customer Handling:** Differentiates between guest and registered customers for redirection and data display.

## 4. `AuthController.php`

*   **Path:** `/Users/cristinaavila/Developer/Shockoe/booking-poc/booking-engine/controllers/front/AuthController.php`
*   **Key Features:**
    *   **Customer Login:** Handles user authentication, validating credentials and logging in existing customers.
    *   **Customer Registration:** Manages the creation of new customer accounts, including validation of input fields and saving customer data.
    *   **Guest Checkout:** Supports guest checkout functionality, allowing users to proceed with an order without creating a full account.
    *   **Account Transformation:** Provides functionality to transform a guest account into a full customer account.
