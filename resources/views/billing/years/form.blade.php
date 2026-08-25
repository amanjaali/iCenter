@extends('layouts.app')
@section('title', $year->exists ? 'Edit '.$year->name : 'New academic year')

@section('content')
    <form method="POST" action="{{ $year->exists ? route('academic-years.update', $year) : route('academic-years.store') }}"
          class="mx-auto flex max-w-2xl flex-col gap-4"
          x-data="{ mode: '{{ old('billing_mode', $year->billing_mode?->value ?? 'continue') }}' }">
        @csrf
        @if ($year->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-head"><div class="card-title">Academic year</div></div>
            <div class="grid gap-4 p-5 sm:grid-cols-3">
                <div>
                    <label class="label" for="name">Name</label>
                    <input id="name" name="name" class="input @error('name') input-invalid @enderror"
                           value="{{ old('name', $year->name) }}" required placeholder="2026-2027">
                    @error('name')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="label" for="start_date">Starts</label>
                    <input id="start_date" name="start_date" type="date" class="input" required
                           value="{{ old('start_date', $year->start_date instanceof \Illuminate\Support\Carbon ? $year->start_date->toDateString() : $year->start_date) }}">
                </div>
                <div>
                    <label class="label" for="end_date">Ends</label>
                    <input id="end_date" name="end_date" type="date" class="input" required
                           value="{{ old('end_date', $year->end_date instanceof \Illuminate\Support\Carbon ? $year->end_date->toDateString() : $year->end_date) }}">
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title">Holiday and summer billing</div>
                    <div class="card-sub">Scope of work §10.3 — decided per year, not globally.</div>
                </div>
            </div>
            <div class="flex flex-col gap-3 p-5">
                @foreach (\App\Enums\BillingMode::cases() as $option)
                    <label class="flex cursor-pointer items-start gap-3 rounded-md border p-3.5 transition-colors"
                           :class="mode === '{{ $option->value }}' ? 'border-signal bg-[#E6F8FD]/40' : 'border-line hover:border-line-strong'">
                        <input type="radio" name="billing_mode" value="{{ $option->value }}" x-model="mode"
                               class="mt-0.5 size-3.5 accent-navy">
                        <span>
                            <span class="block text-xs font-medium">{{ $option->label() }}</span>
                            <span class="mt-0.5 block text-[11px] leading-relaxed text-muted">
                                {{ $option === \App\Enums\BillingMode::Continue
                                    ? 'Every month inside the year is invoiced, including the summer.'
                                    : 'Only the months ticked below are invoiced; the rest are skipped entirely.' }}
                            </span>
                        </span>
                    </label>
                @endforeach

                <div x-show="mode === 'pause'" x-cloak class="mt-1 border-t border-rule pt-4">
                    <div class="label">Billable months</div>
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
                        @foreach (range(1, 12) as $month)
                            @php $selected = in_array($month, old('billable_months', $year->billable_months ?? []), false); @endphp
                            <label class="flex cursor-pointer items-center gap-2 rounded-md border border-line px-2.5 py-1.5 text-xs">
                                <input type="checkbox" name="billable_months[]" value="{{ $month }}"
                                       class="size-3.5 accent-navy" @checked($selected)>
                                {{ \Illuminate\Support\Carbon::create(2000, $month, 1)->format('M') }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-pad">
            <label class="flex cursor-pointer items-center gap-2">
                <input type="hidden" name="is_active" value="0">
                <input name="is_active" type="checkbox" value="1" class="size-3.5 accent-navy"
                       @checked(old('is_active', $year->is_active))>
                <span class="text-xs text-body">Make this the current academic year</span>
            </label>
            <div class="mt-3">
                <label class="label" for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="2" class="textarea">{{ old('notes', $year->notes) }}</textarea>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('academic-years.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">{{ $year->exists ? 'Save changes' : 'Create year' }}</button>
        </div>
    </form>
@endsection
