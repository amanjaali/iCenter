@extends('layouts.app')
@section('title', 'Users')
@section('subtitle', 'SCOPE OF WORK §9.2 · FOUR ROLES')

@section('actions')
    <a href="{{ route('users.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-3.5"/> Add user</a>
@endsection

@section('content')
    <div class="mb-4 grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($roles as $role)
            <div class="card p-4">
                <div class="text-xs font-medium">{{ $role->label() }}</div>
                <p class="mt-1.5 text-[11px] leading-relaxed text-muted">{{ $role->description() }}</p>
            </div>
        @endforeach
    </div>

    <form method="GET" class="card mb-4 flex flex-wrap items-end gap-3 p-3.5">
        <div class="min-w-[220px] flex-1">
            <label class="label" for="q">Search</label>
            <input id="q" name="q" class="input" value="{{ request('q') }}" placeholder="Name or email">
        </div>
        <div class="min-w-[180px]">
            <label class="label" for="role">Role</label>
            <select id="role" name="role" class="select">
                <option value="">All roles</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected(request('role') === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-primary"><x-icon name="search" class="size-3.5"/> Search</button>
        <a href="{{ route('users.index') }}" class="btn btn-secondary">Reset</a>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr><th>User</th><th>Email</th><th>Role</th><th>Partner</th><th>Last signed in</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr class="{{ $user->is_active ? '' : 'opacity-55' }}">
                            <td>
                                <div class="flex items-center gap-2.5">
                                    <span class="flex size-[26px] shrink-0 items-center justify-center rounded-full bg-mist
                                                 font-display text-[11px] font-medium text-navy">
                                        {{ $user->initials() }}
                                    </span>
                                    <span>
                                        <span class="font-medium">{{ $user->name }}</span>
                                        @if ($user->job_title)<div class="eyebrow">{{ $user->job_title }}</div>@endif
                                    </span>
                                </div>
                            </td>
                            <td class="text-muted">{{ $user->email }}</td>
                            <td>
                                <x-badge :label="$user->role->label()"
                                         :tone="match($user->role) {
                                            \App\Enums\UserRole::Administrator => 'danger',
                                            \App\Enums\UserRole::Accountant => 'info',
                                            \App\Enums\UserRole::Operations => 'warning',
                                            default => 'neutral',
                                         }"/>
                            </td>
                            <td class="text-muted">{{ $user->partner?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap text-muted">{{ $user->last_login_at?->format('j M Y H:i') ?? 'Never' }}</td>
                            <td><x-badge :tone="$user->is_active ? 'success' : 'neutral'" :label="$user->is_active ? 'Active' : 'Deactivated'"/></td>
                            <td class="text-end">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('users.edit', $user) }}" class="btn btn-ghost btn-sm">
                                        <x-icon name="edit" class="size-3.5"/>
                                    </a>
                                    @if ($user->id !== auth()->id())
                                        <x-confirm-form :action="route('users.toggle', $user)"
                                            :confirm="$user->is_active
                                                ? 'Deactivate '.$user->name.'? They will be signed out at their next request and cannot sign back in.'
                                                : 'Reactivate '.$user->name.'?'"
                                            class="btn btn-ghost btn-sm">
                                            <x-icon :name="$user->is_active ? 'x' : 'check'" class="size-3.5"/>
                                        </x-confirm-form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty title="No users match this view"/></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())<div class="border-t border-rule px-5 py-3">{{ $users->links() }}</div>@endif
    </div>

    <div class="card mt-4 overflow-hidden">
        <div class="card-head">
            <div>
                <div class="card-title">What each role may do</div>
                <div class="card-sub">
                    Derived from the role in one place, so "what may a partner do" has a single answer.
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="table table-compact">
                <thead>
                    <tr>
                        <th>Ability</th>
                        @foreach ($roles as $role)
                            <th class="num">{{ $role->label() }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($abilities as $ability => $allowed)
                        <tr>
                            <td class="text-muted">{{ ucfirst(str_replace('-', ' ', $ability)) }}</td>
                            @foreach ($roles as $role)
                                <td class="num">
                                    @if (in_array($role->value, $allowed, true))
                                        <x-icon name="check" class="ms-auto size-3.5 text-positive"/>
                                    @else
                                        <span class="text-faint">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
