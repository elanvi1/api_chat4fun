<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $arr = ['1.jpg','2.jpg','3.jpg','4.jpg','5.jpg','6.jpg','7.jpg','8.jpg','9.jpg','10.jpg', null,null,null,null];

        shuffle($arr);

        return [
            'name' => $this->faker->name,
            'username' => $this->faker->unique()->userName,
            'email' => $this->faker->unique()->safeEmail,
            'about' => $this->faker->paragraph(1),
            'image' => $arr[0],
            'password' => Hash::make('aaaaaa'),
            'remember_token' => Str::random(10),
            'verified' => User::VERIFIED_USER,
            'email_verified_at' => now(),
            'verification_token' => null,
            'presence' => User::IS_OFFLINE
        ];
    }
}
