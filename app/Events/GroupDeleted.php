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

class GroupDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $group_id,$user_id,$notification;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($group_id,$user_id,$notification)
    {
        $this->group_id = $group_id;
        $this->user_id = $user_id;
        $this->notification = $notification;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('notifications.to.user.'.$this->user_id);
    }

    public function broadcastWith()
    {
        return ['notification'=>$this->notification,'group_id'=>$this->group_id];
    }

    public function broadcastAs()
    {
        return 'GroupDeleted';
    }
}
