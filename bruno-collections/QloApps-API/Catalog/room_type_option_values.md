# Room Type Option Values

This API endpoint allows you to manage room type option values.

## Functionality

- **GET /room_type_option_values**: Retrieves a list of all room type option values.
- **GET /room_type_option_values/{id}**: Retrieves a specific option value by its ID.
- **POST /room_type_option_values**: Creates a new option value.
- **PUT /room_type_option_values/{id}**: Updates an existing option value.
- **DELETE /room_type_option_values/{id}**: Deletes an option value.

## What it receives

When creating or updating an option value, the API expects a JSON object with the following properties:

- `id_attribute_group`: The ID of the attribute group to which the value belongs.
- `name`: The name of the option value.

## What it does

The API creates, updates, or deletes a room type option value in the QloApps database.

## What it returns

- **GET**: Returns a JSON object representing the option value or a list of option values.
- **POST**: Returns a JSON object representing the newly created option value.
- **PUT**: Returns a JSON object representing the updated option value.
- **DELETE**: Returns an empty response.
