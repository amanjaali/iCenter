@extends('layouts.app')
@section('title', $company->exists ? 'Edit '.$company->name : 'New bus company')

@section('content')
    <form method="POST" action="{{ $company->exists ? route('bus-companies.update', $company) : route('bus-companies.store') }}"
          class="mx-auto flex max-w-3xl flex-col gap-4">
        @csrf
        @if ($company->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title">Company details</div>
                    <div class="card-sub">The top of the registry chain that billing is generated from.</div>
                </div>
            </div>

            <div class="grid gap-4 p-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="code">Code</label>
                    <input id="code" name="code" class="input @error('code') input-invalid @enderror"
                           value="{{ old('code', $company->code) }}" required placeholder="BC-001">
                    @error('code')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="label" for="status">Status</label>
                    <select id="status" name="status" class="select">
                        @foreach (['active' => 'Active', 'suspended' => 'Suspended', 'closed' => 'Closed'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $company->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="name">Name</label>
                    <input id="name" name="name" class="input @error('name') input-invalid @enderror"
                           value="{{ old('name', $company->name) }}" required>
                    @error('name')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="name_ar">Name in Arabic</label>
                    <input id="name_ar" name="name_ar" class="input" dir="rtl"
                           value="{{ old('name_ar', $company->name_ar) }}">
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><div class="card-title">Contact</div></div>
            <div class="grid gap-4 p-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="contact_name">Contact name</label>
                    <input id="contact_name" name="contact_name" class="input" value="{{ old('contact_name', $company->contact_name) }}">
                </div>
                <div>
                    <label class="label" for="phone">Phone</label>
                    <input id="phone" name="phone" class="input" value="{{ old('phone', $company->phone) }}">
                </div>
                <div>
                    <label class="label" for="email">Email</label>
                    <input id="email" name="email" type="email" class="input" value="{{ old('email', $company->email) }}">
                </div>
                <div>
                    <label class="label" for="tax_number">Tax number</label>
                    <input id="tax_number" name="tax_number" class="input" value="{{ old('tax_number', $company->tax_number) }}">
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="address">Address</label>
                    <input id="address" name="address" class="input" value="{{ old('address', $company->address) }}">
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title">Contract and terms</div>
                    <div class="card-sub">Payment terms set the due date on every invoice raised for this company.</div>
                </div>
            </div>
            <div class="grid gap-4 p-5 sm:grid-cols-3">
                <div>
                    <label class="label" for="payment_terms_days">Payment terms (days)</label>
                    <input id="payment_terms_days" name="payment_terms_days" type="number" min="0" max="180"
                           class="input input-num" value="{{ old('payment_terms_days', $company->payment_terms_days ?? 15) }}" required>
                </div>
                <div>
                    <label class="label" for="contract_start">Contract start</label>
                    <input id="contract_start" name="contract_start" type="date" class="input"
                           value="{{ old('contract_start', $company->contract_start?->toDateString()) }}">
                </div>
                <div>
                    <label class="label" for="contract_end">Contract end</label>
                    <input id="contract_end" name="contract_end" type="date" class="input"
                           value="{{ old('contract_end', $company->contract_end?->toDateString()) }}">
                </div>
                <div class="sm:col-span-3">
                    <label class="label" for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3" class="textarea">{{ old('notes', $company->notes) }}</textarea>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2">
            <a href="{{ $company->exists ? route('bus-companies.show', $company) : route('bus-companies.index') }}"
               class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                {{ $company->exists ? 'Save changes' : 'Register company' }}
            </button>
        </div>
    </form>
@endsection
