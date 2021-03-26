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

class UserMessageDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($message)
    {
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        $min = min($this->message['sender_id'],$this->message['receiver_id']);
        $max = max($this->message['sender_id'],$this->message['receiver_id']);

        return new PrivateChannel('chat.between.user.'.$min.'.and.'.$max);
    }

    public function broadcastWith()
    {
        return ['message_id' => $this->message['id'], 'friend_id'=>$this->message['sender_id']];
    }

    public function broadcastAs()
    {
        return 'UserMessageDeleted';
    }
}
