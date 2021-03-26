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

class UserRemovedFromGroup implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $info;
    public $notification;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($info,$notification)
    {
        $this->info = $info;
        $this->notification = $notification;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('notifications.group.'.$this->info['group_id']);
    }

    public function broadcastWith()
    {
        return ['info' => $this->info,'notification'=>$this->notification];
    }

    public function broadcastAs()
    {
        return 'UserRemovedFromGroup';
    }
}
