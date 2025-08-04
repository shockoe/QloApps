# Room Types

This API endpoint allows you to manage room types.

## Functionality

- **GET /room_types**: Retrieves a list of all room types.
- **GET /room_types/{id}**: Retrieves a specific room type by its ID.
- **POST /room_types**: Creates a new room type.
- **PUT /room_types/{id}**: Updates an existing room type.
- **DELETE /room_types/{id}**: Deletes a room type.

## What it receives

When creating or updating a room type, the API expects a JSON object with the following properties:

- `name`: The name of the room type.
- `description`: The description of the room type.
- `price`: The price of the room type.
- `id_category_default`: The ID of the default category for the room type.

## What it does

The API creates, updates, or deletes a room type in the QloApps database.

## What it returns

- **GET**: Returns a JSON object representing the room type or a list of room types.
- **POST**: Returns a JSON object representing the newly created room type.
- **PUT**: Returns a JSON object representing the updated room type.
- **DELETE**: Returns an empty response.
