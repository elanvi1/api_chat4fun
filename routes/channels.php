<?php

use App\Models\User;
use App\Models\Group;
use App\Models\Friendship;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// User $channelUser
Broadcast::channel('notifications.to.user.{id}', function($authUser, $id){
    return (int)$authUser->id === (int)$id;
});



Broadcast::channel('notifications.from.user.{id}', function($authUser, $id){
   $friendship = Friendship::where([
    ['main_id',$authUser->id],
    ['friend_id',$id]
   ])->first();

   $friendshipSecond = Friendship::where([
    ['main_id',$id],
    ['friend_id',$authUser->id]
   ])->first();

   if(!isset($friendship) || !isset($friendshipSecond)){
       return false;
   }

   if($friendship->status !== Friendship::ACCEPTED_STATUS || ($friendshipSecond->status !== Friendship::ACCEPTED_STATUS && $friendshipSecond->status !== Friendship::DELETED_STATUS )){
       return false;
   }

   return true;
});

Broadcast::channel('notifications.group.{id}', function($authUser, $id){
    $group = Group::whereId($id)->first();

    if(isset($group)){
        return $group->users->contains(function ($user) use ($authUser){
            return (int)$user->id === (int)$authUser->id;
        });
    }
    
    return false;
});

Broadcast::channel('chat.between.user.{id1}.and.{id2}',function($authUser,$id1,$id2){
    if(((int)$authUser->id !== (int)$id1) && ((int)$authUser->id !== (int)$id2)){
        return false;
    }

    return true;
});

Broadcast::channel('chat.group.{id}',function($authUser,$id){
    $group = Group::whereId($id)->first();

    if(isset($group)){
        return $group->users->contains(function ($user) use ($authUser){
            return (int)$user->id === (int)$authUser->id;
        });
    }

    return false;
});