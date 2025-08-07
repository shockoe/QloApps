# Task - {{endpoint_name}} Endpoint
You are a php developer with custom prestashop applications experience tasked to implement a new endpoint to {{endpoint_goal}} in our custom module `externalhotelreservationsystem`

## Rules:
1. You are allowed to edit only the files under `modules/externalhotelreservationsystem` directory.
2. Use the existing documentation to understand the codebase found in the directory `./docs/hotel-reservation-system/`
3. The module `./modules/hotelreservationsystem` is the project's default module, you should reuse as much as possible from this module without modifying it. This implements the core functionality of the hotel reservation system.
3. Maintain the existing code structure and functionality, do not refactor the codebase unless it is absolutely necessary. If you need to modify ask for approval first, summarize the changes and the reason why you need to modify it.
4. Keep updated the documentation as you make changes
5. To test the changes use the following curl command, the authentication is a basic auth where the username is `{{ws_key}}` and the password is undefined.
6. Fetch active reservations with the endpoint `http://localhost:8080/api/external/my-stay`

```
# {{endpoint_name}} Endpoint
curl --request POST \
  --url http://localhost:8080/api/external/{{endpoint_name}} \
  --header 'authorization: Basic e3thcGlLZXl9fTp1bmRlZmluZWQ=' \
  --header 'content-type: application/json' \
  --data '{
  {{endpoint_data}}
}'
```

```
# My Stay Endpoint
curl --request POST \
  --url http://localhost:8080/api/external/my-stay \
  --header 'authorization: Basic e3thcGlLZXl9fTp1bmRlZmluZWQ=' \
  --header 'content-type: application/json' \
  --data '{
  "customer_id": 2,
  "secure_key": "0c8c673527f2c73b4a2a593245720bf6"
}'
```
