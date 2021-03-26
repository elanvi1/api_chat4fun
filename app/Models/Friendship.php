<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Friendship extends Model
{
    use HasFactory,SoftDeletes;
    
    const BLOCKED_STATUS = 'blocked';
    const DELETED_STATUS = 'deleted';
    const ACCEPTED_STATUS = 'accepted';
    const REMOVED_STATUS = 'removed';
    const PENDING_STATUS = 'pending';
    const USER_ACTIVE = 'active';
    const USER_INACTIVE = 'inactive';

    // Letting laravel know what the dates are so they can be processed as such
    protected $dates=['deleted_at','blocked_at'];

    // Letting laravel know what columns can be mass assigned
    protected $fillable = [
        'main_id', 'friend_id','status','alias','blocked_at','presence_friend','unread_messages','created_at'
    ];

    // Letting laravel know what columns not to retrieve from the db
    protected $hidden = ['deleted_at'];

    // Letting laravel know the format in which certain columns should be retrieved as
    protected $casts = [
        'blocked_at' => 'datetime',
    ];

    // Helper function used to determine if messages from a contact can be viewed
    public function viewable(){
        return $this->attributes['status'] !== self::REMOVED_STATUS && $this->attributes['status'] !== self::PENDING_STATUS;
    }
}
