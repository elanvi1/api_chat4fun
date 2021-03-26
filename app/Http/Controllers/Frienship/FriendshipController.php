<?php

namespace App\Http\Controllers\Frienship;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Message;
use App\Events\UserActive;
use App\Models\Friendship;
use App\Events\ContactAdded;
use App\Events\UserInactive;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Events\FriendshipStatusUpdated;
use App\Http\Controllers\ApiController;
use App\Events\FriendshipAcceptedOrRejected;
use App\Events\MarkMessagesAsRead;

class FriendshipController extends ApiController
{
    // Method used at "/friendship" endpoint for the "GET" request. It retrieves information about all contacts, the last message from the chat with each contact and info about the friendship with each contact
    public function index(Request $request){
        $user = $request->user();
        $friends = $user->friends()->withTrashed()->get();

        $info = collect();

        // user_id, username, about, image, is_online are being retrieved from the users table
        // alias, status, is_active, unread_messages, other_status, relation_id and added_at are being retrieved from the friendships table
        // last_message is being retrieved from the messages table

        // is_online refers to the presence column of contact in the users table
        // is_active refers to the presence_friend column of a contact in the friendships table
        // other_status refers to the status of the friendship of the contact with the main user
        
        foreach ($friends as $friend) {
            $friendship = $friend->pivot;

            if(($friendship->status !== Friendship::REMOVED_STATUS) && ($friendship->status !== Friendship::PENDING_STATUS)){
                $lastMessage = $this->getLastMessage($user,$friend);

                $secondFriendship = Friendship::where([
                    ['main_id',$friend->id],
                    ['friend_id',$user->id]
                ])->firstOrFail();

                $canSeeVisibility = ($friendship->status === Friendship::ACCEPTED_STATUS) && ($secondFriendship->status === Friendship::ACCEPTED_STATUS) ? true : false;

                $individualInfo = collect([
                    'user_id'=>$friend->id,
                    'username'=>$friend->username,
                    'about'=>$friend->about,
                    'image'=>$friend->image,
                    'alias'=>$friendship->alias,
                    'status'=>$friendship->status,
                    'is_online'=>$canSeeVisibility ? ($friend->presence === USER::IS_ONLINE ? true : false) : false,
                    'is_active'=>$canSeeVisibility ? ($friendship->presence_friend === Friendship::USER_ACTIVE ? true : false) :false,
                    'unread_messages'=>$friendship->unread_messages,
                    'other_status'=>$secondFriendship->status,
                    'last_message'=> $lastMessage,
                    'relation_id'=> $friendship->id,
                    'added_at'=>$friendship->created_at->toDateTimeString()
                ]);

                $info->put('info_user_'.$individualInfo['user_id'],$individualInfo);
            }
        }

        return $this->showInfo($info);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    // Method used at "/friendship" endpoint for the "POST" request. Depending on the existence or status of the friendships there are multiple scenarios
    public function store(Request $request)
    {
        $rules = [
            'friend_id' => 'required|numeric'
        ];

        $this->validate($request, $rules);

        $authUser = $request->user();

        $data = $request->only(['friend_id']);

        $data['status'] = Friendship::PENDING_STATUS;
        $data['main_id'] = $authUser->id;
        $data['alias'] = $authUser->makeVisible(['remember_token'])->remember_token;

        $friend = User::whereId($data['friend_id'])->firstOrFail();

        $checkFriendship = Friendship::where([
            ['main_id',$authUser->id],
            ['friend_id',$request->friend_id]
        ])->first();

        // First I check if there is a friendship between the 2 users(if there is one there are 2 guaranteed, check routes->api.php for more info)
        // For simplicity I will call the friendship of the main user with the contact friendship 1 and the friendship of the contact with the main user friendship 2
        if(isset($checkFriendship)){
            $mainFriendship = $checkFriendship;

            // If a friendship exists I check the status of both friendships to determine the outcome

            if(($mainFriendship->status === Friendship::ACCEPTED_STATUS) || ($mainFriendship->status === Friendship::BLOCKED_STATUS) || ($mainFriendship->status === Friendship::DELETED_STATUS)){
                // If the status friendshp 1 is "accepted","blocked" or "deleted" it means that the user is already a contact and a error response is returned
                return $this->errorResponse('Specified user already in your contact list',409);
            }else if($mainFriendship->status === Friendship::PENDING_STATUS){
                // If the status of friendshp 1 is "pending" it means that a frienship request has been already been sent and a error response is returned
                return $this->errorResponse('A friendship request has already been sent to or from '.$friend->username,409);
            }else{
                $secondFriendship = Friendship::where([
                    ['main_id',$friend->id],
                    ['friend_id',$authUser->id]
                ])->firstOrFail();

                // If the status of friendship 1 is "removed" then I check the status of friendship 2 in order to determine what the response will be
                if($secondFriendship->status === Friendship::ACCEPTED_STATUS){
                    // If the status of friendship 2 is "accepted" it means that the user can be automatically added without sending a friendship request. The data being returned is at the the end of this method(store)
                    DB::transaction(function() use($mainFriendship,$secondFriendship,$friend,$authUser){
                        $mainFriendship->status = Friendship::ACCEPTED_STATUS;
                        $mainFriendship->created_at = Carbon::now()->toDateTimeString();
                        $mainFriendship->save();

                        $alias = isset($secondFriendship->alias) ? '(alias '.$secondFriendship->alias.')' : '';

                        $notification = $friend->notifications()->create(['message'=>'User '.$authUser->username.$alias.' has readded you to their contact list. They are now able to see past and future messages from you','title'=>'Someone readded you']);

                        broadcast(new FriendshipStatusUpdated($notification,$friend->id,$authUser->id));
                    });
                }else if($secondFriendship->status === Friendship::BLOCKED_STATUS){
                    // If the status of the friendship 2 is "blocked" it means that the main user can't send a friendship request so an error response is returned
                    return $this->errorResponse('You have been blocked by '.$friend->username.', therefor you can\'t send him/her a friend request', 403);
                }else if($secondFriendship->status === Friendship::REMOVED_STATUS){
                    // If the status of friendship 2 is "removed" then a friendship request is sent, which will appear in the form of a notification for the other user
                    DB::transaction(function() use($mainFriendship,$secondFriendship,$friend,$authUser){
                        $mainFriendship->status = Friendship::PENDING_STATUS;
                        $mainFriendship->alias = $authUser->remember_token;
                        $secondFriendship->status = Friendship::PENDING_STATUS;

                        $mainFriendship->save();
                        $secondFriendship->save();

                        $secondNotification = $friend->notifications()->create(['message'=>'You have received a friend request from '.$authUser->username.'friendship_id'.$secondFriendship->id,'title'=>'Friendship Request Received']);

                        broadcast(new FriendshipStatusUpdated($secondNotification,$friend->id,$authUser->id));
                    });

                    return $this->showMessage('You have sent a friendship request to '.$friend->username.'. Keep an eye on the notifications tab for a reply',201);
                }
            }
        }else{
            // If there are no friendships then a friendship request is sent in the form a notification to the other user
            DB::transaction(function() use ($data, $authUser,$friend, &$mainFriendship, &$secondFriendship){
                $mainFriendship = Friendship::create($data);
                $secondFriendship = Friendship::create(['main_id'=>$data['friend_id'],'friend_id'=>$data['main_id'],'status'=>Friendship::PENDING_STATUS]);

                $secondNotification = $friend->notifications()->create(['message'=>'You have received a friend request from '.$authUser->username.'friendship_id'.$secondFriendship->id,'title'=>'Friendship Request Received']);

                broadcast(new FriendshipStatusUpdated($secondNotification,$friend->id,$authUser->id));
            });

            return $this->showMessage('You have sent a friendship request to '.$friend->username.'. Keep an eye on the notifications tab for a reply',201);
        }

        $lastMessage = $this->getLastMessage($authUser,$friend);
        
        // This is data which will be sent in the scenario where friendship 1 status is "removed" and friendship 2 status is "accepted". It is the same as the info being returned by the index method but for a single contact
        $infoMain = collect([
            'user_id'=>$friend->id,
            'username'=>$friend->username,
            'about'=>$friend->about,
            'image'=>$friend->image,
            'alias'=>$mainFriendship->alias,
            'status'=>Friendship::ACCEPTED_STATUS,
            'other_status'=>Friendship::ACCEPTED_STATUS,
            'is_online'=>$friend->presence === USER::IS_ONLINE ? true : false,
            'is_active'=>$mainFriendship->presence_friend === Friendship::USER_ACTIVE ? true : false,
            'unread_messages'=>$mainFriendship->unread_messages,
            'last_message'=> $lastMessage,
            'relation_id'=> $mainFriendship->id,
            'added_at'=>$mainFriendship->created_at->toDateTimeString()
        ]);

        $alias = isset($mainFriendship->alias) ? '(alias '.$mainFriendship->alias.')' : '';

        return $this->showInfo($infoMain,201,'User '.$friend->username.$alias.' has been added to your contact list');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Friendship  $friendship
     * @return \Illuminate\Http\Response
     */

    // Method used at "/friendship" endpoint for the "PATCH/PUT" request. It is used to change the status of the friendship or the alias of the contact
    public function update(Request $request, Friendship $friendship)
    {
        $rules = [
            'status' => 'in:'.Friendship::ACCEPTED_STATUS.','.
            Friendship::BLOCKED_STATUS.','.
            Friendship::REMOVED_STATUS,
            'alias' => 'sometimes|required|max:189',
        ];

        $this->validate($request, $rules);

        $authUser = $request->user();

        // Making checks to be sure that there are no unauthorized changes.
        if((int)$authUser->id !== (int)$friendship->main_id){
            return $this->errorResponse('Can\'t update friendship status of another user',403);
        }

        if($friendship->status === Friendship::PENDING_STATUS){
            return $this->errorResponse('You can\'t affect friendships with pending status from this endpoint',418);
        }

        $info = collect([]);
        $nrBlockedMsgs = 0;
        $queryBuilderMsgs = null;
        $blockedMessagesToSend = null;
        $secondFriendship = null;

        if($request->has('status')){
            $friend = User::whereId($friendship->friend_id)->first();

            if(($friendship->status === Friendship::BLOCKED_STATUS ) && ($request->status !== Friendship::BLOCKED_STATUS)){
                $blockDate = $friendship->blocked_at;
                $friendship->blocked_at = null;
            }

            if(($request->status === Friendship::BLOCKED_STATUS) && ($friendship->status !== Friendship::BLOCKED_STATUS)){
                $friendship->blocked_at = Carbon::now()->toDateTimeString();
            }

            if((!isset($friend)) && ($request->status === Friendship::ACCEPTED_STATUS)){
                return $this->errorResponse('Specified User no longer has an account, status can only be removed or blocked',409);
            }else{
                $oldStatus = $friendship->status;
                $friendship->status = $request->status;
                $info['status'] = $request->status;

                if(isset($friend) && ($oldStatus !== $friendship->status)){
                    $secondFriendship = Friendship::where([
                        ['main_id',$friend->id],
                        ['friend_id',$authUser->id]
                    ])->firstOrFail();

                    if($oldStatus === Friendship::BLOCKED_STATUS && $friendship->status === Friendship::ACCEPTED_STATUS){
                        $queryBuilderMsgs = Message::where([
                            ['sender_id',$friend->id],
                            ['messageable_id',$authUser->id],
                            ['messageable_type',User::class],
                            ['created_at','>',$blockDate]
                        ]);

                        $blockedMessages = $queryBuilderMsgs->get();
                        
                        $nrBlockedMsgs = $blockedMessages->count();

                        if($nrBlockedMsgs > 0){
                            if($secondFriendship->presence_friend === Friendship::USER_ACTIVE){
                                $nrBlockedMsgs = 0;
                            }else{
                                $friendship->unread_messages += $nrBlockedMsgs;
                            }

                            $blockedMessagesToSend = $blockedMessages->map(function($message){
                                // Changing the attribute names of a message using a transformer. Check app->transformers->MessageTransformer.php for more info
                                $message = fractal($message, new $message->transformer)->toArray()['data'];
                    
                                return $message;
                            });

                            $blockedMessagesToSend = $blockedMessagesToSend->keyBy(function($item){
                                return 'info_message_'.$item['id'];
                            });
                        }
                    }

                    if($request->status === Friendship::ACCEPTED_STATUS && $secondFriendship->status === Friendship::ACCEPTED_STATUS){
                        if($friend->presence === User::IS_ONLINE){
                            $info['is_online'] = true;
                        }
                        
                        if($friendship->presence_friend === Friendship::USER_ACTIVE){
                            $info['is_active'] = true;
                        }
                    }

                    // A notification is being sent to the contact to inform him about the status change
                    if($secondFriendship->status === Friendship::ACCEPTED_STATUS){
                        $notificationMessage = '';
                        $title ='';
                        $alias = isset($secondFriendship->alias) ? '(alias '.$secondFriendship->alias.')' : '';
        
                        if($friendship->status === Friendship::ACCEPTED_STATUS){
                            $notificationMessage = 'User '.$authUser->username.$alias.' has unblocked you. Contact is now able to see your new messages as well as the messages that were sent after the block';
                            $title = 'Someone unblocked you';
                        }else if($friendship->status === Friendship::BLOCKED_STATUS){
                            $notificationMessage = 'User '.$authUser->username.$alias.' has blocked you. Contact won\'t be able to see the messages that you send unless he or she unblocks you';
                            $title ='Someone blocked you';
                        }else if($friendship->status === Friendship::REMOVED_STATUS){
                            $notificationMessage = 'User '.$authUser->username.$alias.' has removed you from their contact list. Contact won\'t be able to see the messages that you send unless he or she adds you again';
                            $title ='Someone removed you';
                        }
                        
                        $notification = $friend->notifications()->create(['message'=>$notificationMessage,'title'=>$title]);

                        $activeAuthUser= ($secondFriendship->presence_friend === Friendship::USER_ACTIVE)  && ($friendship->status === Friendship::ACCEPTED_STATUS) ? true : false;

                        broadcast(new FriendshipStatusUpdated($notification,$friend->id,$authUser->id,$activeAuthUser));
                    }
                }
            }
        }

        if($request->has('alias')){
            $friendship->alias = $request->alias;
            $info['alias'] = $request->alias;
        }

        if($friendship->isClean()){
            return $this->errorResponse('The values that you mentioned are the same as the previous ones',422);
        }

        $additionalInfo = null;

        if(isset($blockedMessagesToSend)){
            $additionalInfo = ['messages'=>$blockedMessagesToSend,'unread_messages'=>$nrBlockedMsgs];
        }

        DB::transaction(function() use ($friendship,$nrBlockedMsgs,$blockedMessagesToSend,$queryBuilderMsgs,$secondFriendship,$request){
            $friendship->save();

            if($nrBlockedMsgs === 0 && isset($blockedMessagesToSend)){
                $queryBuilderMsgs->update(['status'=>Message::READ_STATUS]);

                broadcast(new MarkMessagesAsRead($friendship->main_id,$friendship->friend_id))->toOthers();
            }

            if($request->has('status')){
                if($request->status === Friendship::REMOVED_STATUS){
                    $secondFriendship->presence_friend = Friendship::USER_INACTIVE;
                    $secondFriendship->save();
                }
            }
        });

        return $this->showInfo($info,200,$additionalInfo);;
    }

    // Method used at 'friendship/{friendship}/handleAcceptOrReject' endpoint for the "POST" request. The main user can reject or accept a friendship request.
    public function handleAcceptOrReject(Request $request, Friendship $friendship){
        $rules = [
            'status' => 'required|in:'.Friendship::ACCEPTED_STATUS.','.
            'rejected'
        ];

        $this->validate($request, $rules);

        $authUser = $request->user()->makeVisible(['remember_token']);

        // Making checks to be sure that there are no unauthorized changes.
        if((int)$friendship->main_id !== (int)$authUser->id){
            return $this->errorResponse('You can\'t change the friendship status of other users',403);
        }

        if($friendship->status !== Friendship::PENDING_STATUS){
            return $this->errorResponse('You can only affect friendships with a pending status from this endpoint',418);
        }

        $secondFriendship = Friendship::where([
            ['main_id',$friendship->friend_id],
            ['friend_id',$friendship->main_id]
        ])->firstOrFail();

        if($secondFriendship->status !== Friendship::PENDING_STATUS){
            return $this->errorResponse('Unexpected error one friendship has pending status and the other doesn\'t',424);
        }

        $friend = User::withTrashed()->whereId($friendship->friend_id)->firstOrFail()->makeVisible(['remember_token']);

        if($secondFriendship->alias !== $friend->remember_token){
            return $this->errorResponse('You can\'t accept/reject a friendship that you sent, only the person that you sent it to can ',403);
        }

        if($friend->trashed()){
            return $this->errorResponse('The user that sent you the friendship request has since deactivated their account',403);
        }

        // If the friendship request is deleted then both friendships are deleted and a notification is sent to the other user
        if($request->status === 'rejected'){
            DB::transaction(function() use ($friendship,$secondFriendship,$authUser,$friend){
                $authUser->notifications()->where([
                    ['title','Friendship Request Received'],
                    ['message','like','%friendship_id'.$friendship->id.'%']
                ])->firstOrFail()->forceDelete();

                $notificationToFriend = $friend->notifications()->create(['message'=>$authUser->username.' has rejected your friendship request','title'=>'Friendship Request Rejected']);

                broadcast(new FriendshipAcceptedOrRejected($notificationToFriend, $friend->id));

                $friendship->forceDelete();
                $secondFriendship->forceDelete();
            });

            return $this->showInfo(['user_id'=>$friend->id],200,'Friendship request from '.$friend->username.' has been rejected');
        }

        $lastMessage = $this->getLastMessage($authUser,$friend);

        // If the friendship request is accepted then a notification and info about the main user will be sent to the other user.
        DB::transaction(function() use ($friendship,$secondFriendship,$authUser,$friend,$lastMessage){
            $friendship->status = Friendship::ACCEPTED_STATUS;
            $secondFriendship->status = Friendship::ACCEPTED_STATUS;
            $secondFriendship->alias = null;
            $friendship->created_at = Carbon::now()->toDateTimeString();
            $secondFriendship->created_at = Carbon::now()->toDateTimeString();

            $friendship->save();
            $secondFriendship->save();

            $authUser->notifications()->where([
                ['title','Friendship Request Received'],
                ['message','like','%friendship_id'.$friendship->id.'%']
            ])->firstOrFail()->forceDelete();

            $notificationToFriend = $friend->notifications()->create(['message'=>'User '.$authUser->username.' has accepted your friend request, you can now interact with them from the contacts tab','title'=>'Friendship Request Accepted']);

            $infoSecond = collect([
                    'user_id'=>$authUser->id,
                    'username'=>$authUser->username,
                    'about'=>$authUser->about,
                    'image'=>$authUser->image,
                    'alias'=>$secondFriendship->alias,
                    'status'=>$secondFriendship->status,
                    'is_online'=>$authUser->presence === USER::IS_ONLINE ? true : false,
                    'is_active'=>$secondFriendship->presence_friend === Friendship::USER_ACTIVE ? true : false,
                    'unread_messages'=>$secondFriendship->unread_messages,
                    'other_status'=>Friendship::ACCEPTED_STATUS,
                    'last_message'=> $lastMessage,
                    'relation_id'=> $secondFriendship->id,
                    'added_at'=>$secondFriendship->created_at->toDateTimeString()
            ]);

            broadcast(new FriendshipAcceptedOrRejected($notificationToFriend,$friend->id,$infoSecond));
        });

        // If the friendship request is accepted info about the contact will be returned to the main user
        $infoMain = collect([
            'user_id'=>$friend->id,
            'username'=>$friend->username,
            'about'=>$friend->about,
            'image'=>$friend->image,
            'alias'=>$friendship->alias,
            'status'=>$friendship->status,
            'other_status'=>$secondFriendship->status,
            'is_online'=>$friend->presence === USER::IS_ONLINE ? true : false,
            'is_active'=>$friendship->presence_friend === Friendship::USER_ACTIVE ? true : false,
            'unread_messages'=>$friendship->unread_messages,
            'last_message'=> $lastMessage,
            'relation_id'=> $friendship->id,
            'added_at'=>$friendship->created_at->toDateTimeString()
        ]);

        $alias = isset($infoMain->alias) ? '(alias '.$infoMain->alias.')' : '';

        return $this->showInfo($infoMain,200,'You have accepted the friend request from '.$friend->username.$alias.'. You can interact with them from the contacts tab');
    }

    // Helper method used to get the last message in a chat
    public function getLastMessage($user,$friend){
        $lastMessage = Message::where('messageable_type',User::class)->where(function($query) use ($user,$friend){
            $query->where([
                ['sender_id',$user->id],
                ['messageable_id',$friend->id]
            ])->orWhere([
                ['sender_id',$friend->id],
                ['messageable_id',$user->id]
            ]);
        })->orderBy('id','desc')->first();

        if(isset($lastMessage)){
            $lastMessage = fractal($lastMessage, new $lastMessage->transformer)->toArray()['data'];
        }

        return $lastMessage;
    }

    // Method used at '/active' endpoint for the "POST" request. Makes a user active on a certain contact chat by changing the value of "presence_friend" attribute in the friendships table
    public function userActive(Request $request){
        $rules = [
            'user_id' => 'required|numeric',
        ];

        $this->validate($request, $rules);

        $authUser = $request->user();

        // Making checks to be sure that there are no unauthorized changes.
        $user = User::whereId($request->user_id)->firstOrFail();
        $friendshipMain = Friendship::where([
            ['main_id',$authUser->id],
            ['friend_id',$user->id]
        ])->firstOrFail();

        if ($friendshipMain->status === Friendship::REMOVED_STATUS || $friendshipMain->status === Friendship::PENDING_STATUS) {
            return $this->errorResponse('Can\'t send status to users that are not in your contact list',403);
        }

        $friendshipSecond = Friendship::where([
            ['main_id',$user->id],
            ['friend_id',$authUser->id]
        ])->firstOrFail();

        // The change to the DB takes place regardless of friendship status, this is done in order to see the contact's status right after the main user unblock them or readd them(assuming they have the main contact as accepted) 
        $friendshipSecond->presence_friend = Friendship::USER_ACTIVE;
        $friendshipSecond->save();

        // The change can only be made if both friendships have an "accepted" status
        if($friendshipSecond->status === Friendship::ACCEPTED_STATUS && $friendshipMain->status === Friendship::ACCEPTED_STATUS){
            broadcast(new UserActive($authUser->id,$user->id))->toOthers();
        }
    }

    // Method used at '/inactive' endpoint for the "POST" request. Makes a user inactive on a certain contact chat by changing the value of "presence_friend" attribute in the friendships table
    public function userInactive(Request $request){
        $rules = [
            'user_id' => 'required|numeric',
        ];

        $this->validate($request, $rules);

        $authUser = $request->user();

        // Making checks to be sure that there are no unauthorized changes.
        $user = User::whereId($request->user_id)->firstOrFail();
        $friendshipMain = Friendship::where([
            ['main_id',$authUser->id],
            ['friend_id',$user->id]
        ])->firstOrFail();

        if ($friendshipMain->status === Friendship::REMOVED_STATUS || $friendshipMain->status === Friendship::PENDING_STATUS) {
            return $this->errorResponse('Can\'t send status to users that are not in your contact list',403);
        }

        $friendshipSecond = Friendship::where([
            ['main_id',$user->id],
            ['friend_id',$authUser->id]
        ])->firstOrFail();

        // The change to the DB takes place regardless of friendship status, this is done in order to see the contact's status right after the main user unblock them or readd them(assuming they have the main contact as accepted) 
        $friendshipSecond->presence_friend = Friendship::USER_INACTIVE;
        $friendshipSecond->save();

        // The broadcast is only made if both friendships have an "accepted" status
        if($friendshipSecond->status === Friendship::ACCEPTED_STATUS && $friendshipMain->status === Friendship::ACCEPTED_STATUS){
            broadcast(new UserInactive($authUser->id,$user->id))->toOthers();
        }
    }

    // Method used at 'friendship/{friendship}/resetUnreadMessages' endpoint for the "GET" request. Resets the number of unread messages for a chat by changing the "unread_messages" attribute in the friendships table.
    public function resetUnreadMessages(Request $request, Friendship $friendship){
        $authUser = $request->user();

        // Making checks to be sure that there are no unauthorized changes.
        if($friendship->main_id !== $authUser->id){
            return $this->errorResponse('You can\'t reset unread messages number for other users',403);
        }

        DB::transaction(function() use ($friendship){
            // The change can be made only if the status of friendship 1 is "accepted"
            if($friendship->status === Friendship::ACCEPTED_STATUS){
                $friendship->unread_messages = 0;
                $friendship->save();

                // The number of unread messages is reset only if the main user sees those messages. Because of that the status of the messages sent by the contact is changed to "read".
                Message::where([
                    ['sender_id',$friendship->friend_id],
                    ['messageable_id',$friendship->main_id],
                    ['messageable_type',User::class]
                ])->update(['status'=>Message::READ_STATUS]);

                broadcast(new MarkMessagesAsRead($friendship->main_id,$friendship->friend_id))->toOthers();
            }
        });
    }

    // Method used at '/userIdsPendingFriendships' endpoint for the "GET" request. Returns the ids of the users with which the main user has a friendship with "pending" status. This means that a friendship request was sent by one of the two but no response was received.(if a friendship has "pending" status then both of them have it)
    public function userIdsPendingFriendships(Request $request){
        $authUser = $request->user();

        $userIds = Friendship::where([
            ['main_id',$authUser->id],
            ['status',Friendship::PENDING_STATUS]
        ])->get()->pluck('friend_id');

        return $this->showInfo($userIds,200);
    }
}
