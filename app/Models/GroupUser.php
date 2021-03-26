<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GroupUser extends Model
{
    use HasFactory,SoftDeletes;
    
    // Letting laravel know what columns can be mass assigned
    protected $fillable = [
        'group_id','user_id','permission_id','created_at'
    ];

    // Letting laravel know what the dates are so they can be processed as such
    protected $dates=['deleted_at'];

    // Letting laravel know what columns not to retrieve from the db
    protected $hidden = ['deleted_at','id'];

    // Letting laravel know for which table this model should be used
    protected $table = 'group_user';
}
