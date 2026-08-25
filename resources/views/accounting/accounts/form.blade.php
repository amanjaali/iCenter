@extends('layouts.app')
@section('title', $account->exists ? 'Edit '.$account->code : 'New account')

@section('content')
    <form method="POST" action="{{ $account->exists ? route('accounts.update', $account) : route('accounts.store') }}"
          class="mx-auto flex max-w-2xl flex-col gap-4">
        @csrf
        @if ($account->exists) @method('PUT') @endif

        @if ($account->is_system)
            <div class="flex items-start gap-2.5 rounded-md border border-caution/25 bg-caution-soft px-4 py-3 text-xs text-caution">
                <x-icon name="alert" class="mt-px size-4 shrink-0"/>
                <div class="leading-relaxed">
                    This is a system account: the posting engine resolves it by its code, so the code cannot
                    be changed. Renumbering it would send automatic journals somewhere else.
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title">Account</div>
                    <div class="card-sub">
                        Scope of work §6 — sub-accounts may be added inside a class, but the class
                        numbering (1000 to 9000) is fixed.
                    </div>
                </div>
            </div>
            <div class="grid gap-4 p-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="code">Code</label>
                    <input id="code" name="code" class="input money @error('code') input-invalid @enderror"
                           value="{{ old('code', $account->code) }}" required @disabled($account->is_system)>
                    @error('code')<div class="field-error">{{ $message }}</div>@enderror
                    <div class="hint">Four or more digits. The first digit sets the class.</div>
                </div>
                <div>
                    <label class="label" for="normal_balance">Normal balance</label>
                    <select id="normal_balance" name="normal_balance" class="select">
                        <option value="">Derive from the class</option>
                        <option value="debit" @selected(old('normal_balance', $account->normal_balance?->value) === 'debit')>Debit</option>
                        <option value="credit" @selected(old('normal_balance', $account->normal_balance?->value) === 'credit')>Credit</option>
                    </select>
                    <div class="hint">Set explicitly only for contra accounts.</div>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="name">Name</label>
                    <input id="name" name="name" class="input @error('name') input-invalid @enderror"
                           value="{{ old('name', $account->name) }}" required>
                    @error('name')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="name_ar">Name in Arabic</label>
                    <input id="name_ar" name="name_ar" class="input" dir="rtl" value="{{ old('name_ar', $account->name_ar) }}">
                </div>
                <div>
                    <label class="label" for="subtype">Classification</label>
                    <select id="subtype" name="subtype" class="select">
                        <option value="">—</option>
                        @foreach (\App\Enums\AccountSubtype::cases() as $subtype)
                            <option value="{{ $subtype->value }}" @selected(old('subtype', $account->subtype?->value) === $subtype->value)>
                                {{ $subtype->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="default_nature">Default nature</label>
                    <select id="default_nature" name="default_nature" class="select">
                        <option value="">—</option>
                        <option value="fixed" @selected(old('default_nature', $account->default_nature) === 'fixed')>Fixed</option>
                        <option value="variable" @selected(old('default_nature', $account->default_nature) === 'variable')>Variable</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="description">Description</label>
                    <textarea id="description" name="description" rows="2" class="textarea">{{ old('description', $account->description) }}</textarea>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title">Posting rules</div>
                    <div class="card-sub">
                        Scope of work §7 — an expense account should demand a cost centre and a project,
                        so an entry cannot be posted without them.
                    </div>
                </div>
            </div>
            <div class="flex flex-col gap-3 p-5">
                @foreach ([
                    'is_postable' => ['Allow posting to this account', 'Turn off for a heading that only groups its children.'],
                    'requires_department' => ['Require a cost centre', 'The entry is refused without one.'],
                    'requires_project' => ['Require a project', 'The entry is refused without one.'],
                    'is_cash_equivalent' => ['Treat as cash or bank', 'Appears in the cash flow statement and in “pay from” pickers.'],
                    'is_active' => ['Active', 'Inactive accounts stay on past entries but cannot be chosen for new ones.'],
                ] as $field => [$label, $hint])
                    <label class="flex cursor-pointer items-start gap-3">
                        <input type="hidden" name="{{ $field }}" value="0">
                        <input type="checkbox" name="{{ $field }}" value="1" class="mt-0.5 size-3.5 accent-navy"
                               @checked(old($field, $account->{$field} ?? in_array($field, ['is_postable', 'is_active'], true)))>
                        <span>
                            <span class="block text-xs text-body">{{ $label }}</span>
                            <span class="block text-[11px] text-faint">{{ $hint }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('accounts.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">{{ $account->exists ? 'Save changes' : 'Add account' }}</button>
        </div>
    </form>
@endsection
