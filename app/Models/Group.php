<?php

namespace App\Models;

use App\Models\User;
use App\Models\Message;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Group extends Model
{
    use HasFactory,SoftDeletes;

    // Letting laravel know what the dates are so they can be processed as such
    protected $dates=['deleted_at'];

    // Letting laravel know what columns can be mass assigned
    protected $fillable = [
        'name', 'image','description','created_at'
    ];

    // Letting laravel know what columns not to retrieve from the db
    protected $hidden = ['pivot','deleted_at'];

    // Mutator used to change the value of a column when being retrieved from the DB
    public function getImageAttribute($image){
        return isset($image) ? env('APP_URL')."/img/".$image : null;
    }

    // Describes the many to many relationship between groups and users. Has group_user itermediate table
    public function users(){
        return $this->belongsToMany(User::class)->whereNull('group_user.deleted_at')->withPivot('permission_id','unread_messages','created_at');
    }

    // Describes the one to many polymorphic relationship between a group(receiver) and messages
    public function messages(){
        return $this->morphMany(Message::class,'messageable')->whereNull('messages.deleted_at');
    }

    // Describes the many to many relationship between groups and notifications with group_notification intermediate table
    public function notifications(){
        return $this->belongsToMany(Notification::class)->withTimestamps();
    }
}
