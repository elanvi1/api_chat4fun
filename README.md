# Endpoint, method, payload and purpose

## Endpoint: `/login`
### Method: `POST`
### Payload: `{'email','password','device_name'}`
### Purpose: It is used to log in the user in the app. Doesn't require access token.


## Endpoint: `/logout`
### Method: `DELETE`
### Payload: `none`
### Purpose: It is used to delete the token from the DB and show the user offline. Requires access token


## Endpoint: `/refreshToken`
### Method: `GET`
### Payload: `none`
### Purpose: It is used to get a new token and refresh token. Requires refresh token


## Endpoint: `/user/verify/{token}`
### Method: `GET`
### Payload: `none`
### Purpose: It is used to verify the email address, the link is provided to the user via his/her email address. Doesn't require access token.


## Endpoint: `/user/{user}/resend`
### Method: `GET`
### Payload: `none`
### Purpose: It is used to resend the verification email. Doesn't require access token.


## Endpoint: `/user/{user}/sendReactivateEmail`
### Method: `GET`
### Payload: `none`
### Purpose: It is used to send an account reactivation email. Doesn't require access token.


## Endpoint: `user/reactivate/{token}`
### Method: `GET`
### Payload: `none`
### Purpose: It is used to reactivate the account. Doesn't require access token.



## Endpoint: `/user`
### Method: `GET`
### Payload: `{'username'}`
### Purpose: It is used to retrieve basic information about the users that match a certain serch criteria. Requires access token.


## Endpoint: `/user`
### Method: `POST`
### Payload: `{'name', 'username' , 'email', 'password', 'about', 'image'}`
### Purpose: It is used to store information about a user in the db, when the user creates the account. Doesn't require access token.


## Endpoint: `/user/{userId}`
### Method: `GET`
### Payload: `none`
### Purpose: It is used to retrieve information about the authenticated(main) user. Requires access token.


## Endpoint: `/user/{userId}`
### Method: `PATCH/PUT`
### Payload: `{'name', 'username' , 'email', 'password', 'about', 'image'}`
### Purpose: It is used to change information about the authenticated(main) user. Requires access token.


## Endpoint: `/user/{userId}`
### Method: `DELETE`
### Payload: `none`
### Purpose: It is used to remove information about the authenticated(main) user from the DB, basically deactivating the account. Requires access token.


## Endpoint: `/user/group`
### Method: `POST`
### Payload: `{'group_id','user_id'}`
### Purpose: It is used to create a connection between a group and a user by adding an entry in the group_user table, basically adding a user to a group. Requires access token.


## Endpoint: `/user/{userId}/group/{groupId}`
### Method: `PATCH/PUT`
### Payload: `{'permission_id'}`
### Purpose: It is used to change the permission of a user in a certain group. Requires access token.


## Endpoint: `/user/{userId}/group/{groupId}`
### Method: `DELETE`
### Payload: `none`
### Purpose: It is used to remove the connection between a user and a group, basically removing a user from a group. Requires access token.


## Endpoint: `/group/{group}/resetUnreadMessages`
### Method: `GET`
### Payload: `none`
### Purpose: It is used to reset the unread messages of the main user in a group chat. Requires access token.


## Endpoint: `/conversation_with_user/{user}?page=x`
### Method: `GET`
### Payload: `none`
### Purpose: Retrieves the messages of a contact chat. Requires access token and the page must be specified as a query parameter.


## Endpoint: `/online`
### Method: `GET`
### Payload: `none`
### Purpose: It is used to show the main user online to his contacts. Requires access token.


## Endpoint: `/offline`
### Method: `GET`
### Payload: `none`
### Purpose: It is used to show the main user offline to his contacts. Requires access token.


## Endpoint: `/group`
### Method: `GET`
### Payload: `none`
### Purpose: It is used to retrieve information about each group the main user is in. Requires access token.


## Endpoint: `/group`
### Method: `POST`
### Payload: `{'name', 'description', 'image'}`
### Purpose: It is used to store information about a newly created group in the groups table. Requires access token.


## Endpoint: `/group/{group}`
### Method: `PATCH/PUT`
### Payload: `{'name', 'description', 'image'}`
### Purpose: It is used to change information about a group in the groups table. Requires access token.


## Endpoint: `/group/{group}`
### Method: `DELETE`
### Payload: `none`
### Purpose: It is used to delete information about a group in the groups table. Requires access token.


## Endpoint: `/group/{group}/messages?page=x`
### Method: `GET`
### Payload: `none`
### Purpose: It is used to delete information about a group in the groups table. Requires access token and the page must be specified as a query parameter
