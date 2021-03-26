<?php

namespace App\Http\Controllers\Message;

use App\Models\User;
use App\Models\Group;
use App\Models\Message;
use App\Models\GroupUser;
use App\Events\UserActive;
use App\Models\Friendship;
use App\Events\UserInactive;
use Illuminate\Http\Request;
use App\Events\UserMessageDeleted;
use Illuminate\Support\Facades\DB;
use App\Events\GroupMessageDeleted;
use App\Events\UserMessageReceived;
use App\Events\GroupMessageReceived;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiController;
use App\Transformers\MessageTransformer;
use App\Events\UpdateUnreadMessagesContact;

class MessageController extends ApiController
{
    // Using the constructor in order to use middleware. For more info check app->Http->Middleware->TransformInput.php
    public function __construct()
    {
        parent::__construct();

        $this->middleware('transform.input:'. MessageTransformer::class)->only(['store','destroy']);
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    // Method used at "/message" endpoint for the "POST" request. It is used to store information about a message in the DB.
    public function store(Request $request)
    {
        $rules = [
            'sender_id' => 'required|numeric',
            'messageable_id' => 'required|numeric',
            'messageable_type' => 'required|in:user,group',
            'message' => 'required|max:4294967295',
        ];

        $this->validate($request, $rules);

        // Making checks to be sure that there are no unauthorized changes.
        if((int)$request->user()->id !== (int)$request->sender_id){
            return $this->errorResponse('sender_id must match authenticated user id',403);
        }

        if($request->messageable_type === 'user'){
            $request->messageable_type = User::class;
        }else{
            $request->messageable_type = Group::class;
        }

        User::whereId($request->sender_id)->firstOrFail();

        $friendship = null;

        if($request->messageable_type === User::class){
            User::withTrashed()->whereId($request->messageable_id)->firstOrFail();
            $friendship = Friendship::where([
                ['main_id',$request->sender_id],
                ['friend_id',$request->messageable_id]
            ])->firstOrFail();

            if($friendship->status === Friendship::PENDING_STATUS){
                return $this->errorResponse('Can\'t send messages to this user since the friendship request is still pending',403);
            }

            if($friendship->status === Friendship::REMOVED_STATUS){
                return $this->errorResponse('This user is no longer in your contacts list, therefor you can\'t send messages to them',403);
            }
        }else{
            Group::withTrashed()->whereId($request->messageable_id)->firstOrFail();
            GroupUser::withTrashed()->where([
                ['group_id',$request->messageable_id],
                ['user_id',$request->sender_id]
            ])->firstOrFail();
        }

        $data = $request->only(['sender_id','messageable_id','messageable_type','message']);

        $data['messageable_type'] = $request->messageable_type;

        $data['status'] = Message::SENT_STATUS;

        $message = null;

        DB::transaction(function() use ($data,$request,$friendship, &$message){
            $message = Message::create($data);
            
            // Checking to see if the message was sent to a contact or a group
            if($request->messageable_type === User::class){
                $secondFriendship = Friendship::where([
                    ['main_id',$request->messageable_id],
                    ['friend_id',$request->sender_id]
                ])->firstOrFail();

                // Checking to see if the user is active on the chat at the time the message is sent
                if($friendship->presence_friend === Friendship::USER_INACTIVE){
                    // If the user is not active checking if friendship 2 status is 'accepted' and if it is the number of unread messages for that chat is increased
                    if($secondFriendship->status === Friendship::ACCEPTED_STATUS || $secondFriendship->status === Friendship::REMOVED_STATUS){
                        $secondFriendship->unread_messages += 1;
                        $secondFriendship->save();
                        
                        if($secondFriendship->status === Friendship::ACCEPTED_STATUS){
                            broadcast(new UpdateUnreadMessagesContact($request->messageable_id,$request->sender_id,$secondFriendship->unread_messages));
                        }
                    }
                }elseif($friendship->presence_friend === Friendship::USER_ACTIVE ){
                    // If the user is active on that chat then the message status is changed to "read" only if the status of friendship 2 is "accepted"
                    if($secondFriendship->status === Friendship::ACCEPTED_STATUS){
                        $message->status= Message::READ_STATUS;
                        $message->save();
                    }
                }
            }else{
                $group = Group::whereId($request->messageable_id)->firstOrFail();

                // Increasing the number of unread messages for each user in the group
                foreach($group->users as $user){
                    if($user->id !== $request->sender_id){
                        $user->pivot->unread_messages +=1;
                        $user->pivot->save();
                    }
                }
            }
        });

        // Changing the attribute names of the messages using a transformer. For more info check app->Transformers->MessageTransformer.php
        $message = fractal($message, new $message->transformer)->toArray()['data'];

        if($message['receiver_type'] === 'user'){
            $friendshipSecond = Friendship::where([
                ['main_id',$request->messageable_id],
                ['friend_id',$request->sender_id]
            ])->firstOrFail();
    
            // Message is broadcasted to the contact only if the status of the second friendship is "accepted"
            if($friendshipSecond->status === Friendship::ACCEPTED_STATUS){
                broadcast(new UserMessageReceived($message))->toOthers();
            }
        }

        if($message['receiver_type'] === 'group'){
            broadcast(new GroupMessageReceived($message))->toOthers();
        }

        return $this->showInfo($message,201);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Message  $message
     * @return \Illuminate\Http\Response
     */

    // Method used at "/message" endpoint for the "DELETE" request. It is used to delete information about a message from the DB.
    public function destroy(Request $request,Message $message)
    {
        $user = $request->user();

        // Making checks to be sure that there are no unauthorized changes.
        if(($user->id !== $message->sender_id) && $message->messageable_type === User::class){
            return $this->errorResponse('User that sent the message is different from the user that wants to delete it',403);
        }

        if($message->messageable_type === Group::class){
            $groupUser = GroupUser::where([
                ['group_id',$message->messageable_id],
                ['user_id', $user->id]
            ])->firstOrFail();

            // Checking if the main user has the right permission to delete a group message
            if(($groupUser->permission_id !== 2) && ($groupUser->permission_id !== 4) && ($user->id !== $message->sender_id)){
                return $this->errorResponse('Only users with admin or edit permissions can delete other users messages',403);
            }
        }

        DB::transaction(function() use ($user, &$message){
            $msgStatus = $message->status;
            $message->delete();

            // Changing the attribute names of the messages using a transformer. For more info check app->Transformers->MessageTransformer.php
            $message = fractal($message, new $message->transformer)->toArray()['data'];

            if($message['receiver_type'] === 'user'){
                $friendshipSecond = Friendship::where([
                    ['main_id',$message['receiver_id']],
                    ['friend_id',$user->id]
                ])->firstOrFail();
        
                // Broadcasting to the contact that the message was deleted only if friendship 2 status is 'accepted'
                if($friendshipSecond->status === Friendship::ACCEPTED_STATUS){
                    broadcast(new UserMessageDeleted($message))->toOthers();
                }

                // If the message has a 'sent' status and is deleted then the number of unread messages will decrease by 1(for the contact)
                if($msgStatus === Message::SENT_STATUS && $friendshipSecond->unread_messages > 0){
                    $friendshipSecond->unread_messages -= 1;
                    $friendshipSecond->save();

                    broadcast(new UpdateUnreadMessagesContact($message['receiver_id'],$user->id,$friendshipSecond->unread_messages));
                }
            }

            if($message['receiver_type'] === 'group'){
                broadcast(new GroupMessageDeleted($message))->toOthers();
            }
        });

        return $this->showInfo($message);
    }
    
}
