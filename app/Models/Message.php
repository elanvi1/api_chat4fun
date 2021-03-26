<?php

namespace App\Models;

use App\Models\User;
use App\Transformers\MessageTransformer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Message extends Model
{
    use HasFactory,SoftDeletes;

    const SENDING_STATUS = 'sending';
    const SENT_STATUS = 'sent';
    const READ_STATUS = 'read';
    const FAILED_STATUS = 'failed';

    // Setting a default transformer for this model
    public $transformer = MessageTransformer::class;

    // Letting laravel know what columns can be mass assigned
    protected $fillable = [
        'sender_id', 'messageable_id','messageable_type','status','message'
    ];
  
    // Letting laravel know what the dates are so they can be processed as such
    protected $dates=['deleted_at'];

    // Letting laravel know what columns not to retrieve from the db
    protected $hidden = ['pivot','messageable_type','deleted_at','updated_at'];

    // Describes the one to many relationship between a user(sender) and messages
    public function user(){
        return $this->belongsTo(User::class,'sender_id');
    }

    // Describes the one to many polymorphic relationship between a group or user(receiver) and messages.
    public function messageable(){
        return $this->morphTo();
    }
}
