<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    // Method used at "/notification" endpoint for the "GET" request. It is used to retrieve the notifications from the DB.
    public function index(Request $request)
    {
        $user = $request->user();
        $notifications = $user->notifications;

        $notifications = $notifications->keyBy(function($item){
            return 'info_notification_'.$item['id'];
        });

        $notifications = $notifications->map(function($item){
            if($item->pivot->status === Notification::REMOVED_STATUS){
                return null;
            }

            // The 'id','message' , 'title' and 'created_at' attributes are retrieved from the notifications table
            $newItem = collect($item->toArray())->only(['id','message','title']);

            // The "status" attribute is retrieved from the notification_user table
            $newItem->put('status',$item->pivot->status);
            $newItem->put('created_at', $item->created_at->toDateTimeString());
            
            return $newItem;
        });

        $notifications = $notifications->whereNotNull();

        return $this->showInfo($notifications);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Notification  $notification
     * @return \Illuminate\Http\Response
     */

    // Method used at "/notification" endpoint for the "PATCH/PUT" request. It is used to change the status of a notification.
    public function update(Request $request, Notification $notification)
    {
        $rules = [
            'status' => 'required|in:'.Notification::READ_STATUS.','.
            Notification::UNREAD_STATUS.','.Notification::REMOVED_STATUS
        ];

        $this->validate($request, $rules);

        $authUser= $request->user();

       $authUser->notifications()->wherePivot('notification_id',$notification->id)->firstOrFail();

        $authUser->notifications()->updateExistingPivot($notification->id,['status'=>$request->status]);

        return $this->showInfo(['status'=>$request->status]);
    }
}
