<?php

namespace App\Http\Controllers\User;

use App\Models\User;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Friendship;
use App\Models\Permission;
use App\Models\Notification;
use Illuminate\Http\Request;
use App\Events\UserAddedToGroup;
use Illuminate\Support\Facades\DB;
use App\Events\UserGroupInfoChanged;
use App\Events\UserRemovedFromGroup;
use App\Http\Controllers\Controller;
use App\Events\UserPermissionChanged;
use App\Http\Controllers\ApiController;
use App\Events\UserWasAddedToGroupIndividual;
use App\Http\Controllers\Group\GroupController;
use App\Events\UserWasRemovedFromGroupIndividual;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserGroupController extends ApiController
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    
    // Method used at "user/group" endpoint for the "POST" request. It is used to create a connection between a group and a user by adding an entry in the group_user table.
    public function store(Request $request)
    {
        $rules = [
            'group_id' => 'required|numeric',
            'user_id' => 'required|numeric',
        ];

        $this->validate($request, $rules);

        // Making checks to be sure that there are no unauthorized requests.
        $group = Group::whereId($request->group_id)->firstOrFail();
        
        $user = User::whereId($request->user_id)->firstOrFail();
        
        $authUser = $request->user();

        $authGroupUser = GroupUser::where([
            ['group_id',$group->id],
            ['user_id',$authUser->id]
        ])->first();

        if(!isset($authGroupUser)){
            return $this->errorResponse('Can\'t add an user to a group you\'re not a part of',403);
        }

        // Checking to see if the main user has the necessary permission to add someone to the group
        if(($authGroupUser->permission_id !== 2) && ($authGroupUser->permission_id !== 3)){
            return $this->errorResponse('Only users with admin or add/remove permissions can add/remove persons from a group',403);
        }

        $checkFriendship = Friendship::where([
            ['main_id',$authUser->id],
            ['friend_id',$user->id]
        ])->first();

        // Checking to see if the user to be added is a contact of the main user
        if(!isset($checkFriendship) || ($checkFriendship->status !== Friendship::ACCEPTED_STATUS)){
            return $this->errorResponse('Can\'t add users to group if they are not in your contact list or if they are blocked or deleted',403);
        }

        $secondFriendship = Friendship::where([
            ['main_id',$user->id],
            ['friend_id',$authUser->id]
        ])->firstOrFail();

        // Checking to see if friendship 1 has "accepted" status
        if($secondFriendship->status !== Friendship::ACCEPTED_STATUS){
            return $this->errorResponse('Cant\'t add users to group if they\'ve blocked or removed you',403);
        }
        
        $secondAlias = isset($secondFriendship->alias) ? '(alias '.$secondFriendship->alias.')' : '';

        $checkGroupUser = GroupUser::where([
            ['group_id',$group->id],
            ['user_id',$user->id]
        ])->first();

        if(isset($checkGroupUser)){
           return $this->errorResponse('Specified user already belongs to specified group',409); 
        }

        $data = $request->only(['user_id','group_id']);
       
        $data['permission_id'] = 1;

        $members = null;

        DB::transaction(function() use ($group,$data,$request,$user,$authUser,$secondAlias,&$members){
            $groupUser = GroupUser::create($data);
        
            $permissionName = $this->getPermission($groupUser->permission_id);
            $membersTemp = $group->users;
    
            $members = $membersTemp->map(function($member) use($group,$user){
                $changedUser = collect($member->toArray())->only(['id','username','about','image']);
    
                $groupInfoForUser = GroupUser::where([
                    ['group_id',$group->id],
                    ['user_id',$changedUser['id']]
                ])->firstOrFail();
    
                $permissionName = $this->getPermission($groupInfoForUser->permission_id);
    
                $changedUser->put('permission',$permissionName);
                $changedUser->put('joined_at',$groupInfoForUser->created_at->toDateTimeString());

                $friendshipWithMember = Friendship::where([
                    ['main_id',$user->id],
                    ['friend_id',$changedUser['id']]
                ])->first();

                $getAlias = false;

                if(isset($friendshipWithMember)){
                    if($friendshipWithMember->status !== Friendship::PENDING_STATUS){
                        $getAlias = true;
                    }
                }
                
                // The 'alias' attribute of a member is retrieved from the friendships table
                $changedUser->put('alias', $getAlias ? $friendshipWithMember->alias : null);
    
                return  $changedUser;
            });
    
            $members = $members->keyBy(function($item){
                return 'info_user_'.$item['id'];
            });

            $lastMessage = GroupController::getLastMessage($group,$user);
    
            // For clarification on where each attribute comes from check GroupController
            $info = collect([
                'name'=> $group->name,
                'group_id' => $group->id,
                'description'=>$group->description,
                'image'=>$group->image,
                'unread_messages'=>0,
                'created_at'=>$group->created_at->toDateTimeString(),
                'permission'=>$permissionName,
                'joined_at'=>$groupUser->created_at->toDateTimeString(),
                'last_message'=>$lastMessage,
                'members'=>$members
            ]);

            // 2 notifcation are sent: one to the user being added and one to the group which will be seen by all its members
            $notificationToIndividual = $user->notifications()->create(['message'=>'You have been added to group '.$group->name.' by '.$authUser->username.$secondAlias,'title'=>'Added To Group']);

            $notificationToGroup = $group->notifications()->create(['message'=>$user->username.' was added to the group by '.$authUser->username,'title'=>'User Added to Group']);
    
            broadcast(new UserAddedToGroup($members->where('id',$user->id)->first(), $group->id, $notificationToGroup));
            broadcast(new UserWasAddedToGroupIndividual($info,$request->user_id,$notificationToIndividual));
        });
      

        $alias = isset($checkFriendship->alias) ? '(alias '.$checkFriendship->alias.')' : '';

        return $this->showMessage('User '.$user->username.$alias.' has been added to group '.$group->name);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */

    // Method used at "user/{userId}/group/{groupId}" endpoint for the "PATCH/PUT" request. It is used to change the permission of a user in a certain group
    public function update(Request $request, User $user,Group $group)
    {
        $rules = [
            'permission_id' => 'sometimes|required|numeric',
        ];

        $this->validate($request, $rules);

        $authUser = $request->user();

        $authGroupUser = GroupUser::where([
            ['group_id',$group->id],
            ['user_id',$authUser->id]
        ])->first();

        // Making checks to be sure that there are no unauthorized requests.
        if($authUser->id === $user->id){
            return $this->errorResponse('You can\'t change your own permission regardless of permission type',403);
        }

        if(!isset($authGroupUser)){
            return $this->errorResponse('Can\'t change permissions in a group you\'re not a part of',403);
        }

        // Checking if the main user has the necessary permission to make the change
        if($authGroupUser->permission_id !== 2){
            return $this->errorResponse('Only users with admin permission can change permissions for an user in that group',403);
        }
     
        $groupUser = GroupUser::where([
            ['user_id',$user->id],
            ['group_id',$group->id]
        ])->firstOrFail();

        $permission = Permission::whereId($request->permission_id)->firstOrFail();
        $oldPermissionName = $this->getPermission($groupUser->permission_id);

        $groupUser->permission_id = $permission->id;

        if($groupUser->isClean()){
            return $this->errorResponse('The values that you mentioned are the same as the previous ones',422);
        }

        DB::transaction(function() use ($groupUser,$group,$oldPermissionName,$user,$authUser){
            $groupUser->save();

            $permissionName = $this->getPermission($groupUser->permission_id);
          
            // Sending 2 notifications: one to the user whose permission changed and one to the group which can be seen by all its members
            $notificationToGroup = $group->notifications()->create(['message'=>'User '.$user->username.' permission was changed from '.$oldPermissionName.' to '.$permissionName.' by '.$authUser->username,'title'=>'User Permission Changed']);
            $notificationToIndividual = $user->notifications()->create(['message'=>'Your permission in group'.$group->name.' was changed from '.$oldPermissionName.' to '.$permissionName.' by '.$authUser->username,'title'=>'Your Permission Changed']);
    
            broadcast(new UserGroupInfoChanged(collect(['permission'=>$permissionName,'user_id'=>$user->id]),$group->id,$notificationToGroup));
            broadcast(new UserPermissionChanged(collect(['group_id'=>$group->id,'permission'=>$permissionName]),$user->id,$notificationToIndividual));
        });

        return $this->showMessage('Permission changed to '.$permission->name.' for user '.$user->username.' in group '.$group->name,200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */

    // Method used at "user/{userId}/group/{groupId}" endpoint for the "DELETE" request. It is used to remove the connection between a user and a group
    public function destroy(Request $request,User $user,Group $group)
    {
        $authUser = $request->user();

        $authGroupUser = GroupUser::where([
            ['group_id',$group->id],
            ['user_id',$authUser->id]
        ])->first();

        // Making checks to be sure that there are no unauthorized requests.
        if(!isset($authGroupUser)){
            return $this->errorResponse('Can\'t remove a person from a group you\'re not part of',403);
        }

        if(($authGroupUser->permission_id !== 2) && ($authGroupUser->permission_id !== 3) && ($authUser->id !== $user->id)){
            return $this->errorResponse('Only users with admin or add-remove permissions can add/remove persons from a group',403);
        }

        $groupUser = GroupUser::where([
            ['user_id',$user->id],
            ['group_id',$group->id]
        ])->firstOrFail();

        if($authGroupUser->permission_id === 3 && $groupUser->permission_id ===2){
            return $this->errorResponse('Need to have Admin permission in order to remove a user with Admin permission',403);
        }

        DB::transaction(function() use ($groupUser,$user,$group,$authUser){
            $groupUser->forceDelete();

            $notificationMessageToGroup = '';
            $titleNotificationToGroup = '';

            $otherUsersInGroup = GroupUser::where([
                ['group_id',$group->id],
                ['user_id','<>',$user->id]
            ])->get();

            if($otherUsersInGroup->isNotEmpty()){
                if($authUser->id !== $user->id){
                    // If the a user is removed from a group by someone else a notification is sent to that user informing him.
                    $notificationMessageToGroup = $user->username.' was removed from the group by '.$authUser->username;
                    $titleNotificationToGroup = 'User Removed From Group';
    
                    $notificationToIndividual = $user->notifications()->create(['message'=>'You have been removed from group '.$group->name.' by '.$authUser->username.'. The group conversation no longer appears under the groups tab','title'=>'Removed From Group']);
    
                    broadcast(new UserWasRemovedFromGroupIndividual($group->id,$user->id,$notificationToIndividual));
                }else{
                    $notificationMessageToGroup = $user->username.' has left the group';
                    $titleNotificationToGroup = 'User Left Group';
    
                    // Making sure somebody in the group has admin permission when somebody leaves by themselves
                    $usersWithAdminPermission = GroupUser::where([
                        ['group_id',$group->id],
                        ['user_id','<>',$authUser->id],
                        ['permission_id',2]
                    ])->get();
    
                    // If there are no users with admin permission in the group then the user that has been in the group the longest will be given admin permission
                    if($usersWithAdminPermission->isEmpty()){
                        $oldestUserGroup = GroupUser::where([
                            ['group_id',$group->id],
                            ['user_id','<>',$authUser->id]
                        ])->orderBy('created_at')->firstOrFail();

                        $oldestUserInGroup = User::where('id',$oldestUserGroup->user_id)->firstOrFail();

                        $oldPermissionName = $this->getPermission($oldestUserGroup->permission_id);
                        $oldestUserGroup->permission_id = 2;
                        $oldestUserGroup->save();
                        $newPermissionName = $this->getPermission(2);
    
                        // 2 notifications will be sent: one to the user whose permission is changed to admin and one to the group which can be seen by all its members
                        $notificationPermissionChangeToGroup = $group->notifications()->create(['message'=>'User '.$oldestUserInGroup->username.' permission was changed from '.$oldPermissionName.' to '.$newPermissionName.' by the system because there were no admins left in the group','title'=>'User Permission Changed']);

                        $notificationPermissionChangeToIndividual = $oldestUserInGroup->notifications()->create(['message'=>'Your permission in group'.$group->name.' was changed from '.$oldPermissionName.' to '.$newPermissionName.' by the system because there were no admins left in the group','title'=>'Your Permission Changed']);
    
                        broadcast(new UserGroupInfoChanged(collect(['permission'=>$newPermissionName,'user_id'=>$oldestUserInGroup->id]),$group->id,$notificationPermissionChangeToGroup));
                        broadcast(new UserPermissionChanged(collect(['group_id'=>$group->id,'permission'=>$newPermissionName]),$oldestUserInGroup->id,$notificationPermissionChangeToIndividual));
                    }
                }
    
                $notificationToGroup = $group->notifications()->create(['message'=>$notificationMessageToGroup,'title'=>$titleNotificationToGroup]);
    
                broadcast(new UserRemovedFromGroup(collect(['user_id'=>$user->id,'group_id'=>$group->id]),$notificationToGroup));
            }else{
                // Deleting the group if the person leaving the group is the last person in the group
                $group->forceDelete();
            }
        });

        $firstPart = "User ".$user->username." is";

        if($user->id === $authUser->id){
            $firstPart = "You are";
        }
        
        return $this->showInfo(['group_id'=>$group->id],200,$firstPart." no longer in group ".$group->name);
    }

    // Helper method used to get the permission name based on the id
    public function getPermission($id){
        $permissionName = Permission::whereId($id)->firstOrFail()->name;

        return $permissionName;
    }

    // Method used at "group/{group}/resetUnreadMessages" endpoint for the "GET" request. It is used to reset the unread messages of the main user in a group chat
    public function resetUnreadMessages(Request $request,Group $group){
        $authUser = $request->user();

        $groupUser = GroupUser::where([
            ['group_id',$group->id],
            ['user_id',$authUser->id]
        ])->firstOrFail();

        $groupUser->unread_messages = 0;
        $groupUser->save(); 
    }
}
