<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class UpdateUnreadMessagesContact implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $main_user_id,$friend_id,$unread_messages;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($main_user_id,$friend_id,$unread_messages)
    {
        $this->main_user_id = $main_user_id;
        $this->friend_id = $friend_id;
        $this->unread_messages = $unread_messages;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('notifications.to.user.'.$this->main_user_id);
    }

    public function broadcastAs()
    {
        return 'UpdateUnreadMessagesContact';
    }
}
