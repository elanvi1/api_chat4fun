<?php

namespace App\Http\Controllers\Group;

use App\Models\User;
use App\Models\Group;
use App\Models\Friendship;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiController;

class GroupMessagesController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    // Method used at '/group/{group}/messages' endpoint for the "GET" request. Retrieves the messages of a group chat.
    public function index(Request $request, Group $group)
    {
        // Messages are retrieved 30 per page, so a page query paramater need to be specified.
        // EX: if there are 100 messages and the specified page is 4 then messages 91 to 100 will be returned. They have a order based on creation from newest to oldest so in this example the 10 oldest messages are retrieved
        $rules = [
            'page' => 'required|numeric'
        ];

        $this->validate($request, $rules);

        $user = $request->user();

        // Making checks to be sure that there are no unauthorized changes.
        if(!$group->users->contains($user)){
            return $this->errorResponse('Authenticated user isn\'t in the requested group',403);
        }

        $notificationsTemp = $group->notifications;
        $messagesTemp = $group->messages;

        $messages = $messagesTemp->map(function($message) use($user){
            // Changing the attribute names of a message using a transformer. Check app->transformers->MessageTransformer.php for more info
            $message = fractal($message, new $message->transformer)->toArray()['data'];

            if($message['sender_id'] !== $user->id){
                $friendshipMsg = Friendship::where([
                    ['main_id',$user->id],
                    ['friend_id',$message['sender_id']]
                ])->first();

                // When adding the sender name I check if the main user is friends with the member and if that is true then I check if there is an alias for that member
                $message['sender_name'] = User::withTrashed()->where('id',$message['sender_id'])->firstOrFail()->username;

                if(isset($friendshipMsg)){
                    if(isset($friendshipMsg->alias)&& (($friendshipMsg->status === Friendship::ACCEPTED_STATUS)  || $friendshipMsg->status === Friendship::BLOCKED_STATUS)){
                        $message['sender_name'] = $friendshipMsg->alias;
                    }
                }
            }

            return $message;
        });

        // Group notifications are bundled together with the messages and count toward the 30 mark of messages returned.
        $notifications = $notificationsTemp->map(function($notification){
            $changedNotification = collect($notification->toArray())->only(['id','message','title']);

            $changedNotification->put('created_at', $notification->created_at->toDateTimeString());

            return $changedNotification;
        });

        $messages = $messages->keyBy(function($item){
            return 'info_message_'.$item['id'];
        });

        $notifications = $notifications->keyBy(function($item){
            return 'info_notification_'.$item['id'];
        });

        $messagesAndNotifications = $messages->merge($notifications);

        $messagesAndNotifications = $messagesAndNotifications->sortByDesc('created_at');

        $pageNr = (int)$request->page;
        $totalMessages = count($messagesAndNotifications);
        $nextPage = ($totalMessages - (30 * $pageNr)) > 0 ? ($pageNr + 1) : false;

        // Giving additional information about the messages for convenience
        $info['total_messages'] = $totalMessages;
        $info['current_page'] = $pageNr;
        $info['next_page'] = $nextPage;

        $messagesAndNotifications = $messagesAndNotifications->forPage($pageNr,30);

        $info['nr_messages_returned'] = count($messagesAndNotifications) + ($pageNr - 1) * 30;

        return $this->showInfo($messagesAndNotifications,200,$info);
    }
}
