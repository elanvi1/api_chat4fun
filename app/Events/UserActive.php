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

class UserActive implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $auth_user_id,$user_id;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($auth_user_id,$user_id)
    {
        $this->auth_user_id = $auth_user_id;
        $this->user_id = $user_id;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        $min = min($this->auth_user_id,$this->user_id);
        $max = max($this->auth_user_id,$this->user_id);

        return new PrivateChannel('chat.between.user.'.$min.'.and.'.$max);
    }

    public function broadcastWith()
    {
        return ['friend_id'=> $this->auth_user_id];
    }

    public function broadcastAs()
    {
        return 'UserActive';
    }
}
