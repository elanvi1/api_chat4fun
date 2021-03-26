<?php

namespace App\Http\Controllers\User;

use App\Models\User;
use App\Models\Message;
use App\Models\GroupUser;
use App\Models\Friendship;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiController;

class UserMessagesController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    // Method used at 'conversation_with_user/{user}' endpoint for the "GET" request. Retrieves the messages of a contact chat.
    public function index(Request $request,$id)
    {
        // Messages are retrieved 30 per page, so a page query paramater need to be specified.
        // EX: if there are 100 messages and the specified page is 4 then messages 91 to 100 will be returned. They have a order based on creation from newest to oldest so in this example the 10 oldest messages are retrieved
        $rules = [
            'page' => 'required|numeric'
        ];

        $authUser = $request->user();
        $user = User::withTrashed()->whereId($id)->firstOrFail();

        $this->validate($request, $rules);

        $friendship = Friendship::where([
            ['main_id',$authUser->id],
            ['friend_id',$user->id]
        ])->firstOrFail();

        // Checking to see if the messages in the chat with this contact can be retrieved
        if(!$friendship->viewable()){
            return $this->errorResponse('Specified user not in your contact list',404);
        }
        
        // Getting all the messages that were sent by the main user to the contact
        $messagesSent = $authUser->messagesSent()->where([
            ['messageable_type',User::class],
            ['messageable_id',$user->id]
        ])->get();

        // Getting all the messages that were received by the main user from the contact
        if($friendship->status !== Friendship::BLOCKED_STATUS){
            $messagesReceived = $authUser->messagesReceived()->where([
                ['sender_id',$user->id]
            ])->get();  
        }else{
            // If the status of friendship 1 is blocked then only the received messages up until that point will be retrieved
            $messagesReceived = $authUser->messagesReceived()->where([
                ['sender_id',$user->id],
                ['created_at','<',$friendship->blocked_at]
            ])->get();
        }
      
        // Combining the received and sent messages into one group and ordering them from newest to oldest
        $messages = $messagesSent->concat($messagesReceived)->values()->sortByDesc('created_at')->values();

        $messages = $messages->map(function($message){
            // Changing the attribute names of a message using a transformer. Check app->transformers->MessageTransformer.php for more info
            $message = fractal($message, new $message->transformer)->toArray()['data'];

            return $message;
        });

        // Giving additional information about the messages for convenience
        $pageNr = (int)$request->page;
        $totalMessages = count($messages);
        $nextPage = ($totalMessages - (30 * $pageNr)) > 0 ? ($pageNr + 1) : false;

        $info['total_messages'] = $totalMessages;
        $info['current_page'] = $pageNr;
        $info['next_page'] = $nextPage;

        $messages = $messages->forPage($pageNr,30)->values();

        $messages = $messages->keyBy(function($item){
            return 'info_message_'.$item['id'];
        });

        $info['nr_messages_returned'] = count($messages) + ($pageNr - 1) * 30;
        
        return $this->showInfo($messages,200,$info);
    }
}
