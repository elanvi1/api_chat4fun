<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Notification extends Model
{
    use HasFactory, SoftDeletes;

    const READ_STATUS = 'read';
    const UNREAD_STATUS = 'unread';
    const REMOVED_STATUS = 'removed';

    // Letting laravel know what columns can be mass assigned
    protected $fillable = [
        'message','title'
    ];

    // Letting laravel know what the dates are so they can be processed as such
    protected $dates=['deleted_at'];

    // Letting laravel know what columns not to retrieve from the db
    protected $hidden = ['pivot','deleted_at','updated_at'];

    // Describes the many to many relationship between users and notifications with notification_user intermediate table
    public function users(){
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    // Describes the many to many relationship between groups and notifications with group_notification intermediate table
    public function groups(){
        return $this->belongsToMany(Group::class)->withTimestamps();
    }
}
