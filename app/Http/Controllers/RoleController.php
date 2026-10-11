<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\JsonResponse;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:View role|Create role|Update role|Delete role|Add user-role|Update user-role|Remove user-role', ['only' => ['index', 'show']]);
        $this->middleware('permission:Create role', ['only' => ['create', 'store']]);
        $this->middleware('permission:Update role', ['only' => ['edit', 'update']]);
        $this->middleware('permission:Delete role', ['only' => ['destroy']]);
        $this->middleware('permission:Remove user-role|Delete role', ['only' => ['removeuserrole', 'bulkRemoveUsers']]);
        $this->middleware('permission:Update user-role', ['only' => ['adduser', 'updateuserrole']]);
    }

    public function index(Request $request): View
    {
        $pagetitle = "Role Management";
        $roles = Role::with('permissions:id,name,title')->orderBy('name')->get();
        $permission = Permission::get();
        // Users per role in one query (instead of one query per card)
        $roleUserCounts = DB::table('model_has_roles')
            ->select('role_id', DB::raw('COUNT(*) as total'))
            ->groupBy('role_id')
            ->pluck('total', 'role_id');

        $perm_title = Permission::pluck('title')->implode(',');
        $ex = explode(',', $perm_title);

        return view('roles.index', compact('roles', 'permission', 'roleUserCounts'))
            ->with('perm_title', $ex)
            ->with('pagetitle', $pagetitle);
    }

    public function create(): View
    {
        $permission = Permission::get();
        return view('roles.create', compact('permission'));
    }

    public function store(Request $request): RedirectResponse
    {
        $pagetitle = "Role Management";

        $request->validate([
            'name'       => 'required|unique:roles,name',
            'permission' => 'required|array',
            'permission.*' => 'exists:permissions,id',
            'title'      => 'nullable|string',
            'badge'      => 'nullable|string',
        ]);

        $role = Role::create($request->only(['name', 'title', 'badge']));
        $permissions = Permission::whereIn('id', $request->permission)->pluck('name')->toArray();
        $role->syncPermissions($permissions);

        return redirect()->route('roles.index')
            ->with('success', 'Role created successfully')
            ->with('pagetitle', $pagetitle);
    }

    public function show($id)
    {
        $pagetitle = "Role Management";
        $role = Role::with('permissions')->findOrFail($id);

        // users.name doesn't exist (first_name + last_name) – the User accessor builds it.
        // The page searches and pages this list in the browser.
        $usersWithRole = User::whereHas('roles', fn ($q) => $q->where('roles.id', $id))
            ->with('roles')
            ->orderBy('first_name')->orderBy('last_name')
            ->get();

        $userRoleCount = $usersWithRole->count();

        // People who can still be given this role (for the "Add Users" window)
        $usersNotInRole = User::whereDoesntHave('roles', fn ($q) => $q->where('roles.id', $id))
            ->with('roles')
            ->orderBy('first_name')->orderBy('last_name')
            ->get();

        $rolePermissions  = $role->permissions->sortBy(fn ($p) => ($p->title ?: 'Other') . '|' . $p->name)->values();
        $rolePermissions2 = $role->permissions->pluck('id')->toArray();

        $permission = Permission::get();
        $perm_title = Permission::pluck('title')->implode(',');
        $ex = explode(',', $perm_title);

        Session::put('role_url', request()->fullUrl());

        return view('roles.show', compact(
            'role',
            'usersWithRole',
            'usersNotInRole',
            'userRoleCount',
            'rolePermissions',
            'rolePermissions2',
            'pagetitle'
        ))->with('perm_title', $ex);
    }

    public function edit($id): View
    {
        $pagetitle = "Role Management";
        $role = Role::findOrFail($id);
        $rolePermissions = $role->permissions->pluck('id')->toArray();
        $permission = Permission::get();

        $perm_title = Permission::pluck('title')->implode(',');
        $ex = explode(',', $perm_title);

        return view('roles.edit', compact('role', 'permission', 'rolePermissions'))
            ->with('perm_title', $ex)
            ->with('pagetitle', $pagetitle);
    }

    public function update(Request $request, $id): RedirectResponse
    {
        $pagetitle = "Role Management";

        $request->validate([
            'name'       => 'required|unique:roles,name,' . $id,
            'permission' => 'required|array',
            'permission.*' => 'exists:permissions,id',
            'badge'      => 'nullable|string',
        ]);

        $role = Role::findOrFail($id);
        $role->update($request->only(['name', 'badge']));

        $permissions = Permission::whereIn('id', $request->permission)->pluck('name')->toArray();
        $role->syncPermissions($permissions);

        return redirect(session('role_url') ?? route('roles.index'))
            ->with('success', 'Role updated successfully')
            ->with('pagetitle', $pagetitle);
    }

    public function adduser($id): View
    {
        $pagetitle = "Role Management";
        $role = Role::findOrFail($id);

        $users = User::whereDoesntHave('roles', fn ($q) => $q->where('roles.id', $role->id))
            ->with('roles')
            ->orderBy('first_name')->orderBy('last_name')
            ->get();

        return view('roles.adduser', compact('role', 'users', 'pagetitle'));
    }

    public function updateuserrole(Request $request): RedirectResponse
    {
        $pagetitle = "Role Management";

        $request->validate([
            'users'   => 'required|array',
            'users.*' => 'exists:users,id',
            'roleid'  => 'required|exists:roles,id',
        ]);

        $role = Role::findOrFail($request->roleid);

        foreach ($request->users as $userId) {
            User::findOrFail($userId)->assignRole($role->name);
        }

        return redirect()->route('roles.show', $role->id)
            ->with('success', 'Users added to role successfully')
            ->with('pagetitle', $pagetitle);
    }

    public function removeuserrole($userid, $roleid): JsonResponse
    {
        try {
            $user = User::findOrFail($userid);
            $role = Role::findOrFail($roleid);
            $user->removeRole($role->name);

            return response()->json(['success' => true, 'message' => 'User role removed successfully']);
        } catch (\Exception $e) {
            \Log::error("Remove user role failed", [
                'user_id' => $userid,
                'role_id' => $roleid,
                'error'   => $e->getMessage()
            ]);

            return response()->json(['message' => 'Error removing user role'], 500);
        }
    }

    /**
     * Remove several users from a role at once (role details page).
     */
    public function bulkRemoveUsers(Request $request)
    {
        $request->validate([
            'role_id'          => 'required|exists:roles,id',
            'selected_users'   => 'required|array',
            'selected_users.*' => 'exists:users,id',
        ]);

        try {
            $role = Role::findOrFail($request->role_id);
            $removed = [];

            foreach (User::whereIn('id', $request->selected_users)->get() as $user) {
                if ($user->hasRole($role->name)) {
                    $user->removeRole($role);
                    $removed[] = $user->name;
                }
            }

            if ($removed) {
                return response()->json([
                    'success'       => true,
                    'message'       => 'Removed ' . count($removed) . " user(s) from the {$role->name} role.",
                    'removed_count' => count($removed),
                    'removed_users' => $removed,
                ]);
            }

            return response()->json(['success' => false, 'message' => 'No users were removed. They may not have had this role.']);
        } catch (\Throwable $e) {
            Log::error('Bulk remove users from role failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Could not remove the users.'], 500);
        }
    }

    public function destroy($id): RedirectResponse
    {
        $pagetitle = "Role Management";
        Role::findOrFail($id)->delete();

        return redirect()->route('roles.index')
            ->with('success', 'Role deleted successfully')
            ->with('pagetitle', $pagetitle);
    }
}