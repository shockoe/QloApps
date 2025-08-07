# Hotel Reservation External API Development Guide

## Overview

This document provides comprehensive specifications and implementation guidelines for the new external hotel reservation API endpoints. The API is designed to enable external integrations while maintaining full compatibility with the existing PrestaShop-based hotel reservation system.

## Architecture Overview

### Current System Integration
- **Base System:** PrestaShop 1.6+ with Hotel Reservation System module
- **Existing API:** WebService API with availability endpoint
- **New Module:** `externalhotelreservationsystem` (to be created)
- **Authentication:** WebService key: `MVFDI376Y2MCWR4YWHSH145GR9VWMWYX`

### Design Principles
1. **Backward Compatibility:** All changes must maintain existing admin panel and frontend functionality
2. **RESTful Design:** Follow REST principles for predictable API behavior
3. **Stateless Operations:** Each API call should be independent
4. **Error Handling:** Comprehensive error responses with meaningful messages
5. **Security:** Proper authentication and input validation

## API Endpoints Specification

### Base URL Structure
```
https://yourdomain.com/api/external/
```

### Authentication
All endpoints require the webservice key either as:
- URL parameter: `?ws_key=MVFDI376Y2MCWR4YWHSH145GR9VWMWYX`
- Header: `Authorization: Basic base64(ws_key:)`

---

## 1. Availability Search Endpoint

### Endpoint Details
```
GET /api/external/availability
```

### Purpose
Searches for available rooms based on date range, hotel, and occupancy requirements.

### Request Parameters

#### Required Parameters
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `hotel_id` | integer | Hotel identifier | `1` |
| `check_in` | date | Check-in date (Y-m-d) | `2024-03-15` |
| `check_out` | date | Check-out date (Y-m-d) | `2024-03-17` |

#### Optional Parameters
| Parameter | Type | Description | Default | Example |
|-----------|------|-------------|---------|---------|
| `adults` | integer | Number of adults | `2` | `2` |
| `children` | integer | Number of children | `0` | `1` |
| `room_type` | integer | Specific room type filter | `0` (all) | `5` |

### Request Example
```http
GET /api/external/availability?ws_key=MVFDI376Y2MCWR4YWHSH145GR9VWMWYX&hotel_id=1&check_in=2024-03-15&check_out=2024-03-17&adults=2&children=1
```

### Response Structure

#### Success Response (200 OK)
```json
{
    "success": true,
    "timestamp": "2024-03-15T10:30:00Z",
    "search_criteria": {
        "hotel_id": 1,
        "check_in": "2024-03-15",
        "check_out": "2024-03-17",
        "occupancy": [{"adults": 2, "children": 1}],
        "room_type": 0
    },
    "hotels": [{
        "id_hotel": 1,
        "hotel_name": "Grand Plaza Hotel",
        "email": "reservations@grandplaza.com",
        "check_in": "15:00",
        "check_out": "11:00",
        "rating": 4,
        "available_rooms": [{
            "id_room": 101,
            "id_product": 5,
            "id_room_type": 5,
            "room_type_name": "Deluxe Room",
            "room_num": "101",
            "room_comment": "Sea view",
            "adults": 2,
            "children": 1,
            "max_adults": 3,
            "max_children": 2,
            "max_guests": 4,
            "max_occupancy": 4,
            "price": {
                "base_price": 150.00,
                "tax_included": 180.00,
                "currency": "USD"
            }
        }]
    }],
    "total_available_rooms": 1
}
```

#### Error Response (400 Bad Request)
```json
{
    "success": false,
    "error": "hotel_id is required",
    "timestamp": "2024-03-15T10:30:00Z",
    "error_code": "MISSING_PARAMETER"
}
```

### Implementation References
- **Base Class:** `WebserviceSpecificManagementAvailability.php`
- **Business Logic:** `HotelBookingDetail::getBookingData()`
- **Room Filtering:** `HotelBookingDetail::getSearchAvailableRooms()`
- **Occupancy Logic:** `HotelBookingDetail::getAvailableRoomSatisfingOccupancy()`

---

## 2. Add to Cart Endpoint

### Endpoint Details
```
POST /api/external/add-to-cart
```

### Purpose
Adds selected rooms from availability results to a shopping cart for later checkout.

### Request Parameters

#### Required Parameters
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `hotel_id` | integer | Hotel identifier | `1` |
| `room_id` | integer | Specific room identifier | `101` |
| `check_in` | date | Check-in date (Y-m-d) | `2024-03-15` |
| `check_out` | date | Check-out date (Y-m-d) | `2024-03-17` |
| `adults` | integer | Number of adults | `2` |
| `children` | integer | Number of children | `0` |

#### Optional Parameters
| Parameter | Type | Description | Default | Example |
|-----------|------|-------------|---------|---------|
| `child_ages` | array | Ages of children | `[]` | `[5, 8]` |
| `customer_id` | integer | Existing customer ID | Auto-create | `123` |
| `customer_email` | string | Customer email | Required if new | `guest@example.com` |
| `extra_demands` | array | Additional services | `[]` | `[{"id": 1, "quantity": 1}]` |

### Request Example
```http
POST /api/external/add-to-cart
Content-Type: application/json

{
    "ws_key": "MVFDI376Y2MCWR4YWHSH145GR9VWMWYX",
    "hotel_id": 1,
    "room_id": 101,
    "check_in": "2024-03-15",
    "check_out": "2024-03-17",
    "adults": 2,
    "children": 1,
    "child_ages": [8],
    "customer_email": "guest@example.com",
    "extra_demands": [
        {"id": 1, "name": "Extra Bed", "quantity": 1}
    ]
}
```

### Response Structure

#### Success Response (201 Created)
```json
{
    "success": true,
    "timestamp": "2024-03-15T10:30:00Z",
    "cart_id": "abc123xyz",
    "customer_id": 456,
    "booking_details": {
        "hotel": {
            "id_hotel": 1,
            "hotel_name": "Grand Plaza Hotel"
        },
        "room": {
            "id_room": 101,
            "room_num": "101",
            "room_type_name": "Deluxe Room"
        },
        "dates": {
            "check_in": "2024-03-15",
            "check_out": "2024-03-17",
            "nights": 2
        },
        "occupancy": {
            "adults": 2,
            "children": 1,
            "child_ages": [8]
        },
        "pricing": {
            "room_total": 300.00,
            "extra_demands_total": 50.00,
            "tax_amount": 42.00,
            "total_tax_included": 392.00,
            "currency": "USD"
        },
        "extra_demands": [{
            "id": 1,
            "name": "Extra Bed",
            "quantity": 1,
            "unit_price": 50.00,
            "total_price": 50.00
        }]
    },
    "cart_token": "cart_abc123xyz_token_def456",
    "expires_at": "2024-03-15T11:30:00Z"
}
```

#### Error Response (400 Bad Request)
```json
{
    "success": false,
    "error": "Your cart already contains a booking. Only one room can be booked per order.",
    "timestamp": "2024-03-15T10:30:00Z",
    "error_code": "CART_ALREADY_FULL"
}
```

```json
{
    "success": false,
    "error": "Room is no longer available for the selected dates",
    "timestamp": "2024-03-15T10:30:00Z",
    "error_code": "ROOM_NOT_AVAILABLE",
    "details": {
        "room_id": 101,
        "conflicting_bookings": [
            {"date_from": "2024-03-16", "date_to": "2024-03-18"}
        ]
    }
}
```

### Implementation References
- **Business Logic:** `HotelCartBookingData::addCartBookingData()`
- **Validation:** `HotelCartBookingData::validateCartBookings()`
- **Price Calculation:** `HotelRoomTypeFeaturePricing::getRoomTypeTotalPrice()`
- **Extra Demands:** `HotelRoomTypeGlobalDemand` classes

---

## 3. Make Reservation Endpoint

### Endpoint Details
```
POST /api/external/make-reservation
```

### Purpose
Creates a confirmed order from cart contents, processes payment (mocked), and returns complete booking details.

### Request Parameters

#### Required Parameters
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `cart_token` | string | Cart identification token | `cart_abc123xyz_token_def456` |
| `payment_method` | string | Payment method identifier | `mock_payment` |

#### Optional Parameters
| Parameter | Type | Description | Default | Example |
|-----------|------|-------------|---------|---------|
| `customer_info` | object | Customer details | From cart | See below |
| `special_requests` | string | Special booking requests | `""` | `"Late check-in"` |

#### Customer Information Object
```json
{
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@example.com",
    "phone": "+1-555-123-4567",
    "address": {
        "address1": "123 Main St",
        "city": "New York",
        "state": "NY",
        "postal_code": "10001",
        "country": "US"
    }
}
```

### Request Example
```http
POST /api/external/make-reservation
Content-Type: application/json

{
    "ws_key": "MVFDI376Y2MCWR4YWHSH145GR9VWMWYX",
    "cart_token": "cart_abc123xyz_token_def456",
    "payment_method": "mock_payment",
    "customer_info": {
        "first_name": "John",
        "last_name": "Doe",
        "email": "john.doe@example.com",
        "phone": "+1-555-123-4567"
    },
    "special_requests": "Late check-in requested"
}
```

### Response Structure

#### Success Response (201 Created)
```json
{
    "success": true,
    "timestamp": "2024-03-15T10:30:00Z",
    "reservation": {
        "booking_id": "HTL-2024-001234",
        "order_id": 5678,
        "status": "confirmed",
        "confirmation_number": "CONF-ABC123",
        "hotel": {
            "id_hotel": 1,
            "hotel_name": "Grand Plaza Hotel",
            "address": {
                "street": "123 Beach Boulevard",
                "city": "Miami",
                "state": "FL",
                "postal_code": "33139",
                "country": "USA"
            },
            "contact": {
                "phone": "+1-305-555-0123",
                "email": "reservations@grandplaza.com"
            },
            "check_in_time": "15:00",
            "check_out_time": "11:00",
            "images": [{
                "id": 1,
                "is_cover": true,
                "url": "http://localhost:8080/modules/hotelreservationsystem/views/img/hotel_img/1/1.jpg"
            }]
        },
        "room": {
            "id_room": 101,
            "room_num": "101",
            "room_type": "Deluxe Room",
            "description": "Spacious room with sea view",
            "amenities": ["Wi-Fi", "Air Conditioning", "Mini Bar", "Safe"],
            "images": [{
                "id": 1,
                "is_cover": true,
                "legend": "Main room view",
                "url": "http://localhost:8080/1-medium_default/deluxe-room.jpg"
            }, {
                "id": 2,
                "is_cover": false,
                "legend": "Bathroom view",
                "url": "http://localhost:8080/2-medium_default/deluxe-room.jpg"
            }]
        },
        "dates": {
            "check_in": "2024-03-15",
            "check_out": "2024-03-17",
            "nights": 2
        },
        "occupancy": {
            "adults": 2,
            "children": 1,
            "child_ages": [8],
            "total_guests": 3
        },
        "customer": {
            "customer_id": 456,
            "first_name": "John",
            "last_name": "Doe",
            "email": "john.doe@example.com",
            "phone": "+1-555-123-4567"
        },
        "pricing": {
            "room_charges": {
                "base_rate": 150.00,
                "nights": 2,
                "subtotal": 300.00
            },
            "extra_demands": [{
                "name": "Extra Bed",
                "quantity": 1,
                "unit_price": 50.00,
                "total": 50.00
            }],
            "taxes": {
                "room_tax": 36.00,
                "service_tax": 6.00,
                "total_tax": 42.00
            },
            "totals": {
                "subtotal": 350.00,
                "tax_amount": 42.00,
                "total_amount": 392.00,
                "currency": "USD"
            }
        },
        "payment": {
            "method": "mock_payment",
            "status": "completed",
            "transaction_id": "TXN-ABC123456",
            "amount_paid": 392.00,
            "payment_date": "2024-03-15T10:30:00Z"
        },
        "special_requests": "Late check-in requested",
        "cancellation_policy": {
            "free_cancellation_until": "2024-03-14T23:59:59Z",
            "partial_refund_until": "2024-03-15T12:00:00Z",
            "refund_percentage": 50
        },
        "created_at": "2024-03-15T10:30:00Z"
    }
}
```

#### Error Response (400 Bad Request)
```json
{
    "success": false,
    "error": "Payment processing failed",
    "timestamp": "2024-03-15T10:30:00Z",
    "error_code": "PAYMENT_FAILED",
    "details": {
        "payment_error": "Insufficient funds",
        "cart_preserved": true,
        "retry_allowed": true
    }
}
```

### Implementation References
- **Order Creation:** PrestaShop `Order` class integration
- **Booking Creation:** `HotelBookingDetail::createHotelBookingsFromOrder()`
- **Payment Processing:** Mock payment processor for development
- **Email Notifications:** Hotel reservation confirmation emails
- **Status Management:** Order state transitions

---

## 4. Booking by Confirmation Endpoint

### Endpoint Details
```
POST /api/external/booking-by-confirmation
```

### Purpose
Retrieves complete booking information using confirmation number and customer last name for secure booking lookup.

### Request Parameters

#### Required Parameters (JSON Body)
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `confirmation_number` | string | Order confirmation number | `QQSWLVLPH` |
| `customer_last_name` | string | Customer's last name for verification | `Smith` |

### Request Example
```http
POST /api/external/booking-by-confirmation
Content-Type: application/json
Authorization: Basic <base64_encoded_ws_key>

{
    "confirmation_number": "QQSWLVLPH",
    "customer_last_name": "Smith"
}
```

### Response Structure

#### Success Response (200 OK)
```json
{
    "success": true,
    "timestamp": "2024-03-15T10:30:00Z",
    "booking": {
        "booking_id": "HTL-2024-001234",
        "order_id": 5678,
        "status": "confirmed",
        "booking_status": "alloted",
        "confirmation_number": "QQSWLVLPH",
        "hotel": {
            "id_hotel": 1,
            "hotel_name": "Grand Plaza Hotel",
            "address": {
                "street": "123 Beach Boulevard",
                "city": "Miami",
                "state": "FL",
                "postal_code": "33139",
                "country": "USA"
            },
            "contact": {
                "phone": "+1-305-555-0123",
                "email": "reservations@grandplaza.com"
            },
            "policies": {
                "check_in_time": "15:00",
                "check_out_time": "11:00",
                "cancellation_policy": "Free cancellation until 24 hours before check-in"
            },
            "images": [{
                "id": 1,
                "is_cover": true,
                "url": "http://localhost:8080/modules/hotelreservationsystem/views/img/hotel_img/1/1.jpg"
            }]
        },
        "room": {
            "id_room": 101,
            "room_num": "101",
            "room_type": "Deluxe Room",
            "description": "Spacious room with sea view and balcony",
            "amenities": ["Wi-Fi", "Air Conditioning", "Mini Bar", "Safe", "Balcony"],
            "bed_type": "King Size",
            "max_occupancy": 4,
            "images": [{
                "id": 1,
                "is_cover": true,
                "legend": "Main room view",
                "url": "http://localhost:8080/1-medium_default/deluxe-room.jpg"
            }, {
                "id": 2,
                "is_cover": false,
                "legend": "Bathroom view",
                "url": "http://localhost:8080/2-medium_default/deluxe-room.jpg"
            }]
        },
        "dates": {
            "check_in": "2024-03-15",
            "check_out": "2024-03-17",
            "nights": 2,
            "actual_check_in": null,
            "actual_check_out": null
        },
        "occupancy": {
            "adults": 2,
            "children": 1,
            "child_ages": [8],
            "total_guests": 3
        },
        "customer": {
            "customer_id": 456,
            "first_name": "John",
            "last_name": "Smith",
            "email": "john.smith@example.com",
            "phone": "+1-555-123-4567"
        },
        "pricing": {
            "room_charges": {
                "nightly_rate": 150.00,
                "nights": 2,
                "subtotal": 300.00
            },
            "extra_demands": [],
            "taxes": {
                "room_tax": 36.00,
                "service_tax": 6.00,
                "total_tax": 42.00
            },
            "totals": {
                "subtotal": 350.00,
                "tax_amount": 42.00,
                "total_amount": 392.00,
                "currency": "USD"
            }
        },
        "payment": {
            "method": "Bank Wire",
            "status": "pending",
            "transaction_id": "QQSWLVLPH",
            "amount_paid": 392.00,
            "payment_date": "2024-03-15T10:30:00Z"
        },
        "special_requests": "",
        "booking_history": [],
        "cancellation_info": {
            "is_cancellable": true,
            "cancellation_deadline": "2024-03-14T23:59:59Z"
        },
        "created_at": "2024-03-15T10:30:00Z",
        "updated_at": "2024-03-15T10:30:00Z"
    }
}
```

#### Error Response (404 Not Found)
```json
{
    "success": false,
    "error": "Booking not found",
    "error_code": "BOOKING_NOT_FOUND",
    "timestamp": "2024-03-15T10:30:00Z"
}
```

### Implementation References
- **Handler Class:** `WebserviceSpecificManagementExternal::handleBookingByConfirmation()`
- **Business Logic:** `ExternalBookingManager::getBookingDetailsByConfirmation()`
- **Hotel Images:** `HotelImage::getImagesByHotelId()`
- **Room Images:** `Image::getImages()` from PrestaShop core

---

## 5. My Stay Endpoint

### Endpoint Details
```
POST /api/external/my-stay
```

### Purpose
Retrieves all active and upcoming reservations for a customer using their customer ID and secure key.

### Request Parameters

#### Required Parameters (JSON Body)
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `customer_id` | integer | Customer identifier | `2` |
| `secure_key` | string | Customer's secure authentication key | `0c8c673527f2c73b4a2a593245720bf6` |

### Request Example
```http
POST /api/external/my-stay
Content-Type: application/json
Authorization: Basic <base64_encoded_ws_key>

{
    "customer_id": 2,
    "secure_key": "0c8c673527f2c73b4a2a593245720bf6"
}
```

### Response Structure

#### Success Response (200 OK)
```json
{
    "success": true,
    "timestamp": "2024-03-15T10:30:00Z",
    "customer": {
        "customer_id": 2,
        "first_name": "John",
        "last_name": "Smith",
        "email": "john.smith@example.com"
    },
    "reservations": [{
        "booking_id": "HTL-2024-001234",
        "order_id": 5678,
        "status": "active",
        "confirmation_number": "QQSWLVLPH",
        "hotel": {
            "id_hotel": 1,
            "hotel_name": "Grand Plaza Hotel",
            "email": "reservations@grandplaza.com",
            "phone": "+1-305-555-0123",
            "images": [{
                "id": 1,
                "is_cover": true,
                "url": "http://localhost:8080/modules/hotelreservationsystem/views/img/hotel_img/1/1.jpg"
            }]
        },
        "room": {
            "id_room": 101,
            "room_num": "101",
            "room_type": "Deluxe Room",
            "max_occupancy": 4,
            "images": [{
                "id": 1,
                "is_cover": true,
                "legend": "Main room view",
                "url": "http://localhost:8080/1-medium_default/deluxe-room.jpg"
            }, {
                "id": 2,
                "is_cover": false,
                "legend": "Bathroom view",
                "url": "http://localhost:8080/2-medium_default/deluxe-room.jpg"
            }]
        },
        "dates": {
            "check_in": "2024-03-15",
            "check_out": "2024-03-17",
            "nights": 2
        },
        "occupancy": {
            "adults": 2,
            "children": 1,
            "total_guests": 3
        },
        "pricing": {
            "total_amount": 392.00,
            "currency": "USD"
        },
        "payment": {
            "method": "Bank Wire",
            "status": "pending"
        },
        "created_at": "2024-03-15T10:30:00Z",
        "updated_at": "2024-03-15T10:30:00Z"
    }],
    "total_reservations": 1
}
```

#### Error Response (401 Unauthorized)
```json
{
    "success": false,
    "error": "Invalid customer credentials",
    "error_code": "AUTHENTICATION_FAILED",
    "timestamp": "2024-03-15T10:30:00Z"
}
```

### Implementation References
- **Handler Class:** `WebserviceSpecificManagementExternal::handleMyStay()`
- **Business Logic:** `ExternalMyStayManager::getCustomerReservations()`
- **Customer Validation:** `ExternalMyStayManager::validateCustomer()`
- **Image Integration:** Same as booking-by-confirmation endpoint

---

## 6. Cancel Reservation Endpoint

### Endpoint Details
```
POST /api/external/cancel-reservation
```

### Purpose
Allows authenticated customers to cancel their own hotel reservations, applying hotel-specific cancellation policies and calculating appropriate refund amounts.

### Request Parameters

#### Required Parameters (JSON Body)
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `confirmation_number` | string | Order confirmation number | `QQSWLVLPH` |
| `customer_id` | integer | Customer identifier | `2` |
| `secure_key` | string | Customer's secure authentication key | `0c8c673527f2c73b4a2a593245720bf6` |

#### Optional Parameters
| Parameter | Type | Description | Default | Example |
|-----------|------|-------------|---------|---------|
| `cancellation_reason` | string | Reason for cancellation | `""` | `"Change of travel plans"` |

### Request Example
```http
POST /api/external/cancel-reservation
Content-Type: application/json
Authorization: Basic <base64_encoded_ws_key>

{
    "confirmation_number": "QQSWLVLPH",
    "customer_id": 2,
    "secure_key": "0c8c673527f2c73b4a2a593245720bf6",
    "cancellation_reason": "Change of travel plans"
}
```

### Response Structure

#### Success Response (200 OK)
```json
{
    "success": true,
    "timestamp": "2024-03-15T10:30:00Z",
    "cancellation": {
        "booking_id": "QQSWLVLPH",
        "cancellation_id": "CANCEL-001234",
        "order_id": 5678,
        "status": "confirmed",
        "cancellation_date": "2024-03-15T10:30:00Z",
        
        "booking_summary": {
            "hotel": {
                "hotel_name": "Grand Plaza Hotel"
            },
            "room": {
                "room_num": "101",
                "room_type": "Deluxe Room"
            },
            "dates": {
                "check_in": "2024-03-17",
                "check_out": "2024-03-19"
            },
            "original_amount": {
                "total": 200.00,
                "currency": "USD"
            }
        },
        
        "cancellation_details": {
            "refund_amount": {
                "amount": 200.00,
                "currency": "USD",
                "refund_method": "original_payment_method"
            }
        }
    }
}
```

#### Error Response (404 Not Found)
```json
{
    "success": false,
    "error": "Booking not found or does not belong to customer",
    "error_code": "BOOKING_NOT_FOUND",
    "timestamp": "2024-03-15T10:30:00Z",
    "details": {
        "booking_id": "QQSWLVLPH",
        "customer_id": 2
    }
}
```

#### Error Response (400 Bad Request)
```json
{
    "success": false,
    "error": "Cancellation deadline has passed",
    "error_code": "CANCELLATION_DEADLINE_PASSED",
    "timestamp": "2024-03-15T10:30:00Z",
    "details": {
        "check_in_date": "2024-03-16",
        "current_time": "2024-03-16T10:30:00Z"
    }
}
```

#### Error Response (409 Conflict)
```json
{
    "success": false,
    "error": "This booking has already been cancelled",
    "error_code": "ALREADY_CANCELLED",
    "timestamp": "2024-03-15T10:30:00Z",
    "details": {
        "cancellation_date": "2024-03-10T14:30:00Z",
        "refund_status": "processed",
        "refund_reference": "REF-CS-001234"
    }
}
```

### Business Logic

#### Cancellation Rules
- **Customer Verification:** Booking must belong to authenticated customer
- **Status Update:** Order and booking details are marked as cancelled.
- **Refund (Basic):** Currently, a full refund is assumed. Advanced refund rules based on cancellation policies are a future enhancement.

#### Security Features
- **Customer Verification:** Booking must belong to authenticated customer
- **Multi-layer Authentication:** WebService key + customer credentials

### Implementation References
- **Handler Class:** `WebserviceSpecificManagementExternal::handleCancelReservation()`
- **Business Logic:** `ExternalCancellationManager::processCancellationRequest()`
- **Status Updates:** PrestaShop `OrderHistory` and `HotelBookingDetail` classes.

---

## 7. Get Booking Details Endpoint

### Endpoint Details
```
GET /api/external/booking/{booking_id}
```

### Purpose
Retrieves complete booking information for a confirmed reservation.

### URL Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `booking_id` | string | Booking identifier | `HTL-2024-001234` |

### Request Example
```http
GET /api/external/booking/HTL-2024-001234?ws_key=MVFDI376Y2MCWR4YWHSH145GR9VWMWYX
```

### Response Structure

#### Success Response (200 OK)
```json
{
    "success": true,
    "timestamp": "2024-03-15T10:30:00Z",
    "booking": {
        "booking_id": "HTL-2024-001234",
        "order_id": 5678,
        "status": "confirmed",
        "booking_status": "alloted",
        "confirmation_number": "CONF-ABC123",
        "hotel": {
            "id_hotel": 1,
            "hotel_name": "Grand Plaza Hotel",
            "address": {
                "street": "123 Beach Boulevard",
                "city": "Miami",
                "state": "FL",
                "postal_code": "33139",
                "country": "USA"
            },
            "contact": {
                "phone": "+1-305-555-0123",
                "email": "reservations@grandplaza.com"
            },
            "policies": {
                "check_in_time": "15:00",
                "check_out_time": "11:00",
                "cancellation_policy": "Free cancellation until 24 hours before check-in"
            }
        },
        "room": {
            "id_room": 101,
            "room_num": "101",
            "room_type": "Deluxe Room",
            "description": "Spacious room with sea view and balcony",
            "amenities": ["Wi-Fi", "Air Conditioning", "Mini Bar", "Safe", "Balcony"],
            "bed_type": "King Size",
            "max_occupancy": 4
        },
        "dates": {
            "check_in": "2024-03-15",
            "check_out": "2024-03-17",
            "nights": 2,
            "actual_check_in": null,
            "actual_check_out": null
        },
        "occupancy": {
            "adults": 2,
            "children": 1,
            "child_ages": [8],
            "total_guests": 3
        },
        "customer": {
            "customer_id": 456,
            "first_name": "John",
            "last_name": "Doe",
            "email": "john.doe@example.com",
            "phone": "+1-555-123-4567"
        },
        "pricing": {
            "room_charges": {
                "nightly_rate": 150.00,
                "nights": 2,
                "subtotal": 300.00
            },
            "extra_demands": [{
                "id": 1,
                "name": "Extra Bed",
                "quantity": 1,
                "unit_price": 50.00,
                "total": 50.00
            }],
            "taxes": {
                "room_tax": 36.00,
                "service_tax": 6.00,
                "total_tax": 42.00
            },
            "totals": {
                "subtotal": 350.00,
                "tax_amount": 42.00,
                "total_amount": 392.00,
                "currency": "USD"
            }
        },
        "payment": {
            "method": "mock_payment",
            "status": "completed",
            "transaction_id": "TXN-ABC123456",
            "amount_paid": 392.00,
            "payment_date": "2024-03-15T10:30:00Z"
        },
        "special_requests": "Late check-in requested",
        "booking_history": [{
            "action": "booking_created",
            "timestamp": "2024-03-15T10:30:00Z",
            "details": "Reservation confirmed"
        }],
        "cancellation_info": {
            "is_cancellable": true,
            "free_cancellation_until": "2024-03-14T23:59:59Z",
            "partial_refund_until": "2024-03-15T12:00:00Z",
            "current_refund_percentage": 50
        },
        "created_at": "2024-03-15T10:30:00Z",
        "updated_at": "2024-03-15T10:30:00Z"
    }
}
```

#### Error Response (404 Not Found)
```json
{
    "success": false,
    "error": "Booking not found",
    "timestamp": "2024-03-15T10:30:00Z",
    "error_code": "BOOKING_NOT_FOUND",
    "details": {
        "booking_id": "HTL-2024-001234"
    }
}
```

### Implementation References
- **Booking Retrieval:** `HotelBookingDetail::getBookingsByIdOrder()`
- **Order Details:** PrestaShop `Order` class methods
- **Hotel Information:** `HotelBranchInformation::hotelBranchInfoById()`
- **Room Details:** `HotelRoomInformation::getHotelRoomsInfo()`

---

## Image Support Implementation

### Overview
All booking-related endpoints now include comprehensive image support for both hotels and rooms. Images are automatically fetched and included in API responses to provide rich visual content for frontend applications.

### Image Data Structure

#### Hotel Images
Hotel images are stored in the `htl_image` table and managed by the `HotelImage` class from the hotel reservation system module.

```json
{
    "images": [{
        "id": 1,
        "is_cover": true,
        "url": "http://localhost:8080/modules/hotelreservationsystem/views/img/hotel_img/1/1.jpg"
    }, {
        "id": 2,
        "is_cover": false,
        "url": "http://localhost:8080/modules/hotelreservationsystem/views/img/hotel_img/1/2.jpg"
    }]
}
```

**Hotel Image Fields:**
| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Unique hotel image identifier |
| `is_cover` | boolean | Whether this is the primary/cover image |
| `url` | string | Full URL to the image file |

#### Room Images
Room images are stored in PrestaShop's standard `image` table and linked to room type products. They include additional metadata like legends for accessibility.

```json
{
    "images": [{
        "id": 1,
        "is_cover": true,
        "legend": "Main room view",
        "url": "http://localhost:8080/1-medium_default/deluxe-room.jpg"
    }, {
        "id": 2,
        "is_cover": false,
        "legend": "Bathroom view", 
        "url": "http://localhost:8080/2-medium_default/deluxe-room.jpg"
    }]
}
```

**Room Image Fields:**
| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | PrestaShop image identifier |
| `is_cover` | boolean | Whether this is the primary product image |
| `legend` | string | Image description/alt text |
| `url` | string | Full URL to the image file (medium size format) |

### Image Integration Points

#### Endpoints with Image Support
1. **booking-by-confirmation** - Full hotel and room images
2. **my-stay** - Hotel and room images for all reservations
3. **make-reservation** - Images included in reservation response

#### Image Sources and Processing

**Hotel Images:**
- **Source:** `htl_image` database table
- **Retrieval:** `HotelImage::getImagesByHotelId($hotel_id)`
- **URL Generation:** `HotelImage::getImageLink($image_id)` with `Context::getContext()->link->getMediaLink()`
- **File Storage:** `/modules/hotelreservationsystem/views/img/hotel_img/{hotel_id}/{image_id}.jpg`

**Room Images:**
- **Source:** PrestaShop `image` table (linked to room type products)
- **Retrieval:** `Image::getImages($language_id, $product_id)`
- **URL Generation:** `Context::getContext()->link->getImageLink()` with medium image format
- **File Storage:** PrestaShop standard product image directory with size variants

### Implementation Details

#### Code Implementation Pattern
```php
// Get hotel images
$hotelImages = array();
require_once(dirname(__FILE__).'/../../hotelreservationsystem/classes/HotelImage.php');
$hotelImageObj = new HotelImage();
$hotelImagesList = $hotelImageObj->getImagesByHotelId($hotel_id);
if ($hotelImagesList && is_array($hotelImagesList)) {
    foreach ($hotelImagesList as $hotelImg) {
        $imageObj = new HotelImage($hotelImg['id']);
        $imageUrl = $imageObj->getImageLink($hotelImg['id']);
        $hotelImages[] = array(
            'id' => (int)$hotelImg['id'],
            'is_cover' => (bool)$hotelImg['cover'],
            'url' => Context::getContext()->link->getMediaLink($imageUrl),
        );
    }
}

// Get room images (product images)
$roomImages = array();
$productImages = Image::getImages($languageId, $product_id);
if ($productImages) {
    foreach ($productImages as $productImg) {
        $roomImages[] = array(
            'id' => (int)$productImg['id_image'],
            'is_cover' => (bool)$productImg['cover'],
            'legend' => $productImg['legend'],
            'url' => Context::getContext()->link->getImageLink($product->link_rewrite, $productImg['id_image'], ImageType::getFormatedName('medium')),
        );
    }
}
```

#### Classes Modified for Image Support
1. **ExternalBookingManager.php** - Added hotel and room image fetching to `getBookingDetailsByConfirmation()` method
2. **ExternalMyStayManager.php** - Added image support to `getBookingDetailsForOrder()` method  
3. **ExternalReservationManager.php** - Added image support to `formatResponse()` method

### Frontend Integration Guidelines

#### Image Display Recommendations
- **Hotel Images:** Use for property showcase, header banners, gallery displays
- **Room Images:** Use for room type selection, booking confirmation, detailed room views
- **Cover Images:** Use `is_cover: true` images as primary display images
- **Responsive Images:** URLs point to medium-format images (suitable for web display)

#### Error Handling
- **Empty Image Arrays:** If no images are available, arrays will be empty `[]`
- **Broken URLs:** Frontend should implement fallback images for broken or missing image files
- **Loading States:** Implement proper loading states for image-heavy API responses

#### Performance Considerations
- **Image Caching:** Images are served through PrestaShop's media system with proper caching headers
- **Lazy Loading:** Consider implementing lazy loading for image galleries
- **Image Optimization:** Medium format provides good balance between quality and load time

### Database Schema

#### Hotel Images Table (htl_image)
```sql
CREATE TABLE htl_image (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_hotel INT NOT NULL,
    cover TINYINT(1) DEFAULT 0,
    -- Additional fields managed by HotelImage class
    INDEX idx_hotel_id (id_hotel),
    INDEX idx_cover (id_hotel, cover)
);
```

#### PrestaShop Images Table (image)
```sql
-- Standard PrestaShop image table
-- Links to products (room types) via id_product
-- Includes multilingual legend support via image_lang table
```

This image integration provides comprehensive visual support for all booking-related operations while maintaining complete compatibility with the existing PrestaShop infrastructure.

---

## Implementation Guidelines

### 1. Module Structure

**Current Implementation:** `externalhotelreservationsystem`

```
modules/externalhotelreservationsystem/
├── externalhotelreservationsystem.php          # Main module file
├── classes/
│   ├── WebserviceSpecificManagementExternal.php  # Base external API handler
│   ├── ExternalAvailabilityManager.php          # Availability endpoint logic
│   ├── ExternalCartManager.php                  # Cart management logic
│   ├── ExternalReservationManager.php           # Reservation creation logic
│   ├── ExternalBookingManager.php               # Booking retrieval logic (with images)
│   ├── ExternalMyStayManager.php                # Customer reservations (with images)
│   ├── ExternalCustomerManager.php              # Customer signup management
│   ├── ExternalCustomerLoginManager.php         # Customer login management
│   ├── ExternalCartDetailsManager.php           # Cart details retrieval
│   ├── ExternalEmptyCartManager.php             # Cart cleanup operations
│   ├── ExternalApiValidator.php                 # Input validation utilities
│   ├── ExternalCancellationManager.php          # Cancellation logic
│   └── index.php
├── logs/
│   └── debug.log                                # API request/response logging
├── config.xml
├── install.php
└── index.php
```

**Key Implementation Features:**
- ✅ Complete endpoint coverage (availability, cart, reservation, booking, my-stay, cancel-reservation)
- ✅ Customer management (signup, login, authentication)
- ✅ Image support for hotels and rooms
- ✅ POST method implementation for secure operations
- ✅ Comprehensive error handling and logging
- ✅ Database schema extensions for external references

### 2. WebService Integration

#### Main Module Registration
**File:** `externalhotelreservationsystem.php`

```php
<?php
class ExternalHotelReservationSystem extends Module
{
    public function hookAddWebserviceResources()
    {
        return array(
            'external' => array(
                'description' => 'External Hotel Reservation API',
                'specific_management' => true,
                'specific_management_class' => 'WebserviceSpecificManagementExternal',
            ),
        );
    }
}
```

#### Route Handler
**File:** `classes/WebserviceSpecificManagementExternal.php`

```php
<?php
class WebserviceSpecificManagementExternal implements WebserviceSpecificManagementInterface
{
    public function manage()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $pathInfo = $_SERVER['PATH_INFO'];
        
        // Parse route: /api/external/{endpoint}
        $pathParts = explode('/', trim($pathInfo, '/'));
        if (count($pathParts) < 3 || $pathParts[1] !== 'external') {
            return $this->errorResponse('Invalid API endpoint');
        }
        
        $endpoint = $pathParts[2];
        
        switch ($endpoint) {
            case 'availability':
                return $this->handleAvailability($method);
            case 'add-to-cart':
                return $this->handleAddToCart($method);
            case 'make-reservation':
                return $this->handleMakeReservation($method);
            case 'booking':
                return $this->handleBookingDetails($method, $pathParts);
            case 'booking-by-confirmation':
                return $this->handleBookingByConfirmation($method, $params);
            case 'my-stay':
                return $this->handleMyStay($method, $params);
            case 'cancel-reservation':
                return $this->handleCancelReservation($method, $params);
            default:
                return $this->errorResponse('Unknown endpoint: ' . $endpoint);
        }
    }
}
```

### 3. Database Schema Extensions

#### Cart Token Management
```sql
CREATE TABLE htl_external_cart_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cart_token VARCHAR(255) UNIQUE NOT NULL,
    id_cart INT NOT NULL,
    id_customer INT,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cart_token (cart_token),
    INDEX idx_cart_id (id_cart),
    INDEX idx_expires (expires_at)
);
```

#### External Booking References
```sql
CREATE TABLE htl_external_booking_refs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id VARCHAR(100) UNIQUE NOT NULL,
    id_order INT NOT NULL,
    confirmation_number VARCHAR(50),
    external_ref VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_booking_id (booking_id),
    INDEX idx_order_id (id_order)
);
```

### 4. Security Considerations

#### Input Validation
```php
class ExternalApiValidator
{
    public static function validateDateRange($checkIn, $checkOut)
    {
        if (!Validate::isDate($checkIn) || !Validate::isDate($checkOut)) {
            throw new InvalidArgumentException('Invalid date format');
        }
        
        if (strtotime($checkIn) < strtotime(date('Y-m-d'))) {
            throw new InvalidArgumentException('Check-in date cannot be in the past');
        }
        
        if (strtotime($checkOut) <= strtotime($checkIn)) {
            throw new InvalidArgumentException('Check-out must be after check-in');
        }
    }
    
    public static function validateOccupancy($adults, $children = 0)
    {
        if (!Validate::isUnsignedInt($adults) || $adults < 1 || $adults > 10) {
            throw new InvalidArgumentException('Adults must be between 1 and 10');
        }
        
        if (!Validate::isUnsignedInt($children) || $children < 0 || $children > 6) {
            throw new InvalidArgumentException('Children must be between 0 and 6');
        }
    }
}
```

#### Rate Limiting
```php
class ExternalApiRateLimit
{
    private static $limits = array(
        'availability' => array('requests' => 60, 'window' => 3600), // 60/hour
        'add-to-cart' => array('requests' => 10, 'window' => 600),   // 10/10min
        'make-reservation' => array('requests' => 5, 'window' => 600), // 5/10min
        'booking' => array('requests' => 100, 'window' => 3600)      // 100/hour
    );
    
    public static function checkLimit($endpoint, $clientIp)
    {
        // Implementation for rate limiting logic
        // Store in cache or database with expiration
    }
}
```

### 5. Error Handling Standards

#### Error Response Format
```php
class ExternalApiResponse
{
    public static function error($message, $code = 'GENERAL_ERROR', $httpStatus = 400, $details = null)
    {
        $response = array(
            'success' => false,
            'error' => $message,
            'error_code' => $code,
            'timestamp' => date('c')
        );
        
        if ($details) {
            $response['details'] = $details;
        }
        
        http_response_code($httpStatus);
        header('Content-Type: application/json');
        return json_encode($response);
    }
    
    public static function success($data, $httpStatus = 200)
    {
        $response = array(
            'success' => true,
            'timestamp' => date('c')
        );
        
        $response = array_merge($response, $data);
        
        http_response_code($httpStatus);
        header('Content-Type: application/json');
        return json_encode($response);
    }
}
```

#### Common Error Codes
- `MISSING_PARAMETER` - Required parameter not provided
- `INVALID_PARAMETER` - Parameter format or value invalid
- `HOTEL_NOT_FOUND` - Specified hotel doesn't exist
- `ROOM_NOT_AVAILABLE` - Room unavailable for dates
- `CART_EXPIRED` - Cart token has expired
- `PAYMENT_FAILED` - Payment processing error
- `BOOKING_NOT_FOUND` - Booking ID not found
- `ACCESS_DENIED` - Insufficient permissions
- `RATE_LIMIT_EXCEEDED` - Too many requests

### 6. Testing Strategy

#### Unit Tests
```php
class ExternalApiTest extends PHPUnit_Framework_TestCase
{
    public function testAvailabilitySearch()
    {
        $params = array(
            'hotel_id' => 1,
            'check_in' => date('Y-m-d', strtotime('+7 days')),
            'check_out' => date('Y-m-d', strtotime('+9 days')),
            'adults' => 2
        );
        
        $manager = new ExternalAvailabilityManager();
        $result = $manager->searchAvailability($params);
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('hotels', $result);
    }
}
```

#### Integration Tests
- Full API endpoint testing
- Database transaction testing
- PrestaShop integration validation
- Email notification testing

### 7. Performance Optimizations

#### Caching Strategy
```php
class ExternalApiCache
{
    public static function getAvailabilityCache($cacheKey)
    {
        // Check Redis/Memcached for availability results
        // Cache for 5 minutes to balance accuracy vs performance
    }
    
    public static function setAvailabilityCache($cacheKey, $data, $ttl = 300)
    {
        // Store availability results with 5-minute TTL
    }
}
```

#### Database Optimization
- Indexed queries on booking dates
- Optimized joins between hotel tables
- Connection pooling for high-load scenarios

### 8. Monitoring and Logging

#### API Logging
```php
class ExternalApiLogger
{
    public static function logRequest($endpoint, $params, $response, $executionTime)
    {
        $logData = array(
            'timestamp' => date('c'),
            'endpoint' => $endpoint,
            'method' => $_SERVER['REQUEST_METHOD'],
            'params' => $params,
            'response_code' => http_response_code(),
            'execution_time' => $executionTime,
            'client_ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        );
        
        // Log to file or monitoring system
        file_put_contents('logs/external_api.log', json_encode($logData) . "\n", FILE_APPEND);
    }
}
```

---

## Recent Updates & Enhancements

### Version 2.1 - Image Support Integration (August 2025)

#### New Features Added:
✅ **Comprehensive Image Support**
- Hotel property images automatically included in all booking responses
- Room type images with legends and cover image identification
- Full URL generation with PrestaShop media link integration

#### Endpoints Enhanced:
1. **booking-by-confirmation** - Now includes `hotel.images[]` and `room.images[]`
2. **my-stay** - All customer reservations include hotel and room images  
3. **make-reservation** - Reservation confirmation includes complete image data

#### Technical Implementation:
- **Hotel Images:** Integration with `HotelImage` class and `htl_image` table
- **Room Images:** Integration with PrestaShop core `Image` class and `image` table
- **URL Generation:** Proper media link generation with caching support
- **Performance:** Optimized image queries with proper database indexing

#### Database Schema:
- Leverages existing `htl_image` table for hotel images
- Uses standard PrestaShop `image` and `image_lang` tables for room images
- No additional schema changes required

#### Frontend Benefits:
- Rich visual content for property showcases
- Room type galleries with detailed imagery
- Cover image identification for primary displays
- Responsive image URLs (medium format optimized)

### API Response Structure Updates:

**Before (v2.0):**
```json
{
    "hotel": {
        "hotel_name": "Grand Plaza Hotel",
        "contact": {...}
    },
    "room": {
        "room_type": "Deluxe Room",
        "amenities": [...]
    }
}
```

**After (v2.1):**
```json
{
    "hotel": {
        "hotel_name": "Grand Plaza Hotel",
        "contact": {...},
        "images": [{
            "id": 1,
            "is_cover": true,
            "url": "http://localhost:8080/modules/hotelreservationsystem/views/img/hotel_img/1/1.jpg"
        }]
    },
    "room": {
        "room_type": "Deluxe Room",
        "amenities": [...],
        "images": [{
            "id": 1,
            "is_cover": true,
            "legend": "Main room view",
            "url": "http://localhost:8080/1-medium_default/deluxe-room.jpg"
        }]
    }
}
```

This comprehensive API development guide provides the foundation for implementing a robust external hotel reservation system with full image support while maintaining complete compatibility with the existing PrestaShop infrastructure.
