# Complete Booking Workflow

This document outlines the step-by-step process to create a booking in QloApps using the API.

## Step-by-Step Booking Process

### 1. **Create/Get Customer** (Optional for registered users)
```xml
POST /api/customers
```
**Purpose**: Create a customer account or use existing customer ID.
**Required for**: Registered customer bookings
**Skip if**: Using guest checkout

### 2. **Create Customer Address**
```xml
POST /api/addresses
```
**Purpose**: Create delivery and invoice address for the customer.
**Required fields**:
- `id_customer`: Customer ID from step 1
- `id_country`: Country ID (21 = United States)
- `alias`: Address name (e.g., "Home")
- `firstname`, `lastname`: Customer name
- `address1`, `city`, `postcode`: Address details

### 3. **Create Shopping Cart**
```xml
POST /api/carts
```
**Purpose**: Initialize a new shopping cart for the booking session.
**Required fields**:
- `id_lang`: Language ID (1 = English)
- `id_currency`: Currency ID (1 = default)
- `id_customer`: Customer ID (0 for guest)
- `id_guest`: Guest session ID
- `secure_key`: Security validation key

### 4. **Add Room to Cart**
```xml
POST /api/cart_bookings
```
**Purpose**: Add specific room and dates to the cart.
**Required fields**:
- `id_cart`: Cart ID from step 2
- `id_room`: Specific room inventory ID
- `id_hotel`: Hotel ID
- `date_from`: Check-in date (YYYY-MM-DD)
- `date_to`: Check-out date (YYYY-MM-DD)
- `adults`: Number of adults
- `children`: Number of children

### 5. **Create Order** (Finalize Booking)
```xml
POST /api/orders
```
**Purpose**: Convert cart items to confirmed booking.
**Required fields**:
- `id_customer`: Customer ID
- `id_cart`: Cart ID with booking items
- `current_state`: Order state (1 = Payment accepted)
- `payment`: Payment method description
- `total_paid`: Total amount
- `secure_key`: Security validation
- `id_address_delivery`: Delivery address ID (from step 2)
- `id_address_invoice`: Invoice address ID (from step 2)

## Example Complete Workflow

### Step 1: Create Customer (if needed)
```bash
curl -u APIKEY: -X POST "http://localhost:8080/api/customers?output_format=JSON" \
  -H "Content-Type: application/xml" \
  -d '<?xml version="1.0" encoding="UTF-8"?>
<qloapps xmlns:xlink="http://www.w3.org/1999/xlink">
  <customer>
    <id_default_group>3</id_default_group>
    <id_lang>1</id_lang>
    <firstname>John</firstname>
    <lastname>Doe</lastname>
    <email>john.doe@example.com</email>
    <phone>+1234567890</phone>
    <passwd>password123</passwd>
    <active>1</active>
  </customer>
</qloapps>'
```

### Step 2: Create Cart
```bash
curl -u APIKEY: -X POST "http://localhost:8080/api/carts?output_format=JSON" \
  -H "Content-Type: application/xml" \
  -d '<?xml version="1.0" encoding="UTF-8"?>
<qloapps xmlns:xlink="http://www.w3.org/1999/xlink">
  <cart>
    <id_lang>1</id_lang>
    <id_currency>1</id_currency>
    <id_customer>1</id_customer>
    <id_guest>1</id_guest>
    <secure_key>b44a6d9efd7a0076a0fbce6b15eaf3b1</secure_key>
  </cart>
</qloapps>'
```

### Step 3: Add Room to Cart
```bash
curl -u APIKEY: -X POST "http://localhost:8080/api/cart_bookings?output_format=JSON" \
  -H "Content-Type: application/xml" \
  -d '<?xml version="1.0" encoding="UTF-8"?>
<qloapps xmlns:xlink="http://www.w3.org/1999/xlink">
  <cart_booking>
    <id_cart>1</id_cart>
    <id_room>1</id_room>
    <id_hotel>1</id_hotel>
    <date_from>2025-07-01</date_from>
    <date_to>2025-07-05</date_to>
    <adults>2</adults>
    <children>0</children>
  </cart_booking>
</qloapps>'
```

### Step 4: Create Order (Confirm Booking)
```bash
curl -u APIKEY: -X POST "http://localhost:8080/api/orders?output_format=JSON" \
  -H "Content-Type: application/xml" \
  -d '<?xml version="1.0" encoding="UTF-8"?>
<qloapps xmlns:xlink="http://www.w3.org/1999/xlink">
  <order>
    <id_customer>1</id_customer>
    <id_cart>1</id_cart>
    <current_state>1</current_state>
    <payment>Credit Card</payment>
    <total_paid>300.00</total_paid>
    <total_paid_tax_incl>300.00</total_paid_tax_incl>
    <total_paid_tax_excl>255.00</total_paid_tax_excl>
    <total_paid_real>300.00</total_paid_real>
    <id_currency>1</id_currency>
    <id_lang>1</id_lang>
    <secure_key>b44a6d9efd7a0076a0fbce6b15eaf3b1</secure_key>
  </order>
</qloapps>'
```

## Important Notes

### Before Creating Booking:
1. **Check room availability** using `/api/hotel_rooms`
2. **Get pricing information** using `/api/feature_prices`
3. **Validate dates** (check-in before check-out, future dates)
4. **Verify hotel and room exist** using hotel/room APIs

### Security Considerations:
- Always validate secure_key matches customer/guest session
- Verify payment before setting order state to "Payment accepted"
- Check room availability before confirming booking
- Implement timeout for cart sessions

### Error Handling:
- **Room unavailable**: Check dates and try alternative rooms
- **Invalid customer**: Verify customer exists or create new one
- **Cart expired**: Create new cart and re-add items
- **Payment failed**: Keep order in "Awaiting payment" state

### Order States:
- **1**: Payment accepted (confirmed booking)
- **2**: Processing (being prepared)
- **6**: Canceled (booking canceled)
- **10**: Awaiting payment (pending payment)

## Booking Confirmation

After successful order creation:
1. Order ID is returned
2. Booking confirmation email is sent
3. Room booking records are created in `room_bookings`
4. Room availability is updated
5. Customer can view booking in their account

## Alternative: Guest Booking

For guest bookings:
1. Set `id_customer` to 0
2. Use `id_guest` for session tracking
3. Provide guest information in order
4. Guest can track booking via order reference

This complete workflow ensures proper booking creation with all necessary validations and confirmations.