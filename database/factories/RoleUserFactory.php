<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use App\Models\RoleUser;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleUserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = RoleUser::class;

    protected static $valuesUsed = array();

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $allGood = false;

        while(!$allGood){
            $user = User::all()->random();
            $role = Role::all()->random();

            $array = [$user->id,$role->id];

            if(!in_array($array,self::$valuesUsed)){
                array_push(self::$valuesUsed,$array);
                $allGood = true;
            }
        }

        return [
            'role_id' => $role->id,
            'user_id' => $user->id
        ];
    }
}
