<?php

namespace App\Http\Controllers\MultiBranch;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchInstallation;
use App\Models\User;
use App\Services\MultiBranch\EnrollmentService;
use App\Services\MultiBranch\Install;
use App\Services\MultiBranch\LocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:View branches|Manage branches', ['only' => ['index', 'show']]);
        $this->middleware('permission:Manage branches', ['except' => ['index', 'show']]);
    }

    public function index()
    {
        $pagetitle = 'Branches';
        $branches = Branch::with(['activeInstallation', 'manager:id,first_name,last_name'])->orderBy('number')->get();

        $counts = [];
        if (Schema::hasTable('orders')) {
            $counts = DB::table('orders')->where('order_date', '>=', today())->where('status', '!=', 'cancelled')
                ->selectRaw('branch_id, COUNT(*) c, SUM(total_amount) t')->groupBy('branch_id')->get()->keyBy('branch_id');
        }
        $staff = DB::table('users')->selectRaw('branch_id, COUNT(*) c')->groupBy('branch_id')->pluck('c', 'branch_id');
        $managers = User::whereHas('roles')->orderBy('first_name')->get(['id', 'first_name', 'last_name']);
        $install = Install::current();

        return view('multibranch.branches', compact('pagetitle', 'branches', 'counts', 'staff', 'managers', 'install'));
    }

    public function show(Branch $branch)
    {
        $pagetitle = $branch->name;
        $branch->load(['installations' => fn ($q) => $q->latest(), 'manager']);
        $users = User::with('roles:id,name')->where('branch_id', $branch->id)
            ->orWhereIn('id', DB::table('branch_user')->where('branch_id', $branch->id)->select('user_id'))
            ->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'email', 'branch_id', 'is_active']);
        $allUsers = User::whereHas('roles')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'email', 'branch_id']);
        $install = Install::current();

        return view('multibranch.branch-show', compact('pagetitle', 'branch', 'users', 'allUsers', 'install'));
    }

    protected function rules(?Branch $branch = null): array
    {
        return [
            'code' => ['required', 'alpha_dash', 'max:10', Rule::unique('branches', 'code')->ignore($branch?->id)],
            'number' => ['required', 'integer', 'between:1,99', Rule::unique('branches', 'number')->ignore($branch?->id)],
            'name' => 'required|string|max:255',
            'type' => 'required|in:store,warehouse',
            'status' => 'nullable|in:active,suspended,closed',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email',
            'manager_user_id' => 'nullable|exists:users,id',
            'receipt_header' => 'nullable|string|max:255',
            'receipt_footer' => 'nullable|string|max:255',
        ];
    }

    public function store(Request $request)
    {
        abort_unless(Install::isCentral(), 403, 'Branches are created at headquarters.');
        $data = $request->validate($this->rules());
        $data['code'] = strtoupper($data['code']);
        $branch = Branch::create($data);
        app(LocationService::class)->ensureFor($branch);

        return redirect()->route('mb.branches.show', $branch)->with('success', "Branch {$branch->code} created. Issue an enrollment code to connect its server.");
    }

    public function update(Request $request, Branch $branch)
    {
        abort_unless(Install::isCentral() || $branch->id === Install::branchId(), 403);
        $data = $request->validate($this->rules($branch));
        $data['code'] = strtoupper($data['code']);
        if (!Install::isCentral()) {
            // Identity fields belong to HQ.
            unset($data['code'], $data['number'], $data['type'], $data['status']);
        }
        $branch->update($data);
        return back()->with('success', 'Branch saved.');
    }

    public function issueCode(Branch $branch, EnrollmentService $service)
    {
        abort_unless(Install::isCentral(), 403);
        if ($branch->isWarehouse() && $branch->number === 0) {
            return response()->json(['success' => false, 'message' => 'The head office warehouse runs on this server.'], 422);
        }
        $code = $service->issueCode($branch);
        return response()->json([
            'success' => true,
            'code' => $code,
            'expires' => now()->addHours((int) config('vitemart.enrollment_code_ttl_hours', 24))->format('d M Y, h:i A'),
            'command' => 'php artisan vitemart:install --mode=branch --central-url=' . url('/') . ' --branch-code=' . $branch->code . ' --code=' . $code,
        ]);
    }

    public function revoke(BranchInstallation $installation, EnrollmentService $service)
    {
        abort_unless(Install::isCentral(), 403);
        $service->revoke($installation);
        return back()->with('success', 'Server credentials revoked. It can no longer sync until enrolled again.');
    }

    /** Give a user access to this branch (or make it their home branch). */
    public function userAccess(Request $request, Branch $branch)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'action' => 'required|in:home,add,remove',
        ]);
        $user = User::findOrFail($data['user_id']);
        match ($data['action']) {
            'home' => $user->forceFill(['branch_id' => $branch->id])->save(),
            'add' => $user->branches()->syncWithoutDetaching([$branch->id]),
            'remove' => $user->branches()->detach($branch->id),
        };
        if ($data['action'] === 'remove' && $user->branch_id === $branch->id) {
            return back()->with('error', "{$user->first_name}'s home branch is {$branch->code}; move them to another home branch instead.");
        }
        \App\Services\AuditLogger::log('updated', "Branch access for {$user->first_name}: {$data['action']} {$branch->code}", 'security', $user);
        \App\Services\Sync\Feed::mark('user', $user->uid);
        return back()->with('success', 'Access updated.');
    }
}
