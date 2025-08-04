# Categories

This API endpoint allows you to manage product categories.

## Functionality

- **GET /categories**: Retrieves a list of all product categories.
- **GET /categories/{id}**: Retrieves a specific category by its ID.
- **POST /categories**: Creates a new category.
- **PUT /categories/{id}**: Updates an existing category.
- **DELETE /categories/{id}**: Deletes a category.

## What it receives

When creating or updating a category, the API expects a JSON object with the following properties:

- `name`: The name of the category.
- `id_parent`: The ID of the parent category.
- `active`: Whether the category is active or not.

## What it does

The API creates, updates, or deletes a product category in the QloApps database.

## What it returns

- **GET**: Returns a JSON object representing the category or a list of categories.
- **POST**: Returns a JSON object representing the newly created category.
- **PUT**: Returns a JSON object representing the updated category.
- **DELETE**: Returns an empty response.
