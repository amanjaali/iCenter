@extends('layouts.app')
@section('title', $supplier->exists ? 'Edit '.$supplier->name : 'New supplier')

@section('content')
    <form method="POST" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}"
          class="mx-auto flex max-w-2xl flex-col gap-4">
        @csrf
        @if ($supplier->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-head"><div class="card-title">Supplier</div></div>
            <div class="grid gap-4 p-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="code">Code</label>
                    <input id="code" name="code" class="input @error('code') input-invalid @enderror"
                           value="{{ old('code', $supplier->code) }}" required placeholder="SUP-001">
                    @error('code')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="label" for="payment_terms_days">Payment terms (days)</label>
                    <input id="payment_terms_days" name="payment_terms_days" type="number" min="0" max="180"
                           class="input input-num" required value="{{ old('payment_terms_days', $supplier->payment_terms_days ?? 0) }}">
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="name">Name</label>
                    <input id="name" name="name" class="input @error('name') input-invalid @enderror"
                           value="{{ old('name', $supplier->name) }}" required>
                    @error('name')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="label" for="contact_name">Contact</label>
                    <input id="contact_name" name="contact_name" class="input" value="{{ old('contact_name', $supplier->contact_name) }}">
                </div>
                <div>
                    <label class="label" for="phone">Phone</label>
                    <input id="phone" name="phone" class="input" value="{{ old('phone', $supplier->phone) }}">
                </div>
                <div>
                    <label class="label" for="email">Email</label>
                    <input id="email" name="email" type="email" class="input" value="{{ old('email', $supplier->email) }}">
                </div>
                <div>
                    <label class="label" for="tax_number">Tax number</label>
                    <input id="tax_number" name="tax_number" class="input" value="{{ old('tax_number', $supplier->tax_number) }}">
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="address">Address</label>
                    <input id="address" name="address" class="input" value="{{ old('address', $supplier->address) }}">
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="2" class="textarea">{{ old('notes', $supplier->notes) }}</textarea>
                </div>
                <label class="flex cursor-pointer items-center gap-2 sm:col-span-2">
                    <input type="hidden" name="is_active" value="0">
                    <input name="is_active" type="checkbox" value="1" class="size-3.5 accent-navy"
                           @checked(old('is_active', $supplier->is_active ?? true))>
                    <span class="text-xs text-body">Active</span>
                </label>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('suppliers.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">{{ $supplier->exists ? 'Save changes' : 'Add supplier' }}</button>
        </div>
    </form>
@endsection
