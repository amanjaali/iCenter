@extends('layouts.app')
@section('title', 'Students')
@section('subtitle', number_format($counts['active']).' ACTIVE · '.number_format($counts['left']).' LEFT')

@section('actions')
    @can('manage-registry')
        <a href="{{ route('students.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-3.5"/> Enrol student</a>
    @endcan
@endsection

@section('content')
    <form method="GET" class="card mb-4 flex flex-wrap items-end gap-3 p-3.5">
        <div class="min-w-[200px] flex-1">
            <label class="label" for="q">Search</label>
            <input id="q" name="q" class="input" value="{{ request('q') }}" placeholder="Name, code, guardian or school">
        </div>
        <div class="min-w-[180px]">
            <label class="label" for="bus_company_id">Bus company</label>
            <select id="bus_company_id" name="bus_company_id" class="select">
                <option value="">All companies</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}" @selected(request('bus_company_id') == $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>
        </div>
        @if ($buses->isNotEmpty())
            <div class="min-w-[160px]">
                <label class="label" for="bus_id">Bus</label>
                <select id="bus_id" name="bus_id" class="select">
                    <option value="">All buses</option>
                    @foreach ($buses as $bus)
                        <option value="{{ $bus->id }}" @selected(request('bus_id') == $bus->id)>{{ $bus->code }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="min-w-[130px]">
            <label class="label" for="status">Status</label>
            <select id="status" name="status" class="select">
                <option value="">All</option>
                @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'left' => 'Left'] as $v => $l)
                    <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-primary"><x-icon name="search" class="size-3.5"/> Search</button>
        <a href="{{ route('students.index') }}" class="btn btn-secondary">Reset</a>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Company</th>
                        <th>Bus</th>
                        <th>Guardian</th>
                        <th>Joined</th>
                        <th>Left</th>
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
                            <td class="text-muted">{{ $student->busCompany->name }}</td>
                            <td class="text-muted">{{ $student->bus?->code ?? '—' }}</td>
                            <td class="text-muted">
                                {{ $student->guardian_name ?: '—' }}
                                @if ($student->guardian_phone)<div class="text-[11px] text-faint">{{ $student->guardian_phone }}</div>@endif
                            </td>
                            <td class="whitespace-nowrap text-muted">{{ $student->joined_on?->format('j M Y') }}</td>
                            <td class="whitespace-nowrap text-muted">{{ $student->left_on?->format('j M Y') ?? '—' }}</td>
                            <td><x-badge :status="$student->status" :tone="$student->status === 'active' ? 'success' : 'neutral'"/></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty title="No students found"
                            message="Every student is linked to a bus, and each bus to a company. That chain is the source of all billing."/></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($students->hasPages())<div class="border-t border-rule px-5 py-3">{{ $students->links() }}</div>@endif
    </div>
@endsection
