# Room Type Feature Values

This API endpoint allows you to manage room type feature values.

## Functionality

- **GET /room_type_feature_values**: Retrieves a list of all room type feature values.
- **GET /room_type_feature_values/{id}**: Retrieves a specific feature value by its ID.
- **POST /room_type_feature_values**: Creates a new feature value.
- **PUT /room_type_feature_values/{id}**: Updates an existing feature value.
- **DELETE /room_type_feature_values/{id}**: Deletes a feature value.

## What it receives

When creating or updating a feature value, the API expects a JSON object with the following properties:

- `id_feature`: The ID of the feature to which the value belongs.
- `value`: The value of the feature.

## What it does

The API creates, updates, or deletes a room type feature value in the QloApps database.

## What it returns

- **GET**: Returns a JSON object representing the feature value or a list of feature values.
- **POST**: Returns a JSON object representing the newly created feature value.
- **PUT**: Returns a JSON object representing the updated feature value.
- **DELETE**: Returns an empty response.
