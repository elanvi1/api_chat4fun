Hello {{$user->name}},

Thank you for reactivating your account. You can finalize the process by clicking the link below, after which you can just login as usual:

{{route('reactivate', $user->verification_token)}}