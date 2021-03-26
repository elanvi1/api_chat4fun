<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Group;
use App\Models\Message;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Message::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        // $sender = User::all()->random();

        // $receiverType = $this->faker->randomElement([User::class,Group::class]);

        // if($receiverType === User::class){
        //     $receiver = User::all()->except($sender->id)->random();
        // }else{
        //     $receiver = Group::all()->random();
        // }

        return [
            'sender_id' => 0,
            'messageable_id' => 0,
            'messageable_type' => 'None',
            'message' => $this->faker->paragraph(1),
            'status' => Message::SENT_STATUS
        ];
    }
}
