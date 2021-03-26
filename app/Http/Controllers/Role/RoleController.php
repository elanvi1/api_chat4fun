<?php

namespace App\Http\Controllers\Role;

use App\Models\Role;
use App\Models\User;
use App\Models\RoleUser;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiController;

class RoleController extends ApiController
{
    public function index(Request $request){
        $user = $request->user();
       
        $userRole = RoleUser::where([
            ['role_id',1],
            ['user_id',$user->id]
        ])->first();

        if(!isset($userRole)){
            return $this->errorResponse('Only users with admin role can see roles',403);
        }

        $roles = Role::all();

        return $this->showInfo($roles);
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
        
        $user = $request->user;
     
        $userRole = RoleUser::where([
            ['role_id',1],
            ['user_id',$user->id]
        ])->first();

        if(!isset($userRole)){
            return $this->errorResponse('Only users with admin role can add roles',403);
        }

        $data = $request->only(['name']);

        $role = Role::create($data);

        return $this->showInfo($role,201);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Role  $role
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Role $role)
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
            return $this->errorResponse('Only users with admin role can update roles',403);
        }

        $role->name = $request->name;

        if($role->isClean()){
            return response()->json(['error'=>'The values that you mentioned are the same as the previous ones','code'=>422],422);
        }

        $role->save();

        return $this->showInfo($role);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Role  $role
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request,Role $role)
    {
        $user = $request->user();
        $userRole = RoleUser::where([
            ['role_id',1],
            ['user_id',$user->id]
        ])->first();

        if(!isset($userRole)){
            return $this->errorResponse('Only users with admin role can delete roles',403);
        }

        $role->delete();

        return $this->showInfo($role);
    }
}
