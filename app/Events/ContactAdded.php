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

class ContactAdded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $info;
    public $id;
    public $notification;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($info,$id,$notification)
    {
        $this->info = $info;
        $this->id = $id;
        $this->notification = $notification;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        \Log::debug(['info'=>$this->info,'notification'=>$this->notification]);
        return new PrivateChannel('notifications.to.user.'.$this->id);
    }

    public function broadcastWith()
    {
        
        return ['info'=>$this->info,'notification'=>$this->notification];
    }

    public function broadcastAs()
    {
        return 'ContactAdded';
    }
}
