Hello {{$user->name}},

This email was sent because a request was made for an email change in the chat4fun app. If it wasn't you who made the request please contact the moderators of the app on the contact page. If it was you please click the link below in order to confirm the new email:

{{route('verify', $user->verification_token)}}