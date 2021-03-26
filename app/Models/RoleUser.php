<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RoleUser extends Model
{
    use HasFactory,SoftDeletes;

    // Letting laravel know what columns can be mass assigned
    protected $fillable = [
        'role_id','user_id'
    ];

    // Letting laravel know what the dates are so they can be processed as such
    protected $dates=['deleted_at'];

    // Letting laravel know what columns not to retrieve from the db
    protected $hidden = ['pivot','deleted_at'];

    protected $table = 'role_user';
}
