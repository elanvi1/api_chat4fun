# Endpoint, method, payload and purpose

## Endpoint: ## `/login`
### Method: `POST`
### Payload: `{'email','password','device_name'}`
### Purpose: It is used to log in the user in the app. Doesn't require access token.

## Endpoint: ## `/logout`
### Method: `DELETE`
### Payload: `none`
### Purpose: It is used to delete the token from the DB and show the user offline. Requires access token

## Endpoint: ## `/refreshToken`
### Method: `GET`
### Payload: `none`
### Purpose: It is used to get a new token and refresh token. Requires refresh token

## Endpoint: ## `/user/verify/{token}`
### Method: `GET`
### Payload: `none`
### Purpose: It is used to verify the email address, the link is provided to the user via his/her email address. Doesn't require access token.

## Endpoint: ## `/user/{user}/resend`
### Method: `GET`
### Payload: `none`
### Purpose: It is used to resend the verification email. Doesn't require access token.

## Endpoint: ## `/user/{user}/sendReactivateEmail`
### Method: `GET`
### Payload: `none`
### Purpose: It is used to send an account reactivation email. Doesn't require access token.

## Endpoint: ## `user/reactivate/{token}`
### Method: `GET`
### Payload: `none`
### Purpose: It is used to reactivate the account. Doesn't require access token.
