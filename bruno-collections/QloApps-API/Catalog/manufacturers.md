# Manufacturers

This API endpoint allows you to manage manufacturers.

## Functionality

- **GET /manufacturers**: Retrieves a list of all manufacturers.
- **GET /manufacturers/{id}**: Retrieves a specific manufacturer by its ID.
- **POST /manufacturers**: Creates a new manufacturer.
- **PUT /manufacturers/{id}**: Updates an existing manufacturer.
- **DELETE /manufacturers/{id}**: Deletes a manufacturer.

## What it receives

When creating or updating a manufacturer, the API expects a JSON object with the following properties:

- `name`: The name of the manufacturer.
- `active`: Whether the manufacturer is active or not.

## What it does

The API creates, updates, or deletes a manufacturer in the QloApps database.

## What it returns

- **GET**: Returns a JSON object representing the manufacturer or a list of manufacturers.
- **POST**: Returns a JSON object representing the newly created manufacturer.
- **PUT**: Returns a JSON object representing the updated manufacturer.
- **DELETE**: Returns an empty response.
