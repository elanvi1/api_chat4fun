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

class FriendshipAcceptedOrRejected implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification;
    public $id,$info;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($notification,$id,$info=null)
    {
        $this->notification= $notification;
        $this->id = $id;
        $this->info = $info;
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
        return ['notification'=>$this->notification,'info'=>$this->info];
    }

    public function broadcastAs()
    {
        return 'FriendshipAcceptedOrRejected';
    }
}
