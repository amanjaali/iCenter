@extends('layouts.app')
@section('title', 'Rate history')
@section('subtitle', 'WHICH RATE APPLIED TO WHICH PERIOD AND COMPANY')

@section('content')
    <x-report-header title="Rate history" :subtitle="$report['company']?->name ?? 'All bus companies'"/>

    <form method="GET" class="card flex flex-wrap items-end gap-3 p-3.5 no-print">
        <div class="min-w-[220px]">
            <label class="label" for="bus_company_id">Bus company</label>
            <select id="bus_company_id" name="bus_company_id" class="select">
                <option value="">All bus companies</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}" @selected($report['company']?->id === $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-primary"><x-icon name="filter" class="size-3.5"/> Apply</button>
        <div class="ms-auto flex items-center gap-2">
            <button type="submit" name="export" value="csv" class="btn btn-secondary">
                <x-icon name="download" class="size-3.5"/> CSV
            </button>
            <button type="button" onclick="window.print()" class="btn btn-secondary">
                <x-icon name="print" class="size-3.5"/> Print
            </button>
        </div>
    </form>

    <div class="card mt-4 overflow-hidden">
        <div class="card-head">
            <div>
                <div class="card-title">Every rate ever set</div>
                <div class="card-sub">
                    Scope of work §4.2 — a historical month is always billed at the rate that applied at the
                    time, even after a later increase.
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Academic year</th><th>Applies to</th><th class="num">Amount</th>
                        <th>Effective from</th><th>Effective to</th><th>Set by</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['rows'] as $rate)
                        <tr class="{{ $rate->is_active ? '' : 'opacity-55' }}">
                            <td>{{ $rate->academicYear?->name ?? '—' }}</td>
                            <td>{{ $rate->busCompany?->name ?? 'All bus companies' }}</td>
                            <td class="num"><x-money :value="$rate->amount"/></td>
                            <td class="whitespace-nowrap text-muted">{{ $rate->effective_from->format('j M Y') }}</td>
                            <td class="whitespace-nowrap text-muted">{{ $rate->effective_to?->format('j M Y') ?? 'Open ended' }}</td>
                            <td class="text-muted">{{ $rate->createdBy?->name ?? 'System' }}</td>
                            <td><x-badge :tone="$rate->is_active ? 'success' : 'neutral'" :label="$rate->is_active ? 'Active' : 'Retired'"/></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty title="No rates configured"/></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
