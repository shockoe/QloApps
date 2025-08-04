# Services

This API endpoint allows you to manage services.

## Functionality

- **GET /services**: Retrieves a list of all services.
- **GET /services/{id}**: Retrieves a specific service by its ID.
- **POST /services**: Creates a new service.
- **PUT /services/{id}**: Updates an existing service.
- **DELETE /services/{id}**: Deletes a service.

## What it receives

When creating or updating a service, the API expects a JSON object with the following properties:

- `name`: The name of the service.
- `description`: The description of the service.
- `price`: The price of the service.

## What it does

The API creates, updates, or deletes a service in the QloApps database.

## What it returns

- **GET**: Returns a JSON object representing the service or a list of services.
- **POST**: Returns a JSON object representing the newly created service.
- **PUT**: Returns a JSON object representing the updated service.
- **DELETE**: Returns an empty response.
