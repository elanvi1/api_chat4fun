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

class UserPermissionChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $info,$user_id,$notification;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($info,$user_id,$notification)
    {
        $this->info = $info;
        $this->user_id = $user_id;
        $this->notification= $notification;
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
        return ['info'=> $this->info,'notification'=>$this->notification];
    }

    public function broadcastAs()
    {
        return 'UserPermissionChanged';
    }
}
