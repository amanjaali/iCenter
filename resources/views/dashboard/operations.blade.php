@extends('layouts.app')
@section('title', 'Registry')
@section('subtitle', strtoupper(now()->format('F Y')))

@section('actions')
    @can('manage-registry')
        <a href="{{ route('students.create') }}" class="btn btn-primary">
            <x-icon name="plus" class="size-3.5"/> Enrol student
        </a>
    @endcan
@endsection

@section('content')
    {{-- Scope of work §9.2: the operations role registers companies, buses and
         students and has no access to financial entries, so this dashboard
         shows no money at all. --}}
    <div class="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi label="Active students" :value="number_format($activeStudents)" accent="#00C2E8"
               :href="route('students.index')" delta="Currently enrolled"/>
        <x-kpi label="Buses registered" :value="number_format($buses)" accent="#0A1F3D"
               :href="route('buses.index')" delta="Across all companies"/>
        <x-kpi label="Joined this month" :value="number_format($joinedThisMonth)" accent="#0E7C5A"
               direction="up" delta="New enrolments"/>
        <x-kpi label="Left this month" :value="number_format($leftThisMonth)" accent="#98A2B3"
               direction="down" delta="Enrolments closed"/>
    </div>

    <div class="card mt-4">
        <div class="card-head">
            <div>
                <div class="card-title">Bus companies</div>
                <div class="card-sub">Buses and students on the register</div>
            </div>
            @can('manage-registry')
                <a href="{{ route('bus-companies.create') }}" class="btn btn-secondary btn-sm">
                    <x-icon name="plus" class="size-3"/> Add company
                </a>
            @endcan
        </div>

        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Contact</th>
                        <th class="num">Buses</th>
                        <th class="num">Students</th>
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
                            <td class="text-muted">{{ $company->contact_name ?: '—' }}</td>
                            <td class="num">{{ number_format($company->buses_count) }}</td>
                            <td class="num">{{ number_format($company->students_count) }}</td>
                            <td class="text-end">
                                <a href="{{ route('students.index', ['bus_company_id' => $company->id]) }}"
                                   class="btn btn-secondary btn-sm">Students</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty title="No bus companies registered"
                            message="Register a bus company, then add its buses and students."/></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
