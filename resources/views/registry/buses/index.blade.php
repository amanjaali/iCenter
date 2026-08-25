@extends('layouts.app')
@section('title', 'Buses')
@section('subtitle', $buses->total().' ON THE REGISTER')

@section('actions')
    @can('manage-registry')
        <a href="{{ route('buses.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-3.5"/> Add bus</a>
    @endcan
@endsection

@section('content')
    <form method="GET" class="card mb-4 flex flex-wrap items-end gap-3 p-3.5">
        <div class="min-w-[200px] flex-1">
            <label class="label" for="q">Search</label>
            <input id="q" name="q" class="input" value="{{ request('q') }}" placeholder="Code, plate, route or driver">
        </div>
        <div class="min-w-[190px]">
            <label class="label" for="bus_company_id">Bus company</label>
            <select id="bus_company_id" name="bus_company_id" class="select">
                <option value="">All companies</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}" @selected(request('bus_company_id') == $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-[140px]">
            <label class="label" for="status">Status</label>
            <select id="status" name="status" class="select">
                <option value="">All statuses</option>
                @foreach (['active' => 'Active', 'maintenance' => 'Maintenance', 'retired' => 'Retired'] as $v => $l)
                    <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-primary"><x-icon name="search" class="size-3.5"/> Search</button>
        <a href="{{ route('buses.index') }}" class="btn btn-secondary">Reset</a>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Bus</th>
                        <th>Company</th>
                        <th>Route</th>
                        <th>Driver</th>
                        <th class="num">Capacity</th>
                        <th class="num">Students</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($buses as $bus)
                        <tr>
                            <td>
                                <a href="{{ route('buses.show', $bus) }}" class="font-medium hover:text-signal">{{ $bus->code }}</a>
                                @if ($bus->plate_number)<div class="eyebrow">{{ $bus->plate_number }}</div>@endif
                            </td>
                            <td>
                                <a href="{{ route('bus-companies.show', $bus->busCompany) }}"
                                   class="text-muted hover:text-signal">{{ $bus->busCompany->name }}</a>
                            </td>
                            <td class="text-muted">{{ $bus->route_name ?: '—' }}</td>
                            <td class="text-muted">
                                {{ $bus->driver_name ?: '—' }}
                                @if ($bus->driver_phone)<div class="text-[11px] text-faint">{{ $bus->driver_phone }}</div>@endif
                            </td>
                            <td class="num">{{ $bus->capacity }}</td>
                            <td class="num">
                                {{ number_format($bus->students_count) }}
                                @if ($bus->capacity > 0 && $bus->students_count > $bus->capacity)
                                    <div class="text-[11px] text-negative">over capacity</div>
                                @endif
                            </td>
                            <td><x-badge :status="$bus->status" :tone="$bus->status === 'active' ? 'success' : ($bus->status === 'maintenance' ? 'warning' : 'neutral')"/></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty title="No buses registered"
                            message="Scope of work §3.1: every bus is recorded with the students assigned to it."/></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($buses->hasPages())<div class="border-t border-rule px-5 py-3">{{ $buses->links() }}</div>@endif
    </div>
@endsection
