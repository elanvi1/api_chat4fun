<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Permission;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupUserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = GroupUser::class;

    protected static $valuesUsed = array();

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $permission = Permission::all()->random();

        $allGood = false;

        while(!$allGood){
            $group = Group::all()->random();
            $user = User::all()->random();

            $array = [$group->id,$user->id];

            if(!in_array($array,self::$valuesUsed)){
                array_push(self::$valuesUsed,$array);
                $allGood = true;
            }
        }

        return [
            'group_id' => $group->id,
            'user_id' => $user->id,
            'permission_id' => $permission->id,
            'unread_messages' => 0
        ];
    }
}
