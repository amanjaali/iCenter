@extends('layouts.app')
@section('title', $rate->exists ? 'Edit rate' : 'Add a rate')

@section('content')
    <form method="POST" action="{{ $rate->exists ? route('rates.update', $rate) : route('rates.store') }}"
          class="mx-auto flex max-w-2xl flex-col gap-4">
        @csrf
        @if ($rate->exists) @method('PUT') @endif

        @if ($rate->exists && ($usedBy ?? 0) > 0)
            <div class="flex items-start gap-2.5 rounded-md border border-caution/25 bg-caution-soft px-4 py-3 text-xs text-caution">
                <x-icon name="alert" class="mt-px size-4 shrink-0"/>
                <div class="leading-relaxed">
                    This rate has already been used on {{ $usedBy }} invoice {{ str('line')->plural($usedBy) }}.
                    Editing it does not restate those invoices — they keep the amount they were raised at.
                    To change the price going forward, add a new rate with a later effective date instead.
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title">Rate</div>
                    <div class="card-sub">Per student, per month. Effective dated so past months keep their own rate.</div>
                </div>
            </div>
            <div class="grid gap-4 p-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="academic_year_id">Academic year</label>
                    <select id="academic_year_id" name="academic_year_id" class="select" required>
                        @foreach ($years as $year)
                            <option value="{{ $year->id }}" @selected(old('academic_year_id', $rate->academic_year_id) == $year->id)>
                                {{ $year->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="amount">Amount ({{ config('allva.currency.code') }})</label>
                    <input id="amount" name="amount" type="number" step="1" min="0" required
                           class="input input-num @error('amount') input-invalid @enderror"
                           value="{{ old('amount', $rate->amount ?? 4000) }}">
                    @error('amount')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="bus_company_id">Applies to</label>
                    <select id="bus_company_id" name="bus_company_id" class="select">
                        <option value="">All bus companies (standard rate)</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}"
                                @selected(old('bus_company_id', $rate->bus_company_id ?? request('bus_company_id')) == $company->id)>
                                {{ $company->name }} — special agreement
                            </option>
                        @endforeach
                    </select>
                    <div class="hint">A company-specific rate always beats the standard rate for that company.</div>
                </div>
                <div>
                    <label class="label" for="effective_from">Effective from</label>
                    <input id="effective_from" name="effective_from" type="date" class="input" required
                           value="{{ old('effective_from', $rate->effective_from?->toDateString()) }}">
                </div>
                <div>
                    <label class="label" for="effective_to">Effective to</label>
                    <input id="effective_to" name="effective_to" type="date" class="input"
                           value="{{ old('effective_to', $rate->effective_to?->toDateString()) }}">
                    <div class="hint">Leave empty for an open-ended rate.</div>
                </div>
                <div class="sm:col-span-2 flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0">
                    <input id="is_active" name="is_active" type="checkbox" value="1" class="size-3.5 accent-navy"
                           @checked(old('is_active', $rate->is_active ?? true))>
                    <label for="is_active" class="text-xs text-body">Active — apply this rate when billing</label>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="2" class="textarea">{{ old('notes', $rate->notes) }}</textarea>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title">Reason for this change</div>
                    <div class="card-sub">
                        Scope of work §4.2 — every rate change is logged with the user who made it,
                        the date, and the reason. This field is required.
                    </div>
                </div>
            </div>
            <div class="p-5">
                <textarea id="reason" name="reason" rows="2" required
                          class="textarea @error('reason') input-invalid @enderror"
                          placeholder="For example: price increase agreed for the 2027–2028 academic year">{{ old('reason') }}</textarea>
                @error('reason')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('rates.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">{{ $rate->exists ? 'Save and log the change' : 'Add rate' }}</button>
        </div>
    </form>
@endsection
