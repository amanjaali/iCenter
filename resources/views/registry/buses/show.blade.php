@extends('layouts.app')
@section('title', 'Bus '.$bus->code)
@section('subtitle', strtoupper($bus->busCompany->name))

@section('actions')
    @can('manage-registry')
        <a href="{{ route('students.create', ['bus_company_id' => $bus->bus_company_id, 'bus_id' => $bus->id]) }}"
           class="btn btn-secondary"><x-icon name="plus" class="size-3.5"/> Enrol student</a>
        <a href="{{ route('buses.edit', $bus) }}" class="btn btn-primary">Edit</a>
    @endcan
@endsection

@section('content')
    <div class="grid gap-4 xl:grid-cols-[1fr_300px]">
        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title">Students on this bus</div>
                    <div class="card-sub">{{ number_format($currentStudents) }} billable this month</div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Guardian</th>
                            <th>School</th>
                            <th>Joined</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($students as $student)
                            <tr>
                                <td>
                                    <a href="{{ route('students.show', $student) }}" class="font-medium hover:text-signal">{{ $student->name }}</a>
                                    <div class="eyebrow">{{ $student->code }}</div>
                                </td>
                                <td class="text-muted">
                                    {{ $student->guardian_name ?: '—' }}
                                    @if ($student->guardian_phone)<div class="text-[11px] text-faint">{{ $student->guardian_phone }}</div>@endif
                                </td>
                                <td class="text-muted">{{ $student->school_name ?: '—' }}</td>
                                <td class="whitespace-nowrap text-muted">{{ $student->joined_on?->format('j M Y') }}</td>
                                <td><x-badge :status="$student->status" :tone="$student->status === 'active' ? 'success' : 'neutral'"/></td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-empty title="No students on this bus"
                                message="A bus with no students contributes nothing to the invoice."/></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($students->hasPages())<div class="border-t border-rule px-5 py-3">{{ $students->links() }}</div>@endif
        </div>

        <div class="card card-pad">
            <div class="eyebrow mb-3">Bus details</div>
            <dl class="flex flex-col gap-2.5 text-xs">
                @foreach ([
                    'Company' => $bus->busCompany->name,
                    'Plate' => $bus->plate_number,
                    'Route' => $bus->route_name,
                    'Capacity' => $bus->capacity,
                    'Driver' => $bus->driver_name,
                    'Driver phone' => $bus->driver_phone,
                    'Device serial' => $bus->device_serial,
                    'Status' => ucfirst($bus->status),
                ] as $label => $value)
                    <div class="flex justify-between gap-3">
                        <dt class="shrink-0 text-faint">{{ $label }}</dt>
                        <dd class="text-end text-body">{{ $value !== null && $value !== '' ? $value : '—' }}</dd>
                    </div>
                @endforeach
            </dl>
            @if ($bus->capacity > 0)
                @php $usage = min(100, (int) round($currentStudents / $bus->capacity * 100)); @endphp
                <div class="mt-4 border-t border-rule pt-4">
                    <div class="mb-2 flex justify-between text-[11px]">
                        <span class="text-muted">Capacity used</span>
                        <span class="money">{{ $usage }}%</span>
                    </div>
                    <div class="h-[5px] rounded-sm bg-rule">
                        <div class="h-[5px] rounded-sm {{ $usage > 100 ? 'bg-negative' : 'bg-signal' }}" style="width: {{ $usage }}%"></div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
