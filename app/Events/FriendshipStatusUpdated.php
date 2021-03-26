<?php

namespace App\Events;

use App\Traits\ApiResponser;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class FriendshipStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification;
    public $id,$senderId,$is_active;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($notification,$id,$senderId,$is_active=false)
    {
        $this->notification= $notification;
        $this->id = $id;
        $this->senderId = $senderId;
        $this->is_active = $is_active;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('notifications.to.user.'.$this->id);
    }

    public function broadcastWith()
    {
        return ['notification'=>$this->notification,'friend_id'=>$this->senderId,'is_active'=>$this->is_active];
    }

    public function broadcastAs()
    {
        return 'FriendshipStatusUpdated';
    }
}
