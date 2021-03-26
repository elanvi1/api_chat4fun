<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Friendship;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Factories\Factory;

class FriendshipFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Friendship::class;

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
            $main = User::all()->random();
            $friend = User::all()->except($main->id)->random();

            $array = [$main->id,$friend->id];

            if(!in_array($array,self::$valuesUsed)){
                array_push(self::$valuesUsed,$array);
                $allGood = true;
            }
        }

        return [
            'main_id' => $main->id,
            'friend_id' => $friend->id,
            'status' => $status = $this->faker->randomElement([Friendship::ACCEPTED_STATUS,Friendship::ACCEPTED_STATUS,Friendship::ACCEPTED_STATUS,Friendship::ACCEPTED_STATUS,Friendship::ACCEPTED_STATUS,Friendship::ACCEPTED_STATUS,
            Friendship::BLOCKED_STATUS,
            Friendship::REMOVED_STATUS]),
            'blocked_at' => $status === Friendship::BLOCKED_STATUS ? now() : null,
            'alias' => $this->faker->firstName,
            'presence_friend' => Friendship::USER_INACTIVE,
            'unread_messages' => 2
        ];
    }
}
