@extends('layouts.app')
@section('title', 'Academic years')
@section('subtitle', 'HOLIDAY AND SUMMER BILLING · SET PER YEAR')

@section('actions')
    @can('manage-rates')
        <a href="{{ route('academic-years.create') }}" class="btn btn-primary">
            <x-icon name="plus" class="size-3.5"/> Add year
        </a>
    @endcan
@endsection

@section('content')
    <div class="mb-4 flex items-start gap-2.5 rounded-md border border-line bg-white px-4 py-3 text-xs text-body">
        <x-icon name="info" class="mt-px size-4 shrink-0 text-signal"/>
        <div class="leading-relaxed">
            Scope of work §10.3 — whether billing pauses over school holidays and the summer, or continues
            through all twelve months, is set here, per academic year. A month outside the billing months
            is skipped by the generator rather than invoiced at zero.
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Year</th>
                        <th>Runs</th>
                        <th>Billing</th>
                        <th>Billable months</th>
                        <th class="num">Rates</th>
                        <th class="num">Invoices</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($years as $year)
                        <tr>
                            <td>
                                <span class="font-medium">{{ $year->name }}</span>
                                @if ($year->is_active)<span class="badge badge-success ms-2">Current</span>@endif
                            </td>
                            <td class="whitespace-nowrap text-muted">
                                {{ $year->start_date->format('j M Y') }} – {{ $year->end_date->format('j M Y') }}
                            </td>
                            <td>
                                <x-badge :tone="$year->billing_mode === \App\Enums\BillingMode::Continue ? 'info' : 'warning'"
                                         :label="$year->billing_mode->label()"/>
                            </td>
                            <td class="text-muted">
                                @if ($year->billing_mode === \App\Enums\BillingMode::Continue)
                                    All twelve months
                                @else
                                    {{ collect($year->billable_months ?? [])
                                        ->map(fn ($m) => \Illuminate\Support\Carbon::create(2000, $m, 1)->format('M'))
                                        ->implode(', ') ?: '—' }}
                                @endif
                            </td>
                            <td class="num">{{ $year->rate_cards_count }}</td>
                            <td class="num">{{ number_format($year->invoices_count) }}</td>
                            <td class="text-end">
                                @can('manage-rates')
                                    <a href="{{ route('academic-years.edit', $year) }}" class="btn btn-ghost btn-sm">
                                        <x-icon name="edit" class="size-3.5"/>
                                    </a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty title="No academic years"
                            message="An academic year must cover a month before that month can be billed."/></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
