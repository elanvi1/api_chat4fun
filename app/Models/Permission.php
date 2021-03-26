<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Permission extends Model
{
    use HasFactory,SoftDeletes;

    // Letting laravel know what columns can be mass assigned
    protected $fillable = [
        'name'
    ];

    // Letting laravel know what the dates are so they can be processed as such
    protected $dates=['deleted_at'];

    // Letting laravel know what columns not to retrieve from the db
    protected $hidden = ['pivot','deleted_at'];
}
