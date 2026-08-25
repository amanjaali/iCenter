@extends('layouts.app')
@section('title', $partner->exists ? 'Edit '.$partner->name : 'New partner')

@section('content')
    <form method="POST" action="{{ $partner->exists ? route('partners.update', $partner) : route('partners.store') }}"
          class="mx-auto flex max-w-2xl flex-col gap-4">
        @csrf
        @if ($partner->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-head"><div class="card-title">Partner</div></div>
            <div class="grid gap-4 p-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="code">Code</label>
                    <input id="code" name="code" class="input @error('code') input-invalid @enderror"
                           value="{{ old('code', $partner->code) }}" required placeholder="P1">
                    @error('code')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="label" for="sort_order">Order</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" class="input input-num"
                           value="{{ old('sort_order', $partner->sort_order ?? 0) }}">
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="name">Name</label>
                    <input id="name" name="name" class="input @error('name') input-invalid @enderror"
                           value="{{ old('name', $partner->name) }}" required>
                    @error('name')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="name_ar">Name in Arabic</label>
                    <input id="name_ar" name="name_ar" class="input" dir="rtl" value="{{ old('name_ar', $partner->name_ar) }}">
                </div>
                <div>
                    <label class="label" for="email">Email</label>
                    <input id="email" name="email" type="email" class="input" value="{{ old('email', $partner->email) }}">
                </div>
                <div>
                    <label class="label" for="phone">Phone</label>
                    <input id="phone" name="phone" class="input" value="{{ old('phone', $partner->phone) }}">
                </div>
                <div>
                    <label class="label" for="user_id">Login</label>
                    <select id="user_id" name="user_id" class="select">
                        <option value="">No login yet</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(old('user_id', $partner->user_id) == $user->id)>
                                {{ $user->name }} ({{ $user->email }})
                            </option>
                        @endforeach
                    </select>
                    <div class="hint">Scope of work §9.1 — each partner has a read-only login.</div>
                </div>
                <div>
                    <label class="label" for="capital_account_id">Capital account</label>
                    <select id="capital_account_id" name="capital_account_id" class="select">
                        <option value="">—</option>
                        @foreach ($capitalAccounts as $account)
                            <option value="{{ $account->id }}" @selected(old('capital_account_id', $partner->capital_account_id) == $account->id)>
                                {{ $account->displayName() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="joined_on">Joined</label>
                    <input id="joined_on" name="joined_on" type="date" class="input"
                           value="{{ old('joined_on', $partner->joined_on?->toDateString()) }}">
                </div>
                <div>
                    <label class="label" for="left_on">Left</label>
                    <input id="left_on" name="left_on" type="date" class="input"
                           value="{{ old('left_on', $partner->left_on?->toDateString()) }}">
                </div>
                <label class="flex cursor-pointer items-center gap-2 sm:col-span-2">
                    <input type="hidden" name="is_active" value="0">
                    <input name="is_active" type="checkbox" value="1" class="size-3.5 accent-navy"
                           @checked(old('is_active', $partner->is_active ?? true))>
                    <span class="text-xs text-body">Active — included in profit distributions</span>
                </label>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title">Ownership</div>
                    <div class="card-sub">
                        Scope of work §2 — configurable, so a change in shareholding is handled without
                        rebuilding the distribution logic. §9.3 requires the change to be logged with a reason.
                    </div>
                </div>
            </div>
            <div class="grid gap-4 p-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="ownership_percent">Ownership %</label>
                    <input id="ownership_percent" name="ownership_percent" type="number" step="0.0001" min="0" max="100"
                           class="input input-num @error('ownership_percent') input-invalid @enderror" required
                           value="{{ old('ownership_percent', $partner->ownership_percent ?? 20) }}">
                    @error('ownership_percent')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="label" for="effective_date">Effective from</label>
                    <input id="effective_date" name="effective_date" type="date" class="input"
                           value="{{ old('effective_date', now()->toDateString()) }}">
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="reason">Reason for this ownership figure</label>
                    <textarea id="reason" name="reason" rows="2" required
                              class="textarea @error('reason') input-invalid @enderror"
                              placeholder="For example: initial shareholding, or additional capital contributed">{{ old('reason') }}</textarea>
                    @error('reason')<div class="field-error">{{ $message }}</div>@enderror
                    <div class="hint">A posted distribution keeps the percentage used at the time — this does not restate it.</div>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('partners.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">{{ $partner->exists ? 'Save changes' : 'Add partner' }}</button>
        </div>
    </form>
@endsection
