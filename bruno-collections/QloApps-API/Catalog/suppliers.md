# Suppliers

This API endpoint allows you to manage suppliers.

## Functionality

- **GET /suppliers**: Retrieves a list of all suppliers.
- **GET /suppliers/{id}**: Retrieves a specific supplier by its ID.
- **POST /suppliers**: Creates a new supplier.
- **PUT /suppliers/{id}**: Updates an existing supplier.
- **DELETE /suppliers/{id}**: Deletes a supplier.

## What it receives

When creating or updating a supplier, the API expects a JSON object with the following properties:

- `name`: The name of the supplier.
- `active`: Whether the supplier is active or not.

## What it does

The API creates, updates, or deletes a supplier in the QloApps database.

## What it returns

- **GET**: Returns a JSON object representing the supplier or a list of suppliers.
- **POST**: Returns a JSON object representing the newly created supplier.
- **PUT**: Returns a JSON object representing the updated supplier.
- **DELETE**: Returns an empty response.
