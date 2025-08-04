# Room Type Features

This API endpoint allows you to manage room type features.

## Functionality

- **GET /room_type_features**: Retrieves a list of all room type features.
- **GET /room_type_features/{id}**: Retrieves a specific feature by its ID.
- **POST /room_type_features**: Creates a new feature.
- **PUT /room_type_features/{id}**: Updates an existing feature.
- **DELETE /room_type_features/{id}**: Deletes a feature.

## What it receives

When creating or updating a feature, the API expects a JSON object with the following properties:

- `name`: The name of the feature.
- `id_feature_value`: The ID of the feature value.

## What it does

The API creates, updates, or deletes a room type feature in the QloApps database.

## What it returns

- **GET**: Returns a JSON object representing the feature or a list of features.
- **POST**: Returns a JSON object representing the newly created feature.
- **PUT**: Returns a JSON object representing the updated feature.
- **DELETE**: Returns an empty response.
