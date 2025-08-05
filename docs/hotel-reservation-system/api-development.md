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
            "check_out_time": "11:00"
        },
        "room": {
            "id_room": 101,
            "room_num": "101",
            "room_type": "Deluxe Room",
            "description": "Spacious room with sea view",
            "amenities": ["Wi-Fi", "Air Conditioning", "Mini Bar", "Safe"]
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

## 4. Get Booking Details Endpoint

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

## Implementation Guidelines

### 1. Module Structure

Create new module: `externalhotelreservationsystem`

```
modules/externalhotelreservationsystem/
├── externalhotelreservationsystem.php          # Main module file
├── classes/
│   ├── WebserviceSpecificManagementExternal.php  # Base external API handler
│   ├── ExternalAvailabilityManager.php          # Availability endpoint logic
│   ├── ExternalCartManager.php                  # Cart management logic
│   ├── ExternalReservationManager.php           # Reservation creation logic
│   ├── ExternalBookingManager.php               # Booking retrieval logic  
│   ├── ExternalPaymentProcessor.php             # Mock payment processor
│   └── index.php
├── controllers/
│   └── index.php
├── config.xml
├── install.php
└── index.php
```

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

This comprehensive API development guide provides the foundation for implementing a robust external hotel reservation system while maintaining full compatibility with the existing PrestaShop infrastructure.