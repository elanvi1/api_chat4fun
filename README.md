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


## Endpoint: `/friendship`
### Method: `GET`
### Payload: `none`
### Purpose: It retrieves information about all contacts, the last message from the chat with each contact and info about the friendship with each contact. Requires access token.


## Endpoint: `/friendship`
### Method: `POST`
### Payload: `{'friend_id'}`
### Purpose: Depending on the existence or status of the friendships there are multiple scenarios, for more info check FriendshipController.php -> store method . Requires access token.


## Endpoint: `/friendship/{friendship}`
### Method: `PATCH/PUT`
### Payload: `{'status','alias'}`
### Purpose: It is used to change the status of the friendship or the alias of the contact . Requires access token.


## Endpoint: `/friendship/{friendship}/handleAcceptOrReject`
### Method: `POST`
### Payload: `{'status'}`
### Purpose: It is used by the main user to reject or accept a friendship request. Requires access token.


## Endpoint: `/active`
### Method: `POST`
### Payload: `{'user_id'}`
### Purpose: Makes the main user active on a certain contact chat by changing the value of "presence_friend" attribute in the friendships table. Requires access token.


## Endpoint: `/inactive`
### Method: `POST`
### Payload: `{'user_id'}`
### Purpose: Makes the main user inactive on a certain contact chat by changing the value of "presence_friend" attribute in the friendships table. Requires access token.


## Endpoint: `/friendship/{friendship}/resetUnreadMessages`
### Method: `GET`
### Payload: `none`
### Purpose: Resets the number of unread messages for a chat by changing the "unread_messages" attribute in the friendships table. Requires access token.


## Endpoint: `/userIdsPendingFriendships`
### Method: `GET`
### Payload: `none`
### Purpose: Returns the ids of the users with which the main user has a friendship with "pending" status. This means that a friendship request was sent by one of the two but no response was received. Requires access token.


## Endpoint: `/message`
### Method: `POST`
### Payload: `{'sender_id', 'receiver_id', 'receiver_type','message'}`
### Purpose: It is used to store information about a message in the DB. Requires access token.


## Endpoint: `/message/{message}`
### Method: `DELETE`
### Payload: `none`
### Purpose: It is used to delete information about a message from the DB. Requires access token.


## Endpoint: `/notification`
### Method: `GET`
### Payload: `none`
### Purpose: It is used to retrieve the main users notifications from the DB. Requires access token.


## Endpoint: `/notification/{notification}`
### Method: `PATCH/PUT`
### Payload: `{'status'}`
### Purpose: It is used to change the status of a notification. Requires access token.


# TO REPLACE
After installing all the composer packages, go to the TO REPLACE IN SANCTUM folder and copy the `php` files in there to the path specied in the `folder_location.txt` file
