<?php

namespace App\Transformers;

use App\Models\User;
use App\Models\Message;
use League\Fractal\TransformerAbstract;

class MessageTransformer extends TransformerAbstract
{
    /**
     * List of resources to automatically include
     *
     * @var array
     */
    protected $defaultIncludes = [
        //
    ];
    
    /**
     * List of resources possible to include
     *
     * @var array
     */
    protected $availableIncludes = [
        //
    ];
    
    /**
     * A Fractal transformer.
     *
     * @return array
     */

    // Used to transform the column names from the db in order to make them easier to understand and also to limit information exposure which may cause a security breach.
    public function transform(Message $message)
    {
        return [
            "id"=>(int)$message->id,
            "sender_id"=>(int)$message->sender_id,
            "receiver_id"=>(int)$message->messageable_id,
            "receiver_type"=>$message->messageable_type === User::class ? 'user':'group',
            "message"=>$message->message,
            "status"=>$message->status,
            "created_at"=>$message->created_at->toDateTimeString()
        ];
    }

    // Used in TransformInput middleware
    public static function originalAttribute($index){
        $attributes = [
            "receiver_id" => "messageable_id",
            "receiver_type" => "messageable_type"
        ];

        return isset($attributes[$index]) ? $attributes[$index] : $index;
    }

    // Used in TransformInput middleware
    public static function transformedAttribute($index){
        $attributes = [
            "messageable_id" => "receiver_id",
            "messageable_type" => "receiver_type" 
        ];

        return isset($attributes[$index]) ? $attributes[$index] : $index;
    }
}
