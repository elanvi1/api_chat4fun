<?php

namespace App\Http\Controllers\User;

use Carbon\Carbon;
use App\Models\User;
use App\Mail\UserCreated;
use App\Events\UserOnline;
use App\Models\Friendship;
use App\Events\UserOffline;
use App\Models\Notification;
use Illuminate\Http\Request;
use App\Mail\UserReactivated;
use App\Events\UserInfoChanged;
use Illuminate\Support\Facades\DB;
use App\Events\UserGroupInfoChanged;
use App\Events\UserRemovedFromGroup;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Validation\ValidationException;

class UserController extends ApiController
{
    // Using the constructor in order to use authentication check middleware(sanctum TBA) only for certain methods
    public function __construct(){
        $this->middleware('auth:sanctum')->except(['store','logIn','refreshToken','verify','resend','sendReactivateEmail','reactivate']);
    }

    // Method used at "/user" endpoint for the "GET" request. It is used to retrieve basic information about the users that match a certain serach criteria 
    public function index(Request $request)
    {
        // The 'username' attribute in the payload represents the username that the main user searched for. It doesn't need to be an exact match, the basic info of users whose username contain the searched string will be returned.
        $rules = [
            'username' => 'required|max:189',
        ];

        $this->validate($request, $rules);

        $authUser = $request->user();

        // Getting the users whose username contains the searched string
        $users = User::where('username','like','%'.$request->username.'%')->get();

        $message = 'No users were found matching the search criteria';

        if(isset($users)){
            $users = $users->map(function($user) use($authUser){
                $friendship = Friendship::where([
                    ['main_id',$authUser->id],
                    ['friend_id',$user->id]
                ])->first();

                // Filtering to make sure that only users with which there is no friendship and users with which there is a friendship with 'removed' status are returned
                if(isset($friendship)){
                    if($friendship->status !== Friendship::REMOVED_STATUS){
                        return null;
                    }
                }

                if($user->id === $authUser->id){
                    return null;
                }

                // Only basic info about each user is returned 
                $changedUser = collect($user->toArray())->only(['id','username','about','image']);

                return $changedUser;
            });

            $users = $users->whereNotNull();

            if($users->isNotEmpty()){
                $users = $users->keyBy(function($item){
                    return 'info_user_'.$item['id'];
                });

                $users = $users->sortBy('username');
    
                $message = $users->count().' user(s) were found matching the search criteria';
            }else{
                $users = null;
            }
        }

        return $this->showInfo($users,200,$message);
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    // Method used at "/user" endpoint for the "POST" request. It is used to store information about a user in the db, when the user creates the account
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|max:189',
            'username' => 'required|max:17|unique:users',
            'email' => 'required|email|unique:users|max:189',
            'password' => 'required|min:6|confirmed|max:189',
            'about' => 'max:120',
            'image' => 'image|file|mimes:jpeg,png,gif,webp|max:2048',
        ];

        $this->validate($request, $rules);

        $data = $request->only(['name','username','email','password','about']);
        $data['password'] = Hash::make($request->password);
        $data['verified'] = User::UNVERIFIED_USER;
        $data['verification_token'] = User::generateVerificationCode();

        if($request->hasFile('image')){
            $data['image'] = $request->image->store('');
        }else{
            $data['image'] = null;
        }

        $user = User::create($data);

        // User needs to verify his email in order to use the account
        return $this->showMessage('Successfull registration, a verification email has been sent to '.$data['email'].'. Please check it out and you\'ll be able to chat with your friends in not time',201,$user);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */

    // Method used at "/user/{userId}" endpoint for the "GET" request. It is used to retrieve information about the authenticated(main) user
    public function show(Request $request,User $user)
    {
        // Making checks to be sure that there are no unauthorized requests.
        if($request->user()->id !== $user->id){
            return $this->errorResponse('Can\'t view personal info of another user', 403);
        }

        $userInfo = collect($user->toArray())->only(['id','name','username','email','about','image']);

        $userInfo->put('created_at', $user->created_at->toDateTimeString());

        return $this->showInfo($userInfo);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */

    // Method used at "/user/{userId}" endpoint for the "PATCH/PUT" request. It is used to change information about the authenticated(main) user
    public function update(Request $request, User $user)
    {
        $rules = [
            'name' => 'min:1|max:189',
            'username' => 'min:1|max:17|unique:users',
            'email' => 'email|unique:users|max:189',
            'password' => 'min:6|max:189',
            'new_password' => 'min:6|confirmed|max:189',
            'about' => 'max:120',
            'image' => 'nullable|image|file|mimes:jpeg,png,gif,webp|max:2048',
        ];

        $this->validate($request, $rules);
       
        // Making checks to be sure that there are no unauthorized changes
        if($request->user()->id !== $user->id){
            return $this->errorResponse('Can\'t modify personal information of another user', 403);
        }

        $forBroadcast = collect();

        if($request->has('name')){
            $user->name = $request->name;
        }

        if($request->has('username')){
            $user->username = $request->username;
            $forBroadcast->put('username',$request->username);
        }

        if($request->has('about')){
            $user->about = $request->about;
            $forBroadcast->put('about',$request->about);
           
        }

        // If the email is changed then the main user must verify his new email in order to use the account again
        if ($request->has('email') && $user->email != $request->email) {
            if(!$request->has('password')){
                return $this->errorResponse('Password must also be provided when changing the email',401);
            }

            if(!Hash::check($request->password, $user->password)){
                return $this->errorResponse('The provided credentials are incorrect',401);
            }

            $user->verified = User::UNVERIFIED_USER;
            $user->verification_token = User::generateVerificationCode();
            $user->email = $request->email;
        }

        if($request->has('new_password')){
            if(!$request->has('password')){
                return $this->errorResponse('A new password must also be provided when changing the password',403);
            }

            if(!Hash::check($request->password, $user->password)){
                return $this->errorResponse('The provided credentials are incorrect',401);
            }

            $user->password = Hash::make($request->new_password);
        }

        if($request->hasFile('image') || $request->has('image')){
            if(isset($user->image)){
                $imgName = explode('/', parse_url($user->image,PHP_URL_PATH))[2];
                Storage::delete($imgName);
            }
            
            if(isset($request->image)){
                $user->image = $request->image->store('');
            }else{
                $user->image = null;
            }
            
            $forBroadcast->put('image',$user->image);
        }

        if($user->isClean()){
            return response()->json(['error'=>'The values that you mentioned are the same as the previous ones','code'=>422],422);
        }

        $user->save();

        // Broadcasting to each contact and group that the main users info has changed
        if($forBroadcast->isNotEmpty()){
            $forBroadcast->put('user_id',$request->user()->id);
            broadcast(new UserInfoChanged($forBroadcast));

            $groupIds = $user->groups->pluck('id')->values();
            
            $groupIds->each(function ($groupId) use ($forBroadcast){
                broadcast(new UserGroupInfoChanged($forBroadcast,$groupId));
            });

            unset($user['groups']);
        }

        return $this->showInfo($user);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */

    // Method used at "/user/{userId}" endpoint for the "DELETE/PUT" request. It is used to remove information about the authenticated(main) user from the DB.
    public function destroy(Request $request,User $user)
    {
        // Making checks to be sure that there are no unauthorized requests
        if($request->user()->id !== $user->id){
            return $this->errorResponse('Can\'t delete another user', 403);
        }

        DB::transaction(function() use($user,$request){
            // Sending a notification to each contact that has a friendship with 'accepted' status with the main user
            $notification = Notification::create(['message'=>'User '.$user->username.' has deleted their account. You are still able to see your conversation and send messages to him/her, but he/she will only see them if he/she reactivates their account','title'=>'Someone deleted their account']);

            $friendships = Friendship::where([
                ['friend_id',$user->id],
                ['status',Friendship::ACCEPTED_STATUS]
            ])->get();

            if($friendships->isNotEmpty()){
                $friendships->each(function($friendship) use($notification){
                    // Changing the status of each friendship 2 to 'deleted'
                    $friendship->status = Friendship::DELETED_STATUS;
                    $friendship->save();

                    $friend = User::withTrashed()->where('id',$friendship->main_id)->first();

                    if(isset($friend)){
                        $friend->notifications()->attach($notification->id);
                    }
                });
            }

            $groups = $user->groups;

            // Sending a notification to each group
            if($groups->isNotEmpty()){
                $groupNotification = Notification::create(['message'=>'User '.$user->username.' has left the group because of account deletion','title'=>'User Left Group']);

                foreach ($groups as $group) {
                    $group->notifications()->attach($groupNotification->id);

                    broadcast(new UserRemovedFromGroup(collect(['user_id'=>$user->id,'group_id'=>$group->id]),$groupNotification));
                    
                    $group->pivot->forceDelete();
                }
            }
            
            unset($user['groups']);

            if(isset($user->image)){
                $imgName = explode('/', parse_url($user->image,PHP_URL_PATH))[2];
            
                Storage::delete($imgName);
            }
    
            broadcast(new UserInfoChanged(collect(['status'=>Friendship::DELETED_STATUS,'user_id'=>$user->id]),$notification));

            // Creating a verification token which will be used if the user decides to reactivate his account
            $user->verification_token = User::generateVerificationCode();
            $user->save();

            $this->showOffline($request);
            $user->delete();
            $user->tokens()->delete();
        });

        return $this->showMessage('User '.$user->username.' was successfully deleted. If you would like to reactivate the account log in with your credentials and follow the provided instructions');
    }

    // Method used at "/login" endpoint for the "POST" request. It is used to log in the user in the app. It has 2 major steps:
    // 1) Check the users credentials
    // 2) Create a token(if the credentials match) which the user can use to access endpoints
    public function logIn(Request $request){
        $request-> validate([
            'email'=>'required|email',
            'password'=>'required|min:6|max:189',
            'device_name' => 'required'
        ]);

        $user = User::withTrashed()->where('email', $request->email)->firstOrFail();

        // Checking to see if the provided credentials match
        if(!Hash::check($request->password, $user->password)){
            return $this->errorResponse('The provided credentials are incorrect',401);
        }

        // Checking to see if the account was deactivated
        if($user->trashed()){
            return $this->errorResponse('You have deactivated your account if you would like to reactivate it click ',404,['link'=>env('APP_URL')."/user/".$user->id."/sendReactivateEmail"]);
        }

        // Checking to see if the email is verified
        if((int)$user->verified !== 1){
            return $this->errorResponse('Please verify your email and you\'ll be chatting with your friends in no time. If you can\'t find the email please check the spam folder. If you still can\'t find it, we can resend it by clicking ',403,['link'=>env('APP_URL')."/user/".$user->id."/resend"]);
        }

        $userTokensFromSameDevice = $user->tokens()->where('name',$request->device_name)->get();

        // Deleting previous tokens for the specified device
        if(isset($userTokensFromSameDevice)){
            foreach($userTokensFromSameDevice as $token){
                $token->delete();
            }
        }

        $createdToken = $user->createToken($request->device_name);

        // Providing token information along with the user id
        return $this->showMessage('Successfull login',201,[
            'token' => $createdToken->plainTextToken,
            'expiry_token' => 3600,
            'refresh_token' => $createdToken->plainTextRefreshToken,
            'expiry_refresh_token' => 1209600,
            'user_id' => $user->id
        ]);
    }

    // Method used at "/logout" endpoint for the "DELETE" request. It is used to delete the token from the DB and show the user offline
    public function logOut(Request $request){
        $fullToken = $request->bearerToken();
        $user = $request->user();

        $id = explode('|',$fullToken)[0];

        $this->showOffline($request);

        $user->tokens()->whereId($id)->delete();
    }

    // Method used at "/refreshToken" endpoint for the "GET" request. It is used to get a new token and refresh token
    public function refreshToken(Request $request){
        $refresh_token = $request->bearerToken();


        if(!isset($refresh_token)){
            return $this->errorResponse('Please provide the refresh_token as a bearer token in the authorization header',401);
        }

        $id = explode('|',$refresh_token)[0];
        $actualToken = explode('|',$refresh_token)[1];

        $personalAccessToken = PersonalAccessToken::whereId($id)->first();

        // Checking to see if the token provided by the user exists
        if(!isset($personalAccessToken)){
            return $this->errorResponse('There is no access token for the provided id',401);
        }

        // Checking to see if the refresh token provided by the user matches
        $tokenMatch = Hash::check($actualToken,$personalAccessToken->refresh_token);

        if(!$tokenMatch){
            return $this->errorResponse('The provided refresh_token doesn\'t match',401);
        }

        $created_at = $personalAccessToken->created_at;
        $expiry_date = $created_at->addDays(14);

        // Checking to see if the refresh token expired
        if(Carbon::now()->gt($expiry_date)){
            return $this->errorResponse('Refresh token expired, please log in again',401);
        }

        $user = User::whereId($personalAccessToken->tokenable_id)->firstOrFail();

        $createdToken = $user->createToken($personalAccessToken->name);

        // Deleting the provided token
        $personalAccessToken->delete();

        // Returning the info about a new token
        return $this->showMessage('Successfull token refresh',201,[
            'token' => $createdToken->plainTextToken,
            'expiry_token' => 3600,
            'refresh_token' => $createdToken->plainTextRefreshToken,
            'expiry_refresh_token' => 1209600,
            'user_id' => $user->id
        ]);
    }

    // Method used at "user/verify/{token}" endpoint for the "GET" request. It is used to verify the email address
    public function verify($token)
    {
        $user = User::where('verification_token', $token)->firstOrFail();

        $user->verified= User::VERIFIED_USER;
        $user->verification_token = null;
        $user->email_verified_at = Carbon::now();
        $user->save();

        return $this->showMessage('The account has been verified successfully');
    }

    // Method used at "user/{user}/resend" endpoint for the "GET" request. It is used to resend the verification email.
    public function resend(User $user){
        if($user->isVerified()){
            return $this->errorResponse('User is already verified',409);
        }

        retry(5,function() use ($user){
            Mail::to($user)->send(new UserCreated($user));
        }, 100);

        return $this->showMessage('The verification email has been resent');
    }
    
    // Method used at "user/{user}/sendReactivateEmail" endpoint for the "GET" request. It is used to send an account reactivation email.
    public function sendReactivateEmail($id){
        $user = User::onlyTrashed()->whereId($id)->firstOrFail();

        retry(5,function() use ($user){
            Mail::to($user)->send(new UserReactivated($user));
        }, 100);

        return $this->showMessage('The account reactivation email has been sent');
    }
   
    // Method used at "user/reactivate/{token}" endpoint for the "GET" request. It is used to reactivate the account.
    public function reactivate($token){
        $user = User::onlyTrashed()->where('verification_token', $token)->firstOrFail();

        DB::transaction(function() use ($user){
            $user->restore();
            $user->verification_token = null;
            $user->save();

            // Sending a notification to each contact that has a friendship with 'deleted' status with the main user.(it means that the status was 'accepted' before the account deactivation)
            $notification = Notification::create(['message'=>'User '.$user->username.' has reactivated their account. They can now see past and future messages from you','title'=>'Someone reactivated their account']);

            $friendships = Friendship::where([
                ['friend_id',$user->id],
                ['status',Friendship::DELETED_STATUS]
            ])->get();

            if($friendships->isNotEmpty()){
                $friendships->each(function($friendship) use($notification){
                    // Changing every friendship 2 status to 'accepted' back
                    $friendship->status = Friendship::ACCEPTED_STATUS;
                    $friendship->save();

                    $friend = User::withTrashed()->where('id',$friendship->main_id)->first();

                    if(isset($friend)){
                        $friend->notifications()->attach($notification->id);
                    }
                });
            }

            broadcast(new UserInfoChanged(collect(['status'=>Friendship::ACCEPTED_STATUS,'user_id'=>$user->id]),$notification));
        });

        return $this->showMessage('The account has been reactivated successfully');
    }

    // Method used at "/online" endpoint for the "GET" request. It is used to show the main user online
    public function showOnline(Request $request){
        $authUser = $request->user();

        $authUser->presence = USER::IS_ONLINE;
        $authUser->save();

        broadcast(new UserOnline($authUser->id));
    }

    // Method used at "/offline" endpoint for the "GET" request. It is used to show the main user offline
    public function showOffline(Request $request){
        $authUser = $request->user();

        DB::transaction(function() use ($authUser){
            $authUser->presence = USER::IS_OFFLINE;
            $authUser->save();

            Friendship::where([
                ['friend_id',$authUser->id],
                ['presence_friend',Friendship::USER_ACTIVE]
            ])->update(['presence_friend'=>Friendship::USER_INACTIVE]);
        });

        broadcast(new UserOffline($authUser->id));
    }
}
