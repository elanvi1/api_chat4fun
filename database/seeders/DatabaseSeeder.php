<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\Group;
use App\Models\Message;
use App\Models\RoleUser;
use App\Models\GroupUser;
use App\Models\Friendship;
use App\Models\Notification;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Database\Factories\FriendshipFactory;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        User::flushEventListeners();
        Role::flushEventListeners();
        Group::flushEventListeners();
        Permission::flushEventListeners();
        GroupUser::flushEventListeners();
        RoleUser::flushEventListeners();
        Friendship::flushEventListeners();
        Message::flushEventListeners();

        User::truncate();
        Role::truncate();
        Group::truncate();
        Permission::truncate();
        GroupUser::truncate();
        RoleUser::truncate();
        Friendship::truncate();
        Message::truncate();
        Notification::truncate();
        DB::table('group_notification')->truncate();
        DB::table('notification_user')->truncate();
        DB::table('personal_access_tokens')->truncate();

        $userQuantity = 200;
        $groupQuantity = 20;
        $groupUserQuantity = 600;
        $friendshipQuantity = 1000;

        // Creating the permissions table
        Permission::factory()->create(['name'=> 'Regular']);
        Permission::factory()->create(['name'=> 'Admin']);
        Permission::factory()->create(['name'=> 'Add/Remove']);
        Permission::factory()->create(['name'=> 'Edit']);

        // Creating the users table
        User::factory()->times($userQuantity)->create();

        // Creating the groups table
        Group::factory()->times($groupQuantity)->create();

        // Creating the group_user table
        GroupUser::factory()->times($groupUserQuantity)->create()->each(function($groupUser){
            // For each user in a group 2 messages will be created with that user as the sender and the group as the receiver
            Message::factory()->times(2)->create([
                'sender_id' => $groupUser->user_id,
                'messageable_id' => $groupUser->group_id,
                'messageable_type' => Group::class,
            ]);
        });

        // Creating the friendships table
        Friendship::factory()->times($friendshipQuantity)->create()->each(function ($friendship){
            // For each friendship a second friendship must be created(check routes->api.php for more info)
            // First I check to see if there is one and if there isn't I create it
            $secondFriendship = Friendship::where([
                ['main_id' , $friendship->friend_id],
                ['friend_id' , $friendship->main_id]
            ])->first();

            if(!isset($secondFriendship)){
                Friendship::factory()->create([
                    'main_id' => $friendship->friend_id, 
                    'friend_id' => $friendship->main_id
                ]);
            }

            // For each friendship 2 messages are being created
            Message::factory()->times(2)->create([
                'sender_id' => $friendship->friend_id,
                'messageable_id' => $friendship->main_id,
                'messageable_type' => User::class,
            ]);

            Message::factory()->times(2)->create([
                'sender_id' => $friendship->main_id,
                'messageable_id' => $friendship->friend_id,
                'messageable_type' => User::class,
            ]);
        });
    }
}
