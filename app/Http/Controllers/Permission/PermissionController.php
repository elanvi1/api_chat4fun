<?php

namespace App\Http\Controllers\Permission;

use App\Models\User;
use App\Models\RoleUser;
use App\Models\GroupUser;
use App\Models\Permission;
use App\Models\Notification;
use Illuminate\Http\Request;
use App\Events\PermissionAdded;
use App\Events\PermissionDeleted;
use App\Events\PermissionUpdated;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiController;

class PermissionController extends ApiController
{
    public function index(Request $request){
        $permissions = Permission::all();

        $permissions = $permissions->keyBy(function($item){
            return 'info_permission_'.$item['id'];
        });

        return $this->showInfo($permissions);
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|max:189',
        ];

        $this->validate($request, $rules);

        $user = $request->user();
        $userRole = RoleUser::where([
            ['role_id',1],
            ['user_id',$user->id]
        ])->first();

        if(!isset($userRole)){
            return $this->errorResponse('Only users with admin roles can add permissions',403);
        }

        $data = $request->only(['name']);

        $permission =null;

        DB::transaction(function() use($user,$data,&$permission){
            $permission = Permission::create($data);

            $userIds = GroupUser::where('permission_id',2)->get()->pluck('user_id')->unique()->values();

            $notification = Notification::create(['message'=>'New permission '.$permission->name.' was added to the list of permissions that can be given in a group by an admin','title'=>'New Permission Added']);

            $userIds->each(function($id) use($permission,$notification){
                $notifiableUser = User::where('id',$id)->first();

                if(isset($notifiableUser)){
                    $notifiableUser->notifications()->attach($notification->id);
                }

                broadcast(new PermissionAdded($permission, $id, $notification));
            });
        });
        
        return $this->showInfo($permission,201);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Permission  $permission
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Permission $permission)
    {
        $rules = [
            'name' => 'sometimes|required|max:189',
        ];

        $this->validate($request, $rules);

        $user = $request->user();
        $userRole = RoleUser::where([
            ['role_id',1],
            ['user_id',$user->id]
        ])->first();

        if(!isset($userRole)){
            return $this->errorResponse('Only users with admin roles can update permission info',403);
        }

        $oldPermissionName = $permission->name;
        $permission->name = $request->name;

        if($permission->isClean()){
            return response()->json(['error'=>'The values that you mentioned are the same as the previous ones','code'=>422],422);
        }

        DB::transaction(function() use($user,$permission,$oldPermissionName){
            $permission->save();

            $userIds = GroupUser::where('permission_id',$permission->id)->get()->pluck('user_id')->unique()->values();

            $notification = Notification::create(['message'=>'Group permission '.$oldPermissionName.' has changed its name to '.$permission->name,'title'=>'Permission Name Changed']);

            $userIds->each(function($id) use($permission,$notification){
                $notifiableUser = User::where('id',$id)->first();

                if(isset($notifiableUser)){
                    $notifiableUser->notifications()->attach($notification->id);
                }

                broadcast(new PermissionUpdated($permission, $id,$notification));
            });
        });

       

        return $this->showInfo($permission);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Permission  $permission
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request,Permission $permission)
    {
        $user = $request->user();
        $userRole = RoleUser::where([
            ['role_id',1],
            ['user_id',$user->id]
        ])->first();

        if(!isset($userRole)){
            return $this->errorResponse('Only users with admin roles can delete permissions',403);
        }

        if($permission->id ===1){
            return $this->errorResponse('Can\'t delete regular permission as this is the default value given when adding an user to a group',403);
        }

        DB::transaction(function() use($permission){
            GroupUser::where('permission_id',$permission->id)->update(['permission_id'=>1]);

            $userIds = GroupUser::where('permission_id',$permission->id)->get()->pluck('user_id')->unique()->values();

            $notification = Notification::create(['message'=>'Group permission '.$permission->name.' has been deleted. All user with this permission now have regular permission','title'=>'Permission Deleted']);

            $userIds->each(function($id) use($permission,$notification){
                $notifiableUser = User::where('id',$id)->first();

                if(isset($notifiableUser)){
                    $notifiableUser->notifications()->attach($notification->id);
                }

                broadcast(new PermissionDeleted($permission, $id,$notification));
            });

            $permission->delete();
        });

        return $this->showInfo($permission);
    }
}
