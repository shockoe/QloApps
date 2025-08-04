# Room Type Options

This API endpoint allows you to manage room type options.

## Functionality

- **GET /room_type_options**: Retrieves a list of all room type options.
- **GET /room_type_options/{id}**: Retrieves a specific option by its ID.
- **POST /room_type_options**: Creates a new option.
- **PUT /room_type_options/{id}**: Updates an existing option.
- **DELETE /room_type_options/{id}**: Deletes an option.

## What it receives

When creating or updating an option, the API expects a JSON object with the following properties:

- `name`: The name of the option.
- `public_name`: The public name of the option.
- `group_type`: The type of the option group.

## What it does

The API creates, updates, or deletes a room type option in the QloApps database.

## What it returns

- **GET**: Returns a JSON object representing the option or a list of options.
- **POST**: Returns a JSON object representing the newly created option.
- **PUT**: Returns a JSON object representing the updated option.
- **DELETE**: Returns an empty response.
