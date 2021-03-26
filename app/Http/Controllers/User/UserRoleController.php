<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\ApiController;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\RoleUser;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserRoleController extends ApiController
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        
        $rules = [
            'user_id' => 'required|numeric',
            'role_id' => 'required|numeric',
        ];

        $this->validate($request, $rules);
       
        $user = User::where('id',$request->user_id)->firstOrFail ();
        $role = Role::where('id',$request->role_id)->firstOrFail ();
        $authUser = $request->user();
        $authUserHasAdminRole = $authUser->roles->contains('id',1);
        
        if(!$authUserHasAdminRole){
            return $this->errorResponse('Only users with admin role can give an user a role',403);
        }

        $userHasRequestedRole = $user->roles->contains('id',$request->role_id);

        if($userHasRequestedRole){
            return $this->errorResponse('Specified user already has the specified role',409);
        }

        RoleUser::create(['role_id'=>$role->id,'user_id'=>$user->id]);

        return $this->showMessage('User '.$user->username." was successfully given the role ".$role->name, 201);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request,User $user,Role $role)
    {
        $authUser = $request->user();
        $authUserHasAdminRole = $authUser->roles->contains('id',1);
        
        if(!$authUserHasAdminRole){
            return $this->errorResponse('Only users with admin role can remove a role from a user',403);
        }
        
        $roleUser = RoleUser::where([
            ['user_id',$user->id],
            ['role_id',$role->id]
        ])->firstOrFail();
        
        $roleUser->forceDelete();

        return $this->showMessage("User ".$user->username." no longer has a role ".$role->name);
    }
}
