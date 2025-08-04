# Tags

This API endpoint allows you to manage tags.

## Functionality

- **GET /tags**: Retrieves a list of all tags.
- **GET /tags/{id}**: Retrieves a specific tag by its ID.
- **POST /tags**: Creates a new tag.
- **PUT /tags/{id}**: Updates an existing tag.
- **DELETE /tags/{id}**: Deletes a tag.

## What it receives

When creating or updating a tag, the API expects a JSON object with the following properties:

- `name`: The name of the tag.
- `id_lang`: The ID of the language for the tag.

## What it does

The API creates, updates, or deletes a tag in the QloApps database.

## What it returns

- **GET**: Returns a JSON object representing the tag or a list of tags.
- **POST**: Returns a JSON object representing the newly created tag.
- **PUT**: Returns a JSON object representing the updated tag.
- **DELETE**: Returns an empty response.
