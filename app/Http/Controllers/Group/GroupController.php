<?php

namespace App\Http\Controllers\Group;

use App\Events\GroupDeleted;
use App\Models\User;
use App\Models\Group;
use App\Models\Message;
use App\Models\GroupUser;
use App\Models\Friendship;
use App\Models\Permission;
use App\Models\Notification;
use Illuminate\Http\Request;
use App\Events\GroupInfoUpdated;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Storage;

// Method used at '/group' endpoint for the "GET" request. Returns information about each group the main user is in.
class GroupController extends ApiController
{
    public function index(Request $request){
        $authUser = $request->user();
        $groups = $authUser->groups;

        $info = collect();

        foreach ($groups as $group) {
            $relation = $group->pivot;
            $permissionName = $this->getPermission($relation->permission_id);
            $membersTemp = $group->users;

            $members = $membersTemp->map(function($user,$key) use($group,$authUser){
                // The 'id','username','about','image' attributes of a member are retrieved from the users table
                $changedUser = collect($user->toArray())->only(['id','username','about','image']);

                $groupInfoForUser = GroupUser::where([
                    ['group_id',$group->id],
                    ['user_id',$changedUser['id']]
                ])->firstOrFail();

                $permissionName = $this->getPermission($groupInfoForUser->permission_id);

                // The 'permission' and 'joined_at' attributes of a member are retrieved from the group_user table
                $changedUser->put('permission',$permissionName);
                $changedUser->put('joined_at',$groupInfoForUser->created_at->toDateTimeString());

                $friendship = Friendship::where([
                    ['main_id',$authUser->id],
                    ['friend_id',$user->id]
                ])->first();

                $getAlias = false;

                if(isset($friendship)){
                    if($friendship->status !== Friendship::PENDING_STATUS){
                        $getAlias = true;
                    }
                }
                
                // The 'alias' attribute of a member is retrieved from the friendships table
                $changedUser->put('alias', $getAlias ? $friendship->alias : null);

                return  $changedUser;
            });

            $members = $members->keyBy(function($item){
                return 'info_user_'.$item['id'];
            });

            $lastMessage = self::getLastMessage($group,$authUser);

            // The 'name','group_id','description' and 'created_at' attributes are retrieved from the groups table
            // The 'unread_messages','permission' and 'joined_at' attributes are retrieved from the group_user table
            // The 'last_message' attribute is retrieved from the messages table
            // For members see above
            $individualInfo = collect([
                'name'=> $group->name,
                'group_id' => $group->id,
                'description'=>$group->description,
                'image'=>$group->image,
                'unread_messages'=>$relation->unread_messages,
                'created_at'=>$group->created_at->toDateTimeString(),
                'permission'=>$permissionName,
                'joined_at'=>$relation->created_at->toDateTimeString(),
                'last_message'=> $lastMessage,
                'members'=>$members
            ]);

            $info->put('info_group_'.$individualInfo['group_id'],$individualInfo);
        }

        return $this->showInfo($info);
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    // Method used at '/group' endpoint for the "POST" request. Stores the information about a newly created group in the groups table
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|max:17',
            'description' => 'max:120',
            'image' => 'image|file|mimes:jpeg,png,gif,webp|max:2048',
        ];

        $this->validate($request, $rules);

        $authUser = $request->user();

        $data = $request->only(['name','description','image']);

        if($request->hasFile('image')){
            $data['image'] = $request->image->store('');
        }else{
            $data['image'] = null;
        }

        
        $groupInfo = [];

        DB::transaction(function () use ($data,$authUser,&$groupInfo){
            $group = Group::create($data);

            $relation = GroupUser::create(['group_id'=>$group->id,'user_id'=>$authUser->id,'permission_id'=>2]);

            $userInfo = collect($authUser->toArray())->only(['id','username','about','image']);

            $permissionName = $this->getPermission(2);
            $joinedAt = $relation->created_at->toDateTimeString();

            $userInfo->put('permission',$permissionName);
            $userInfo->put('joined_at',$joinedAt);
            $userInfo->put('alias',null);

            $members = collect(['info_user_'.$authUser->id => $userInfo]);

            // The info about the group which will be returned, it is the same as the info returned from the "index" method but just for newly created group
            $groupInfo = collect([
                'name'=> $group->name,
                'group_id' => $group->id,
                'description'=>$group->description,
                'image'=>$group->image,
                'created_at'=>$group->created_at->toDateTimeString(),
                'permission'=>$permissionName,
                'joined_at'=>$joinedAt,
                'last_message'=> null,
                'members'=>$members
            ]);
        });

        return $this->showInfo($groupInfo,201,'New group '.$data['name'].' was created, you can interact with it under the groups tab');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Group  $group
     * @return \Illuminate\Http\Response
     */

    // Method used at '/group' endpoint for the "PATCH/PUT" request. Changes information about a group in the groups table.
    public function update(Request $request, Group $group)
    {
        $rules = [
            'name' => 'sometimes|required|max:17',
            'description' => 'max:120',
            'image' => 'nullable|image|file|mimes:jpeg,png,gif,webp|max:2048',
        ];

        $this->validate($request, $rules);

        // Making checks to be sure that there are no unauthorized changes.
        $groupUser = GroupUser::where([
            ['group_id',$group->id],
            ['user_id',$request->user()->id]
        ])->firstOrFail();

        // Checking if the main user has the necessary permission to make such a change
        if(!($groupUser->permission_id === 2) && !($groupUser->permission_id === 4)){
            return $this->errorResponse('User doesn\'t have the required permissions to edit group info',403);
        }

        $forBroadcast = collect(['group_id'=>$group->id]);

        if($request->has('name')){
            $group->name = $request->name;
            $forBroadcast->put('name', $request->name);
        }

        if($request->has('description')){
            $group->description = $request->description;
            $forBroadcast->put('description', $request->description);
        }

        if($request->hasFile('image') || $request->has('image')){
            if(isset($group->image)){
                $imgName = explode('/', parse_url($group->image,PHP_URL_PATH))[2];
                Storage::delete($imgName);
            }
            
            if(isset($request->image)){
                $group->image = $request->image->store('');
            }else{
                $group->image = null;
            }
            
            $forBroadcast->put('image', $group->image);
        }

        if($group->isClean()){
            return response()->json(['error'=>'The values that you mentioned are the same as the previous ones','code'=>422],422);
        }

        $group->save();

        broadcast(new GroupInfoUpdated($forBroadcast));

        return $this->showInfo($forBroadcast);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Group  $group
     * @return \Illuminate\Http\Response
     */

    // Method used at '/group' endpoint for the "DELETE" request. Deletes information about a group in the groups table.
    public function destroy(Request $request,Group $group)
    {
        $authUser = $request->user();

        // Making checks to be sure that there are no unauthorized changes.
        $groupUser = GroupUser::where([
            ['group_id',$group->id],
            ['user_id',$authUser->id]
        ])->firstOrFail();

        // Checking if the main user has the necessary permission to make such a change
        if(!($groupUser->permission_id === 2)){
            return $this->errorResponse('You don\'t have the required permission to delete the group',403);
        }

        DB::transaction(function() use($group,$authUser){
            // A notification will be sent to all the users informing them that the group was deleted
            $notification = Notification::create(['message'=>'Group '.$group->name.' has been deleted by '.$authUser->username.'. As a result it has been removed from your groups tab and you can no longer interact with it','title'=>'Group Deleted']);

            foreach($group->users as $user){
                $user->pivot->forceDelete();
                $user->notifications()->attach($notification->id);
                broadcast(new GroupDeleted($group->id,$user->id,$notification));
            }

            $group->forceDelete();
        });

        if(isset($group->image)){
            $imgName = explode('/', parse_url($group->image,PHP_URL_PATH))[2];

            Storage::delete($imgName);
        }

        return $this->showMessage($group->name.' group was successfully deleted');
    }

    // Helper method used to retrieve the name of a permission based on the id
    public function getPermission($id){
        $permissionName = Permission::whereId($id)->firstOrFail()->name;

        return $permissionName;
    }

    // Helper method used to get the last message in group chat
    public static function getLastMessage($group,$authUser){
        $lastMessage = Message::where([
            ['messageable_type',Group::class],
            ['messageable_id',$group->id]
        ])->orderBy('id','desc')->first();

        if(isset($lastMessage)){
            $lastMessage = fractal($lastMessage, new $lastMessage->transformer)->toArray()['data'];
            

            // GET username or alias(if applicable ) of the user that sent last message
            if($lastMessage['sender_id'] !== $authUser->id){
                $friendshipLastMsg = Friendship::where([
                    ['main_id',$authUser->id],
                    ['friend_id',$lastMessage['sender_id']]
                ])->first();

                $lastMessage['sender_name'] = User::withTrashed()->where('id',$lastMessage['sender_id'])->firstOrFail()->username;

                if(isset($friendshipLastMsg)){
                    if(isset($friendshipLastMsg->alias) && (($friendshipLastMsg->status === Friendship::ACCEPTED_STATUS)  || $friendshipLastMsg->status === Friendship::BLOCKED_STATUS)){
                        $lastMessage['sender_name'] = $friendshipLastMsg->alias;
                    }
                }
            }
        }

        return $lastMessage;
    }
}
