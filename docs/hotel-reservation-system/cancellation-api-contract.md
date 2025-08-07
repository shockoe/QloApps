# Hotel Reservation Cancellation API Contract

## Overview

This document defines the API contract for implementing customer-facing reservation cancellation functionality in the `externalhotelreservationsystem` module. The API leverages the existing sophisticated cancellation system while providing secure, user-friendly access for customer self-service cancellation requests.

## Table of Contents

1. [API Endpoint Specification](#api-endpoint-specification)
2. [Request/Response Contracts](#requestresponse-contracts)
3. [Business Logic Requirements](#business-logic-requirements)
4. [Error Handling Specification](#error-handling-specification)
5. [Security Requirements](#security-requirements)
6. [Implementation Plan](#implementation-plan)
7. [Code Changes Required](#code-changes-required)
8. [Sequence Diagrams](#sequence-diagrams)

---

## API Endpoint Specification

### Endpoint Details
```
POST /api/external/cancel-reservation
Content-Type: application/json
Authorization: Basic <base64_encoded_ws_key>
```

### Purpose
Allows authenticated customers to cancel their own hotel reservations, applying hotel-specific cancellation policies and calculating appropriate refund amounts.

### Authentication
- **Method**: Basic Authentication using WebService API key
- **Customer Verification**: Customer ID + Secure Key combination
- **Booking Ownership**: Verify booking belongs to authenticated customer

---

## Request/Response Contracts

### Request Schema

#### Required Parameters (JSON Body)
```json
{
    "booking_id": "string",           // External booking ID (HTL-2024-001234)
    "customer_id": "integer",         // Customer identifier
    "secure_key": "string",           // Customer's secure authentication key
    "cancellation_reason": "string"   // Optional cancellation reason
}
```

#### Request Example
```http
POST /api/external/cancel-reservation
Content-Type: application/json
Authorization: Basic TVZGREkzNzZZMk1DV1I0WVdIU0gxNDVHUjlWV01XWVg6

{
    "booking_id": "HTL-2024-001234",
    "customer_id": 2,
    "secure_key": "0c8c673527f2c73b4a2a593245720bf6",
    "cancellation_reason": "Change of travel plans"
}
```

### Success Response Schema

#### HTTP 200 OK
```json
{
    "success": true,
    "timestamp": "2024-03-15T10:30:00Z",
    "cancellation": {
        "booking_id": "HTL-2024-001234",
        "cancellation_id": "CANCEL-001234",
        "order_id": 5678,
        "status": "confirmed",
        "cancellation_date": "2024-03-15T10:30:00Z",
        
        "booking_summary": {
            "hotel": {
                "id_hotel": 1,
                "hotel_name": "Grand Plaza Hotel",
                "contact": {
                    "phone": "+1-305-555-0123",
                    "email": "reservations@grandplaza.com"
                }
            },
            "room": {
                "id_room": 101,
                "room_num": "101",
                "room_type": "Deluxe Room"
            },
            "dates": {
                "check_in": "2024-03-17",
                "check_out": "2024-03-19",
                "nights": 2
            },
            "original_amount": {
                "total": 200.00,
                "currency": "USD"
            }
        },
        
        "cancellation_details": {
            "days_until_checkin": 2,
            "rule_applied": {
                "id_refund_rule": 1,
                "name": "48-hour cancellation policy",
                "description": "50% charge for cancellations within 48 hours of check-in",
                "rule_type": "percentage",
                "deduction_value": 50,
                "applicable_days": 2
            },
            "cancellation_charge": {
                "amount": 100.00,
                "currency": "USD",
                "calculation_method": "percentage"
            },
            "refund_amount": {
                "amount": 100.00,
                "currency": "USD",
                "refund_method": "credit_slip"
            }
        },
        
        "timeline": {
            "cancellation_deadline": "2024-03-16T23:59:59Z",
            "refund_processing_time": "5-7 business days",
            "credit_slip_generation": "immediate"
        },
        
        "customer_info": {
            "refund_reference": "REF-CS-001234",
            "customer_service_contact": "+1-305-555-0100",
            "policy_link": "https://hotel.com/cancellation-policy"
        }
    }
}
```

### Error Response Schemas

#### HTTP 404 - Booking Not Found
```json
{
    "success": false,
    "error": "Booking not found or does not belong to customer",
    "error_code": "BOOKING_NOT_FOUND",
    "timestamp": "2024-03-15T10:30:00Z",
    "details": {
        "booking_id": "HTL-2024-001234",
        "customer_id": 2
    }
}
```

#### HTTP 400 - Cancellation Deadline Passed
```json
{
    "success": false,
    "error": "Cancellation deadline has passed",
    "error_code": "CANCELLATION_DEADLINE_PASSED",
    "timestamp": "2024-03-15T10:30:00Z",
    "details": {
        "check_in_date": "2024-03-16",
        "cancellation_deadline": "2024-03-15T23:59:59Z",
        "current_time": "2024-03-16T10:30:00Z",
        "hours_past_deadline": 10.5
    }
}
```

#### HTTP 403 - Cancellations Not Allowed
```json
{
    "success": false,
    "error": "This hotel does not accept cancellation requests",
    "error_code": "CANCELLATIONS_NOT_ALLOWED",
    "timestamp": "2024-03-15T10:30:00Z",
    "details": {
        "hotel_name": "Grand Plaza Hotel",
        "policy_reason": "Hotel policy prohibits cancellations",
        "alternative_contact": "+1-305-555-0123"
    }
}
```

#### HTTP 409 - Already Cancelled
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

#### HTTP 401 - Authentication Failed
```json
{
    "success": false,
    "error": "Invalid customer credentials",
    "error_code": "AUTHENTICATION_FAILED",
    "timestamp": "2024-03-15T10:30:00Z"
}
```

---

## Business Logic Requirements

### Validation Rules

#### 1. Customer Authentication
```php
// Required validation steps
1. Verify customer_id exists and is active
2. Validate secure_key matches customer record
3. Ensure customer has permission to cancel bookings
4. Rate limiting: Max 5 cancellation attempts per hour per customer
```

#### 2. Booking Ownership & Status
```php
// Booking validation requirements
1. Booking exists and belongs to authenticated customer
2. Booking status allows cancellation (not already cancelled/refunded)
3. Order status is cancellable (not in error/processing states)
4. No existing pending cancellation requests
```

#### 3. Hotel Policy Compliance
```php
// Hotel-specific policy checks
1. Hotel allows cancellations (active_refund = 1)
2. Check-in date is in the future
3. Cancellation request within allowed timeframe
4. Apply hotel-specific refund rules based on timing
```

### Refund Calculation Logic

#### Rule Application Process
```php
// Calculation workflow
1. Determine days between cancellation request and check-in date
2. Query hotel-specific refund rules ordered by position
3. Find first applicable rule where days_before_checkin >= rule.days
4. Apply rule based on payment type (full/advance payment)
5. Calculate cancellation charge and refund amount
6. Handle multi-currency conversion if required
```

#### Amount Calculation Components
```php
// Total booking amount calculation
$totalAmount = $roomCharges + $extraDemands + $serviceProducts;

// For percentage rules
$cancellationCharge = $totalAmount * ($ruleValue / 100);

// For fixed amount rules (with currency conversion)
$cancellationCharge = convertCurrency($fixedAmount, $defaultCurrency, $orderCurrency);

// Final refund amount
$refundAmount = $totalAmount - $cancellationCharge;
```

### Status Management

#### Database Updates Required
```php
// Primary status updates
htl_booking_detail.is_cancelled = 1;
htl_booking_detail.is_refunded = 1; // If refund involved

// Create refund request tracking
INSERT INTO order_return (id_order, state, date_add);
INSERT INTO order_return_detail (id_order_return, id_htl_booking, refunded_amount);

// Update order status if complete cancellation
UPDATE orders SET current_state = PS_OS_REFUND WHERE id_order = ?;
```

---

## Error Handling Specification

### Error Categories

#### 1. Authentication Errors (HTTP 401)
- Invalid WebService API key
- Invalid customer_id + secure_key combination
- Customer account suspended/inactive

#### 2. Authorization Errors (HTTP 403)
- Booking doesn't belong to customer
- Hotel policy prohibits cancellations
- Customer lacks cancellation permissions

#### 3. Business Logic Errors (HTTP 400)
- Cancellation deadline passed
- Invalid booking status
- Required fields missing/invalid
- Booking already cancelled

#### 4. Not Found Errors (HTTP 404)
- Booking ID not found
- Customer not found
- Hotel not found

#### 5. Conflict Errors (HTTP 409)
- Duplicate cancellation request
- Booking in non-cancellable state
- Concurrent modification detected

#### 6. Server Errors (HTTP 500)
- Database connection failures
- Payment gateway errors
- Email system failures

### Error Response Standards

#### Consistent Error Format
```json
{
    "success": false,
    "error": "Human-readable error message",
    "error_code": "MACHINE_READABLE_CODE",
    "timestamp": "2024-03-15T10:30:00Z",
    "details": {
        // Context-specific error details
    }
}
```

#### Error Code Categories
- **BOOKING_*** - Booking-related errors
- **CUSTOMER_*** - Customer authentication/authorization
- **POLICY_*** - Hotel policy violations
- **SYSTEM_*** - Technical/infrastructure errors
- **VALIDATION_*** - Input validation errors

---

## Security Requirements

### Authentication & Authorization

#### Multi-Layer Security
```php
// Security implementation requirements
1. WebService API key validation (Basic Auth header)
2. Customer identity verification (customer_id + secure_key)
3. Booking ownership validation
4. Rate limiting per customer and IP
5. HTTPS-only communication enforcement
```

#### Input Validation
```php
// Required input validations
- booking_id: Valid format, exists in database
- customer_id: Positive integer, valid customer
- secure_key: Exact match with customer record
- cancellation_reason: Optional string, max 500 characters
```

### Rate Limiting

#### Cancellation Request Limits
```php
// Recommended limits
- Per Customer: 5 cancellation attempts per hour
- Per IP Address: 20 cancellation attempts per hour
- Per WebService Key: 100 cancellation attempts per hour
- Global: 1000 cancellation attempts per hour
```

### Audit Logging

#### Required Log Information
```php
// Security audit trail
{
    "timestamp": "2024-03-15T10:30:00Z",
    "endpoint": "/api/external/cancel-reservation",
    "customer_id": 2,
    "booking_id": "HTL-2024-001234",
    "ip_address": "192.168.1.100",
    "user_agent": "Mobile App v2.1.0",
    "result": "success|failed",
    "error_code": "BOOKING_NOT_FOUND",
    "refund_amount": 100.00,
    "processing_time_ms": 1234
}
```

---

## Implementation Plan

### Phase 1: Core Infrastructure (Week 1)
1. Create `ExternalCancellationManager.php` class
2. Implement customer authentication and booking validation
3. Integrate with existing `HotelOrderRefundRules` system
4. Basic cancellation charge calculation
5. Simple success/error response handling

### Phase 2: Advanced Features (Week 2)
1. Comprehensive error handling with detailed responses
2. Integration with email notification system
3. Support for complex booking scenarios
4. Rate limiting and security enhancements
5. Audit logging implementation

### Phase 3: Integration & Testing (Week 3)
1. Integration with existing admin cancellation workflow
2. Database transaction handling and rollback mechanisms
3. Performance optimization and caching
4. Comprehensive API testing and validation
5. Documentation and deployment preparation

---

## Code Changes Required

### New Files to Create

#### 1. `ExternalCancellationManager.php`
```php
<?php
/**
 * External API cancellation management
 * Location: modules/externalhotelreservationsystem/classes/ExternalCancellationManager.php
 */
class ExternalCancellationManager
{
    public function processCancellationRequest($params)
    {
        // Main cancellation processing method
    }
    
    private function validateCustomerAndBooking($customerId, $secureKey, $bookingId)
    {
        // Customer authentication and booking ownership validation
    }
    
    private function checkCancellationPolicy($hotelId, $checkInDate)
    {
        // Hotel policy and deadline validation
    }
    
    private function calculateCancellationCharges($orderId, $bookingId)
    {
        // Leverage existing HotelOrderRefundRules system
    }
    
    private function processCancellation($bookingDetails, $cancellationCharges)
    {
        // Update database tables and create refund records
    }
    
    private function formatResponse($cancellationResult)
    {
        // Format API response with cancellation details
    }
}
```

#### 2. WebService Handler Integration
```php
// Update: modules/externalhotelreservationsystem/classes/WebserviceSpecificManagementExternal.php

protected function handleCancellationRequest($method, $params)
{
    if ($method !== 'POST') {
        $this->errorResponse('Method Not Allowed', 405);
        return;
    }
    
    try {
        require_once(dirname(__FILE__).'/ExternalCancellationManager.php');
        $manager = new ExternalCancellationManager();
        $input = Tools::file_get_contents('php://input');
        $result = $manager->processCancellationRequest(json_decode($input, true));
        
        $this->output = json_encode($result);
    } catch (Exception $e) {
        $this->errorResponse('Internal error: ' . $e->getMessage(), 500);
    }
    return true;
}
```

#### 3. Route Registration
```php
// Update: modules/externalhotelreservationsystem/classes/WebserviceSpecificManagementExternal.php

public function manage()
{
    // Add case for cancellation endpoint
    switch ($endpoint) {
        // ... existing cases ...
        case 'cancel-reservation':
            return $this->handleCancellationRequest($method, $params);
        // ... rest of cases ...
    }
}
```

### Database Schema Extensions (Optional)

#### Cancellation Audit Table
```sql
-- Optional: Enhanced audit trail for API cancellations
CREATE TABLE htl_external_cancellation_log (
    id_cancellation_log INT AUTO_INCREMENT PRIMARY KEY,
    id_order INT NOT NULL,
    id_htl_booking INT NOT NULL,
    id_customer INT NOT NULL,
    booking_id VARCHAR(100) NOT NULL,
    cancellation_reason TEXT,
    refund_rule_applied INT,
    cancellation_charge DECIMAL(20,6) NOT NULL,
    refund_amount DECIMAL(20,6) NOT NULL,
    api_source VARCHAR(50) DEFAULT 'external_api',
    ip_address VARCHAR(45),
    user_agent TEXT,
    date_add DATETIME NOT NULL,
    
    INDEX idx_order (id_order),
    INDEX idx_booking (id_htl_booking),
    INDEX idx_customer (id_customer),
    INDEX idx_date (date_add)
);
```

### Integration Points

#### Email Notification Enhancement
```php
// Optional: Custom email templates for API cancellations
// Location: modules/externalhotelreservationsystem/mails/en/
- cancellation_confirmation.html
- cancellation_confirmation.txt
```

#### Configuration Settings
```php
// Add to hotel configuration
'WK_API_CANCELLATION_ENABLED' => true,
'WK_API_CANCELLATION_DEADLINE_HOURS' => 24, // Minimum hours before check-in
'WK_API_CANCELLATION_RATE_LIMIT' => 5,      // Max requests per hour per customer
```

---

## Sequence Diagrams

### Primary Cancellation Flow

```mermaid
sequenceDiagram
    participant Client as Customer App
    participant API as External API
    participant Auth as Authentication
    participant Manager as CancellationManager
    participant Rules as RefundRules
    participant Booking as BookingDetail
    participant Order as PrestaShop Order
    participant Email as Email System

    Client->>API: POST /api/external/cancel-reservation
    API->>Auth: Validate WebService key
    Auth-->>API: Key validated
    
    API->>Manager: processCancellationRequest()
    Manager->>Manager: validateCustomerAndBooking()
    Manager->>Manager: checkCancellationPolicy()
    
    Manager->>Rules: getBookingCancellationDetails()
    Rules->>Rules: Calculate days until check-in
    Rules->>Rules: Find applicable refund rule
    Rules->>Rules: Calculate cancellation charge
    Rules-->>Manager: Return charge details
    
    Manager->>Booking: processRefundInBookingTables()
    Booking->>Booking: Set is_cancelled/is_refunded = 1
    Booking->>Order: Update order status and totals
    Booking->>Booking: Resolve overbookings (if enabled)
    
    Manager->>Email: Send cancellation confirmation
    Manager->>Manager: Log cancellation event
    Manager-->>API: Return cancellation result
    API-->>Client: JSON response with refund details
```

### Error Handling Flow

```mermaid
sequenceDiagram
    participant Client as Customer App
    participant API as External API
    participant Manager as CancellationManager
    participant Logger as Audit Logger

    Client->>API: POST /api/external/cancel-reservation
    API->>Manager: processCancellationRequest()
    
    alt Authentication Failure
        Manager->>Manager: validateCustomerAndBooking()
        Manager-->>API: AuthenticationException
        API->>Logger: Log failed auth attempt
        API-->>Client: 401 AUTHENTICATION_FAILED
    
    else Booking Not Found
        Manager->>Manager: validateCustomerAndBooking()
        Manager-->>API: BookingNotFoundException
        API->>Logger: Log booking not found
        API-->>Client: 404 BOOKING_NOT_FOUND
    
    else Cancellation Deadline Passed
        Manager->>Manager: checkCancellationPolicy()
        Manager-->>API: DeadlinePassedException
        API->>Logger: Log deadline violation
        API-->>Client: 400 CANCELLATION_DEADLINE_PASSED
    
    else Already Cancelled
        Manager->>Manager: checkBookingStatus()
        Manager-->>API: AlreadyCancelledException
        API->>Logger: Log duplicate cancellation
        API-->>Client: 409 ALREADY_CANCELLED
    
    else System Error
        Manager->>Manager: processCancellation()
        Manager-->>API: SystemException
        API->>Logger: Log system error
        API-->>Client: 500 INTERNAL_ERROR
    end
```

### Integration with Existing System

```mermaid
sequenceDiagram
    participant API as External API
    participant Manager as CancellationManager
    participant ExistingRules as HotelOrderRefundRules
    participant ExistingBooking as HotelBookingDetail
    participant ExistingAdmin as AdminController

    Note over API, ExistingAdmin: API leverages existing cancellation infrastructure

    API->>Manager: Process cancellation request
    Manager->>ExistingRules: getBookingCancellationDetails()
    ExistingRules->>ExistingRules: Apply existing business rules
    ExistingRules-->>Manager: Return cancellation charges
    
    Manager->>ExistingBooking: processRefundInBookingTables()
    ExistingBooking->>ExistingBooking: Use existing refund logic
    ExistingBooking-->>Manager: Booking updated
    
    Note over Manager, ExistingAdmin: Same business logic as admin interface
    Manager->>ExistingAdmin: Use same email/notification system
    ExistingAdmin->>ExistingAdmin: Send standard cancellation emails
    
    Manager-->>API: Return standardized response
```

This API contract provides a comprehensive foundation for implementing secure, robust customer-facing cancellation functionality while leveraging the existing sophisticated hotel reservation cancellation system.