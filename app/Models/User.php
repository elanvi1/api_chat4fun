<?php

namespace App\Models;

use App\Models\Role;
use App\Models\Message;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Storage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes,HasApiTokens;

    const VERIFIED_USER = '1';
    const UNVERIFIED_USER = '0';
    const IS_ONLINE = 'online';
    const IS_OFFLINE = 'offline';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    // Letting laravel know what columns can be mass assigned
    protected $fillable = [
        'name', 'username','email', 'password','about','image','verified','verification_token','presence'
    ];

    // Letting laravel know what the dates are so they can be processed as such
    protected $dates = ['deleted_at'];
    
    protected $table = 'users';

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */

    // Letting laravel know what columns not to retrieve from the db
    protected $hidden = [
        'password', 'remember_token','verification_token','deleted_at'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */

    // Letting laravel know the format in which certain columns should be retrieved as
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // Helper functions used to determine if the user is verified
    public function isVerified(){
        return $this->verified == User::VERIFIED_USER;
    }

    // Helper function used to create a verification code
    public static function generateVerificationCode(){
        return Str::random(40);
    }

    // Mutator used to change the value of a column when being retrieved from the DB
    public function getImageAttribute($image){
        return isset($image) ? env('APP_URL')."/img/".$image : null;
    }

    public function roles(){
        return $this->belongsToMany(Role::class);
    }

    // Describes the many to many relationship between users and groups with group_user intermediate table
    public function groups(){
        return $this->belongsToMany(Group::class)->whereNull('group_user.deleted_at')->withPivot('permission_id','unread_messages','created_at');
    }

    // Describes the many to many relationship between users and users with friendships intermediate table
    public function friends(){
        return $this->belongsToMany(User::class,'friendships','main_id','friend_id')->withPivot('alias', 'status','created_at','id','presence_friend','unread_messages');
    }

    // Describes the on to many relationship between a user (sender) and messages
    public function messagesSent(){
        return $this->hasMany(Message::class,'sender_id');
    }

    // Describes the one to many polymorphic relationship between a user (receiver) and messages
    public function messagesReceived(){
        return $this->morphMany(Message::class,'messageable');
    }

    // Describs the many to many relationship between users and notifications with notification_user intermediate table
    public function notifications(){
        return $this->belongsToMany(Notification::class)->withPivot('status')->withTimestamps();
    }
}
