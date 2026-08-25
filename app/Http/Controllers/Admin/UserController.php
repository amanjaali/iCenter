<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\User;
use App\Providers\AuthServiceProvider;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/** Scope of work §9.2 — user management, and §9.3 — permission changes logged. */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('manage-users');

        return view('admin.users.index', [
            'users' => User::with('partner')
                ->when($request->string('role')->toString(), fn ($q, $r) => $q->where('role', $r))
                ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(
                    fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")
                ))
                ->orderBy('role')->orderBy('name')
                ->paginate(30)->withQueryString(),
            'roles' => UserRole::cases(),
            'abilities' => AuthServiceProvider::abilityMatrix(),
        ]);
    }

    public function create()
    {
        $this->authorize('manage-users');

        return view('admin.users.form', [
            'user' => new User(['role' => UserRole::Partner, 'is_active' => true]),
            'partners' => Partner::orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-users');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role' => ['required', 'string', 'in:'.implode(',', array_column(UserRole::cases(), 'value'))],
            'partner_id' => ['nullable', 'exists:partners,id'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = User::create($data);

        return redirect()->route('users.index')
            ->with('success', "{$user->name} can now sign in as {$user->role->label()}.");
    }

    public function edit(User $user)
    {
        $this->authorize('manage-users');

        return view('admin.users.form', [
            'user' => $user,
            'partners' => Partner::orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('manage-users');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', "unique:users,email,{$user->id}"],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'role' => ['required', 'string', 'in:'.implode(',', array_column(UserRole::cases(), 'value'))],
            'partner_id' => ['nullable', 'exists:partners,id'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // An administrator who demotes themselves locks everyone out of user
        // management if they were the last one.
        if ($user->id === Auth::id() && $data['role'] !== UserRole::Administrator->value
            && User::where('role', UserRole::Administrator->value)->where('is_active', true)->count() <= 1) {
            return back()->withInput()->with('error', 'You are the only active administrator. Appoint another before changing your own role.');
        }

        $oldRole = $user->role;

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        // §9.3 — permission changes are logged, not merely applied.
        if ($oldRole !== $user->role) {
            app(AuditLogger::class)->record(
                'role_changed',
                $user,
                oldValues: ['role' => $oldRole->value],
                newValues: ['role' => $user->role->value],
                description: "Changed {$user->name}'s role from {$oldRole->label()} to {$user->role->label()}",
            );
        }

        return redirect()->route('users.index')->with('success', "{$user->name} has been updated.");
    }

    public function toggle(User $user)
    {
        $this->authorize('manage-users');

        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        app(AuditLogger::class)->record(
            $user->is_active ? 'user_activated' : 'user_deactivated',
            $user,
            description: ($user->is_active ? 'Activated ' : 'Deactivated ').$user->name,
        );

        return back()->with('success', "{$user->name} has been "
            .($user->is_active ? 'reactivated.' : 'deactivated and can no longer sign in.'));
    }
}
