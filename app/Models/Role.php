<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Role extends Model
{
    use HasFactory,SoftDeletes;

    // Letting laravel know what the dates are so they can be processed as such
    protected $dates=['deleted_at'];

    // Letting laravel know what columns can be mass assigned
    protected $fillable = [
        'name', 
    ];

    // Letting laravel know what columns not to retrieve from the db
    protected $hidden = ['pivot','deleted_at'];

    public function users(){
        return $this->belongsToMany(User::class);
    }
}
