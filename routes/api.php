<?php

use App\Models\Friendship;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\Role\RoleController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Group\GroupController;
use App\Http\Controllers\User\UserRoleController;
use App\Http\Controllers\User\UserGroupController;
use App\Http\Controllers\Message\MessageController;
use App\Http\Controllers\User\UserMessagesController;
use App\Http\Controllers\Group\GroupMessagesController;
use App\Http\Controllers\Frienship\FriendshipController;
use App\Http\Controllers\Permission\PermissionController;
use App\Http\Controllers\Notification\NotificationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// ---FRIENDSHIP LOGIC---
// For each friendship between 2 users there are 2 entries in the "friendships" table, one for each user(each users id is the "main_id" in that entry and the others is the "friend_id"). 
// The friendship entries remain even if a user is removed from the others contacts list. In this case only the status of the friendship that has the id of the user that made the removal as the "main_id", is changed to "removed".
// This structure allows for 2 completely independent friendships between 2 users.
// There is no situation in each a user has friendship and his counterpart doesn't have one. They both have one or neither do.
// For simplicity I will call the friendship of the main user with the contact friendship 1 and the friendship of the contact with the main user friendship 2

// ---DB STRUCTURE---
// There are 4 main tables, which have no foreign keys or refferences to other tables:
// - users
// - groups
// - notifications
// - permissions

// There are 4 intermediate tables, that define a many to many relationship:
// - friendships : is a relationship between many users and many users.
// - group_notification: is a relatianonship between many groups and many notifications. 
// - group_user: is a relationship between many groups and many users.
// - notification_user: is a relationship between many notifications and many users.

// There are 2 tables that represent a one to many relationship:
// - group_user: is a relationship between one permission and many entries in the group_user table(basically the unique combination of the group and user)
// - messages: 
//  - is a relationship between one sender(user) and many messages
//  - is a relationship between one receiver(user or group) and many messages
// - personal_access_tokens: is a relationship between one user and many personal_access_tokens

// There is 1 table that has a polymorphic relationship:
// - messages: has a polymorphic relationship between one receiver, group or user, and many messages


Route::middleware(['auth:sanctum'])->group(function(){
  // User
  Route::resource('user.group',UserGroupController::class,['only'=>['update','destroy']]);
  Route::post('user/group',UserGroupController::class."@store");
  Route::resource('user.role',UserRoleController::class,['only'=>['update','destroy']]);
  Route::post('user/role',UserRoleController::class."@store");
  Route::resource('conversation_with_user/{user}',UserMessagesController::class,['only'=>['index']]);
  Route::get('online',UserController::class."@showOnline");
  Route::get('offline',UserController::class."@showOffline");
  Route::get('group/{group}/resetUnreadMessages',UserGroupController::class."@resetUnreadMessages");


  // GROUP
  Route::resource('group',GroupController::class,['only'=>['store','update','index','destroy']]);
  Route::get('group/{group}/messages',GroupMessagesController::class."@index");

  // ROLE
  Route::resource('role',RoleController::class,['only'=>['store','update','destroy','index']]);

  // PERMISSION
  Route::resource('permission',PermissionController::class,['only'=>['store','update','destroy','index']]);

  // FRIENDSHIP
  Route::resource('friendship',FriendshipController::class,['only'=>['store','update','index']]);
  Route::post('friendship/{friendship}/handleAcceptOrReject',FriendshipController::class."@handleAcceptOrReject");
  Route::post('active',FriendshipController::class."@userActive");
  Route::post('inactive',FriendshipController::class."@userInactive");
  Route::get('friendship/{friendship}/resetUnreadMessages',FriendshipController::class."@resetUnreadMessages");
  Route::get('userIdsPendingFriendships',FriendshipController::class."@userIdsPendingFriendships");

  // MESSAGE
  Route::resource('message',MessageController::class,['only'=>['store','destroy']]);
  
  // NOTIFICATION
  Route::resource('notification',NotificationController::class,['only'=>['index','update']]);
});

// USER
Route::resource('user',UserController::class,['except'=>['create','edit']]);


// AUTHENTICATION
Route::post('/login',UserController::class."@logIn");
Route::delete('/logout',UserController::class."@logOut");
Route::get('/refreshToken',UserController::class."@refreshToken");
Route::name('verify')->get('user/verify/{token}',UserController::class."@verify");
Route::name('resend')->get('user/{user}/resend',UserController::class."@resend");
Route::name('sendReactivateEmail')->get('user/{user}/sendReactivateEmail',UserController::class."@sendReactivateEmail");
Route::name('reactivate')->get('user/reactivate/{token}',UserController::class."@reactivate");

Route::get('/fix',function(){
  Friendship::where('main_id',1)->orWhere('friend_id',1)->update(['status'=>'accepted','alias'=>null]);
});





