# API Endpoint Design: Book Now

## Endpoint: `POST /api/book_now`

**Description:** Creates a preliminary booking by adding a specific room to a new or existing cart for a guest. This endpoint is the next step after a user has identified an available room using the `/api/availability` endpoint.

**Authentication:** Basic Auth with a valid webservice key.

### Request Body (JSON)

```json
{
  "hotel_id": 1,
  "id_product": 1,
  "id_room": 101,
  "check_in": "2025-09-15",
  "check_out": "2025-09-20",
  "adults": 2,
  "children": 1
}
```

### Parameters

| Field       | Type    | Required | Description                                                                 |
| :---------- | :------ | :------- | :-------------------------------------------------------------------------- |
| `hotel_id`  | Integer | Yes      | The ID of the hotel where the room is located.                              |
| `id_product`| Integer | Yes      | The product ID of the room type.                                            |
| `id_room`   | Integer | Yes      | The specific ID of the room to be booked.                                   |
| `check_in`  | String  | Yes      | The check-in date in `YYYY-MM-DD` format.                                   |
| `check_out` | String  | Yes      | The check-out date in `YYYY-MM-DD` format.                                  |
| `adults`    | Integer | Yes      | The number of adults for the booking.                                       |
| `children`  | Integer | No       | The number of children for the booking. Defaults to 0.                      |

### Responses

#### Success Response (201 Created)

```json
{
  "success": true,
  "timestamp": "2025-08-04 18:30:00",
  "booking_details": {
    "id_cart": 25,
    "id_cart_book_data": 15,
    "message": "Room successfully added to cart. Proceed to order creation."
  }
}
```

#### Error Responses

*   **400 Bad Request (Invalid Parameters):**
    ```json
    {
      "success": false,
      "error": "Invalid parameters: 'hotel_id' is required and must be an integer.",
      "timestamp": "2025-08-04 18:31:00"
    }
    ```
*   **422 Unprocessable Entity (Booking Conflict):**
    ```json
    {
      "success": false,
      "error": "The selected room is no longer available for the chosen dates.",
      "timestamp": "2025-08-04 18:32:00"
    }
    ```
*   **500 Internal Server Error:**
    ```json
    {
      "success": false,
      "error": "An internal error occurred while processing the booking.",
      "timestamp": "2025-08-04 18:33:00"
    }
    ```

---
