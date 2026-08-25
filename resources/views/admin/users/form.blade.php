@extends('layouts.app')
@section('title', $user->exists ? 'Edit '.$user->name : 'New user')

@section('content')
    <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}"
          class="mx-auto flex max-w-2xl flex-col gap-4" x-data="{ role: '{{ old('role', $user->role?->value ?? 'partner') }}' }">
        @csrf
        @if ($user->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-head"><div class="card-title">Account</div></div>
            <div class="grid gap-4 p-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="name">Name</label>
                    <input id="name" name="name" class="input @error('name') input-invalid @enderror"
                           value="{{ old('name', $user->name) }}" required>
                    @error('name')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="label" for="email">Email</label>
                    <input id="email" name="email" type="email" class="input @error('email') input-invalid @enderror"
                           value="{{ old('email', $user->email) }}" required>
                    @error('email')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="label" for="job_title">Job title</label>
                    <input id="job_title" name="job_title" class="input" value="{{ old('job_title', $user->job_title) }}">
                </div>
                <div>
                    <label class="label" for="phone">Phone</label>
                    <input id="phone" name="phone" class="input" value="{{ old('phone', $user->phone) }}">
                </div>
                <div>
                    <label class="label" for="password">{{ $user->exists ? 'New password' : 'Password' }}</label>
                    <input id="password" name="password" type="password" class="input @error('password') input-invalid @enderror"
                           autocomplete="new-password" @required(! $user->exists)>
                    @error('password')<div class="field-error">{{ $message }}</div>@enderror
                    @if ($user->exists)<div class="hint">Leave empty to keep the current password.</div>@endif
                </div>
                <div>
                    <label class="label" for="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" class="input"
                           autocomplete="new-password" @required(! $user->exists)>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title">Role</div>
                    <div class="card-sub">Scope of work §9.2. A role change is recorded in the audit trail.</div>
                </div>
            </div>
            <div class="flex flex-col gap-3 p-5">
                @foreach (\App\Enums\UserRole::cases() as $option)
                    <label class="flex cursor-pointer items-start gap-3 rounded-md border p-3.5 transition-colors"
                           :class="role === '{{ $option->value }}' ? 'border-signal bg-[#E6F8FD]/40' : 'border-line hover:border-line-strong'">
                        <input type="radio" name="role" value="{{ $option->value }}" x-model="role" class="mt-0.5 size-3.5 accent-navy">
                        <span>
                            <span class="block text-xs font-medium">{{ $option->label() }}</span>
                            <span class="mt-0.5 block text-[11px] leading-relaxed text-muted">{{ $option->description() }}</span>
                        </span>
                    </label>
                @endforeach

                <div x-show="role === 'partner'" x-cloak class="mt-1 border-t border-rule pt-4">
                    <label class="label" for="partner_id">Linked partner</label>
                    <select id="partner_id" name="partner_id" class="select">
                        <option value="">Not linked</option>
                        @foreach ($partners as $partner)
                            <option value="{{ $partner->id }}" @selected(old('partner_id', $user->partner_id) == $partner->id)>
                                {{ $partner->name }} ({{ rtrim(rtrim(number_format($partner->ownership_percent, 2), '0'), '.') }}%)
                            </option>
                        @endforeach
                    </select>
                    <div class="hint">Links this login to a partner record for the distribution reports.</div>
                </div>

                <label class="mt-1 flex cursor-pointer items-center gap-2 border-t border-rule pt-4">
                    <input type="hidden" name="is_active" value="0">
                    <input name="is_active" type="checkbox" value="1" class="size-3.5 accent-navy"
                           @checked(old('is_active', $user->is_active ?? true))>
                    <span class="text-xs text-body">Active — this account may sign in</span>
                </label>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('users.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">{{ $user->exists ? 'Save changes' : 'Create user' }}</button>
        </div>
    </form>
@endsection
