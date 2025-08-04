# Combinations

This API endpoint allows you to manage product combinations.

## Functionality

- **GET /combinations**: Retrieves a list of all product combinations.
- **GET /combinations/{id}**: Retrieves a specific combination by its ID.
- **POST /combinations**: Creates a new combination.
- **PUT /combinations/{id}**: Updates an existing combination.
- **DELETE /combinations/{id}**: Deletes a combination.

## What it receives

When creating or updating a combination, the API expects a JSON object with the following properties:

- `id_product`: The ID of the product for which the combination is being created.
- `id_product_attribute`: The ID of the product attribute for the combination.
- `price`: The price of the combination.
- `quantity`: The quantity of the combination.

## What it does

The API creates, updates, or deletes a product combination in the QloApps database.

## What it returns

- **GET**: Returns a JSON object representing the combination or a list of combinations.
- **POST**: Returns a JSON object representing the newly created combination.
- **PUT**: Returns a JSON object representing the updated combination.
- **DELETE**: Returns an empty response.
