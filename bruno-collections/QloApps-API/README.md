# QloApps API Documentation

Complete API documentation for QloApps - a hotel booking system built on PrestaShop.

## Overview

QloApps provides a comprehensive REST API for managing hotels, rooms, bookings, and customers. The API is built on PrestaShop's webservice framework and supports both JSON and XML formats.

## Authentication

The API uses HTTP Basic Authentication:
- **Username**: API Key (`MVFDI376Y2MCWR4YWHSH145GR9VWMWYX`)
- **Password**: Empty string

```bash
curl -u MVFDI376Y2MCWR4YWHSH145GR9VWMWYX: "http://localhost:8080/api/hotels?output_format=JSON"
```

## Base URLs

- **Local Development**: `http://localhost:8080/api`
- **Output Format**: Add `?output_format=JSON` for JSON responses (default is XML)

## Available Resources

### Core Hotel Resources
- `hotels` - Hotel properties and branch information
- `hotel_room_types` - Room type definitions
- `hotel_rooms` - Individual room inventory
- `hotel_features` - Hotel amenities and features

### Booking Resources
- `cart_bookings` - Items in booking carts (pending)
- `room_bookings` - Confirmed room reservations
- `booking_extra_demands` - Additional services in bookings
- `advance_payments` - Payment configurations

### Customer Resources
- `customers` - Registered customer accounts
- `guests` - Guest checkout information
- `addresses` - Customer addresses

### Pricing & Inventory
- `feature_prices` - Dynamic pricing rules
- `extra_demands` - Additional services/amenities
- `hotel_refund_rules` - Refund policies

### System Resources
- `orders` - Order management
- `carts` - Shopping cart functionality
- `search` - Search functionality (read-only)

## API Workflow for Booking System

### 1. Search Hotels and Rooms
```bash
# Get all hotels
GET /api/hotels

# Get hotel details
GET /api/hotels/{id}

# Get available room types
GET /api/hotel_room_types

# Get room type details
GET /api/hotel_room_types/{id}
```

### 2. Check Availability
```bash
# Get individual room inventory
GET /api/hotel_rooms

# Check pricing
GET /api/feature_prices
```

### 3. Create Booking
```bash
# Create/update customer
POST /api/customers

# Add to cart
POST /api/cart_bookings

# Create order (converts cart to confirmed booking)
POST /api/orders
```

### 4. Manage Bookings
```bash
# View confirmed bookings
GET /api/room_bookings

# Update booking status
PUT /api/room_bookings/{id}
```

## Key Data Structures

### Hotel Object
```json
{
  "hotel": {
    "id": "1",
    "hotel_name": "The Hotel Prime",
    "description": "Hotel description...",
    "address": "Hotel address",
    "phone": "Phone number",
    "email": "Email address",
    "check_in": "12:00",
    "check_out": "11:00",
    "rating": "3",
    "policies": "Hotel policies...",
    "associations": {
      "room_types": [{"id": "1"}, {"id": "2"}],
      "hotel_features": [{"id": "1"}, {"id": "2"}]
    }
  }
}
```

### Room Type Object
```json
{
  "hotel_room_type": {
    "id": "1",
    "id_product": "1",
    "id_hotel": "1",
    "adults": "2",
    "children": "2",
    "max_adults": "2",
    "max_children": "2",
    "max_guests": "4",
    "min_los": "1",
    "max_los": "0",
    "associations": {
      "hotel_rooms": [{"id": "1"}, {"id": "2"}]
    }
  }
}
```

### Cart Booking Object
```json
{
  "cart_booking": {
    "id": "1",
    "id_cart": "1",
    "id_room": "1",
    "id_hotel": "1",
    "date_from": "2025-07-01",
    "date_to": "2025-07-05",
    "adults": "2",
    "children": "0"
  }
}
```

## HTTP Methods Support

| Resource | GET | POST | PUT | DELETE |
|----------|-----|------|-----|--------|
| hotels | ✅ | ✅ | ✅ | ✅ |
| hotel_room_types | ✅ | ✅ | ✅ | ✅ |
| hotel_rooms | ✅ | ✅ | ✅ | ✅ |
| cart_bookings | ✅ | ✅ | ✅ | ✅ |
| room_bookings | ✅ | ✅ | ✅ | ✅ |
| customers | ✅ | ✅ | ✅ | ✅ |
| search | ✅ | ❌ | ❌ | ❌ |

## Schema Information

Get field definitions for any resource:
```bash
# Get blank template for creation
GET /api/hotels?schema=blank

# Get field synopsis
GET /api/hotels?schema=synopsis
```

## Error Handling

The API returns standard HTTP status codes:
- `200` - Success
- `400` - Bad Request
- `401` - Unauthorized
- `404` - Not Found
- `500` - Internal Server Error

Error responses include details:
```json
{
  "errors": [
    {
      "code": 23,
      "message": "Method GET is not valid"
    }
  ]
}
```

## Rate Limiting

No specific rate limiting is implemented in the base system, but it's recommended to:
- Implement client-side throttling
- Cache responses when appropriate
- Use bulk operations when available

## Data Formats

### Dates
- Format: `YYYY-MM-DD`
- Example: `2025-07-01`

### Times
- Format: `HH:MM` (24-hour)
- Example: `14:30`

### Boolean Values
- `1` for true
- `0` for false

## MCP Server Integration

For MCP server implementation, focus on these key endpoints:

1. **Hotel Search**: `/api/hotels` and `/api/hotel_room_types`
2. **Availability**: `/api/hotel_rooms` and `/api/feature_prices`
3. **Booking Creation**: `/api/cart_bookings` and `/api/orders`
4. **Customer Management**: `/api/customers`

## Bruno Collection Structure

```
QloApps-API/
├── Authentication/
│   └── Test Connection.bru
├── Hotels/
│   ├── Get All Hotels.bru
│   ├── Get Hotel Details.bru
│   └── Get Hotel Schema.bru
├── Rooms/
│   ├── Get All Room Types.bru
│   ├── Get Room Type Details.bru
│   └── Get All Hotel Rooms.bru
├── Bookings/
│   ├── Get Cart Bookings.bru
│   ├── Get Room Bookings.bru
│   └── Create Cart Booking.bru
├── Customers/
│   ├── Get All Customers.bru
│   └── Create Customer.bru
├── Features/
│   ├── Get Hotel Features.bru
│   └── Get Feature Prices.bru
└── Search/
    └── Search API Test.bru
```

## Getting Started

1. Import the Bruno collection
2. Set up the environment variables
3. Test the connection with the Authentication endpoint
4. Explore the hotel and room endpoints
5. Test booking creation workflow

## Known Issues

### ✅ Order Creation API - ISSUE RESOLVED!

**Problem**: Order creation through `/api/orders` was failing with "Can't save Order Payment" error

**Root Cause**: The API was using `WebserviceOrder` module which conflicted with `PaymentModule::validateOrder()`

**✅ SOLUTION IMPLEMENTED**: Use the same configuration as the admin interface:

```xml
<order>
  <module>bo_order</module>          <!-- Admin module instead of wsorder -->
  <payment_type>2</payment_type>     <!-- PAY_AT_HOTEL instead of ONLINE -->
  <current_state>10</current_state>  <!-- Awaiting Payment instead of Paid -->
  <total_paid_real>0</total_paid_real> <!-- Unpaid order -->
  <!-- ... other required fields ... -->
</order>
```

**Key Changes**:
- ✅ **module**: `bo_order` (same as admin) instead of `wsorder`
- ✅ **payment_type**: `2` (PAY_AT_HOTEL) bypasses online payment validation
- ✅ **current_state**: `10` (Awaiting Payment) avoids immediate payment processing
- ✅ **total_paid_real**: `0` prevents payment validation conflicts

**Result**: ✅ Orders now create successfully with `HTTP 201 Created`

**Technical Details**:
- Fixed: PaymentModule.php:484 - `$order->addOrderPayment()` now succeeds
- Solution: Use `BoOrder` module pattern from AdminOrdersController.php:2146
- Files: Order.php:1698-1731 (API) now matches AdminOrdersController.php:2146 (Admin)

## Notes

- All POST/PUT requests typically require XML format in the request body
- The API supports both individual operations and bulk operations  
- PrestaShop's webservice framework provides consistent behavior across all endpoints
- The system maintains referential integrity between hotels, room types, and bookings
- **✅ Complete booking flow now works**: Customer → Address → Cart → Bookings → Order (all via API)
- **Order Creation**: Now fully functional using the `bo_order` module configuration