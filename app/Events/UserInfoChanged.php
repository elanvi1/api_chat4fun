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

class UserInfoChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $info,$notification;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($info,$notification=null)
    {
        $this->info = $info;
        $this->notification=$notification;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('notifications.from.user.'.$this->info['user_id']);
    }

    public function broadcastAs()
    {
        return 'UserInfoChanged';
    }
}
