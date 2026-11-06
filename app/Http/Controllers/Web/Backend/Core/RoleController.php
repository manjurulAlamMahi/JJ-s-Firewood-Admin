<?php

namespace App\Http\Controllers\Web\Backend\Core;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Auth;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function index()
    {
        if (Auth::user()->role_id <= 2) {
            $lims_role_all = Role::where('is_active', true)->get();
            return view('backend.layouts.roles.create', compact('lims_role_all'));
        } else {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => [
                'required',
                'max:255',
                Rule::unique('roles')->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ],
        ]);

        $data = $request->all();
        Role::create($data);

        return redirect('role')->with('message', 'Data inserted successfully');
    }

    public function edit($id)
    {
        if (Auth::user()->role_id <= 2) {
            $lims_role_data = Role::find($id);
            return $lims_role_data;
        } else {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => [
                'required',
                'max:255',
                Rule::unique('roles')->ignore($request->role_id)->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ],
        ]);

        $input = $request->all();
        $lims_role_data = Role::findOrFail($id);
        $lims_role_data->update($input);

        return redirect('role')->with('message', 'Data updated successfully');
    }

    public function permission($id)
    {
        if (Auth::user()->role_id <= 2) {
            $lims_role_data = Role::find($id);
            $permissions = Role::findByName($lims_role_data->name, 'web')->permissions;

            $all_permission = [];
            foreach ($permissions as $permission) {
                $all_permission[] = $permission->name;
            }

            if (empty($all_permission)) {
                $all_permission[] = 'dummy text';
            }

            return view('backend.layouts.roles.permission', compact('lims_role_data', 'all_permission'));
        } else {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }
    }

    public function setPermission(Request $request)
    {
        $role = Role::find($request->role_id);
        if (!$role) {
            return redirect()->back()->with('error', 'Role not found!');
        }

        $submittedPermissions = collect($request->except(['_token', 'role_id']))->keys()->toArray();

        $guardName = $role->guard_name ?? 'web';

        foreach ($submittedPermissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => $guardName,
            ]);
        }

        $role->syncPermissions($submittedPermissions);

        cache()->forget('permissions');

        return redirect('role')->with('message', 'Permissions updated successfully');
    }



    public function destroy($id)
    {
        if (!env('USER_VERIFIED')) {
            return redirect()->back()->with('not_permitted', 'This feature is disabled for demo!');
        }

        $lims_role_data = Role::find($id);
        if ($lims_role_data) {
            $lims_role_data->is_active = false;
            $lims_role_data->save();
            return redirect('role')->with('message', 'Data deleted successfully');
        }

        return redirect('role')->with('not_permitted', 'Role not found');
    }
}
