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

class UserGroupInfoChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $info;
    public $notification,$group_id;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($info,$group_id,$notification = null)
    {
        $this->info = $info;
        $this->group_id = $group_id;
        $this->notification = $notification;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('notifications.group.'.$this->group_id);
    }

    public function broadcastWith()
    {
        return ['info'=>$this->info,'group_id'=>$this->group_id,'notification'=>$this->notification];
    }

    public function broadcastAs()
    {
        return 'UserGroupInfoChanged';
    }
}
