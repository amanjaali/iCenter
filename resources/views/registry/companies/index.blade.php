@extends('layouts.app')
@section('title', 'Bus companies')
@section('subtitle', $companies->total().' REGISTERED')

@section('actions')
    @can('manage-registry')
        <a href="{{ route('bus-companies.create') }}" class="btn btn-primary">
            <x-icon name="plus" class="size-3.5"/> Add company
        </a>
    @endcan
@endsection

@section('content')
    <form method="GET" class="card mb-4 flex flex-wrap items-end gap-3 p-3.5">
        <div class="min-w-[220px] flex-1">
            <label class="label" for="q">Search</label>
            <input id="q" name="q" class="input" value="{{ request('q') }}"
                   placeholder="Name, code or contact">
        </div>
        <div class="min-w-[150px]">
            <label class="label" for="status">Status</label>
            <select id="status" name="status" class="select">
                <option value="">All statuses</option>
                @foreach (['active' => 'Active', 'suspended' => 'Suspended', 'closed' => 'Closed'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-primary"><x-icon name="search" class="size-3.5"/> Search</button>
        <a href="{{ route('bus-companies.index') }}" class="btn btn-secondary">Reset</a>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Contact</th>
                        <th class="num">Buses</th>
                        <th class="num">Students</th>
                        @can('view-financials')<th class="num">Outstanding</th>@endcan
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($companies as $company)
                        <tr>
                            <td>
                                <a href="{{ route('bus-companies.show', $company) }}" class="font-medium hover:text-signal">
                                    {{ $company->name }}
                                </a>
                                <div class="eyebrow">{{ $company->code }}</div>
                            </td>
                            <td class="text-muted">
                                {{ $company->contact_name ?: '—' }}
                                @if ($company->phone)
                                    <div class="text-[11px] text-faint">{{ $company->phone }}</div>
                                @endif
                            </td>
                            <td class="num">{{ number_format($company->buses_count) }}</td>
                            <td class="num">{{ number_format($company->students_count) }}</td>
                            @can('view-financials')
                                <td class="num">
                                    <x-money :value="$balances[$company->id] ?? 0" muted/>
                                </td>
                            @endcan
                            <td>
                                <x-badge :status="$company->status"
                                         :tone="$company->status === 'active' ? 'success' : ($company->status === 'suspended' ? 'warning' : 'neutral')"/>
                            </td>
                            <td class="text-end">
                                @can('manage-registry')
                                    <a href="{{ route('bus-companies.edit', $company) }}" class="btn btn-ghost btn-sm">
                                        <x-icon name="edit" class="size-3.5"/>
                                    </a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-empty title="No bus companies yet"
                                         message="Scope of work §3.1: every bus company registers its buses, and each bus carries its students. That chain is what billing is generated from.">
                                    @can('manage-registry')
                                        <a href="{{ route('bus-companies.create') }}" class="btn btn-primary btn-sm">Add the first company</a>
                                    @endcan
                                </x-empty>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($companies->hasPages())
            <div class="border-t border-rule px-5 py-3">{{ $companies->links() }}</div>
        @endif
    </div>
@endsection
