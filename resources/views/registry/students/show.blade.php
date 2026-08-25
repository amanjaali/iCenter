@extends('layouts.app')
@section('title', $student->name)
@section('subtitle', $student->code.' · '.strtoupper($student->busCompany->name))

@section('actions')
    @can('manage-registry')
        <a href="{{ route('students.edit', $student) }}" class="btn btn-primary">Edit</a>
    @endcan
@endsection

@section('content')
    <div class="grid gap-4 xl:grid-cols-[1fr_320px]">
        <div class="flex flex-col gap-4">
            {{-- The enrollment history is what billing actually counts from. --}}
            <div class="card">
                <div class="card-head">
                    <div>
                        <div class="card-title">Enrolment history</div>
                        <div class="card-sub">Billing counts days from these records, not from the status field.</div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="table table-compact">
                        <thead>
                            <tr>
                                <th>From</th>
                                <th>To</th>
                                <th>Bus</th>
                                <th>Started</th>
                                <th>Ended</th>
                                <th>Billable</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($student->enrollments as $enrollment)
                                <tr>
                                    <td class="whitespace-nowrap">{{ $enrollment->start_date->format('j M Y') }}</td>
                                    <td class="whitespace-nowrap">
                                        {{ $enrollment->end_date?->format('j M Y') ?? '—' }}
                                        @if ($enrollment->isOpen())
                                            <span class="badge badge-success ms-1">Open</span>
                                        @endif
                                    </td>
                                    <td class="text-muted">{{ $enrollment->bus?->code ?? '—' }}</td>
                                    <td class="text-muted">{{ ucfirst($enrollment->start_reason ?? '—') }}</td>
                                    <td class="text-muted">{{ ucfirst($enrollment->end_reason ?? '—') }}</td>
                                    <td>
                                        <x-badge :tone="$enrollment->is_billable ? 'success' : 'neutral'"
                                                 :label="$enrollment->is_billable ? 'Yes' : 'No'"/>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @can('view-financials')
                <div class="card">
                    <div class="card-head">
                        <div>
                            <div class="card-title">Billed months</div>
                            <div class="card-sub">Every month this student has been charged for, and on what basis.</div>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table table-compact">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Invoice</th>
                                    <th class="num">Days billed</th>
                                    <th>Note</th>
                                    <th class="num">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($billedMonths as $detail)
                                    @php $invoice = $detail->line?->invoice; @endphp
                                    <tr>
                                        <td class="whitespace-nowrap">{{ $invoice?->billing_month?->format('F Y') ?? '—' }}</td>
                                        <td>
                                            @if ($invoice)
                                                <a href="{{ route('invoices.show', $invoice) }}" class="money text-xs hover:text-signal">
                                                    {{ $invoice->number }}
                                                </a>
                                            @endif
                                        </td>
                                        <td class="num">{{ $detail->billable_days }} / {{ $detail->days_in_month }}</td>
                                        <td class="text-muted">{{ $detail->note ?: ($detail->is_partial_month ? 'Part month' : 'Full month') }}</td>
                                        <td class="num"><x-money :value="$detail->amount"/></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5"><x-empty title="Not yet billed"
                                        message="This student has not appeared on an issued invoice."/></td></tr>
                                @endforelse
                            </tbody>
                            @if ($billedMonths->isNotEmpty())
                                <tfoot>
                                    <tr>
                                        <td colspan="4">Total billed</td>
                                        <td class="num"><x-money :value="$billedMonths->sum('amount')"/></td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            @endcan
        </div>

        <div class="flex flex-col gap-4">
            <div class="card card-pad">
                <div class="eyebrow mb-3">Details</div>
                <dl class="flex flex-col gap-2.5 text-xs">
                    @foreach ([
                        'Company' => $student->busCompany->name,
                        'Bus' => $student->bus?->code,
                        'Guardian' => $student->guardian_name,
                        'Guardian phone' => $student->guardian_phone,
                        'School' => $student->school_name,
                        'Grade' => $student->grade,
                        'BLE tag' => $student->ble_tag,
                        'Joined' => $student->joined_on?->format('j M Y'),
                        'Left' => $student->left_on?->format('j M Y'),
                    ] as $label => $value)
                        <div class="flex justify-between gap-3">
                            <dt class="shrink-0 text-faint">{{ $label }}</dt>
                            <dd class="text-end text-body">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            @can('manage-registry')
                @if ($student->currentEnrollment())
                    <div class="card card-pad">
                        <div class="eyebrow mb-3">Record as left</div>
                        <p class="mb-3 text-xs leading-relaxed text-muted">
                            Closes the enrolment on the chosen date. Billing stops from that day, prorated
                            by the rule in force.
                        </p>
                        <form method="POST" action="{{ route('students.withdraw', $student) }}" class="flex flex-col gap-3">
                            @csrf
                            <div>
                                <label class="label" for="left_on">Last day</label>
                                <input id="left_on" name="left_on" type="date" class="input" required
                                       value="{{ now()->toDateString() }}">
                            </div>
                            <div>
                                <label class="label" for="end_reason">Reason</label>
                                <select id="end_reason" name="end_reason" class="select">
                                    @foreach (['left' => 'Left the service', 'transferred' => 'Transferred', 'suspended' => 'Suspended'] as $v => $l)
                                        <option value="{{ $v }}">{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button class="btn btn-secondary w-full justify-center">Record departure</button>
                        </form>
                    </div>
                @else
                    <div class="card card-pad">
                        <div class="eyebrow mb-3">Re-enrol</div>
                        <p class="mb-3 text-xs leading-relaxed text-muted">
                            Opens a new enrolment. Billing resumes from the chosen date.
                        </p>
                        <form method="POST" action="{{ route('students.reinstate', $student) }}" class="flex flex-col gap-3">
                            @csrf
                            <div>
                                <label class="label" for="start_date">First day back</label>
                                <input id="start_date" name="start_date" type="date" class="input" required value="{{ now()->toDateString() }}">
                            </div>
                            <div>
                                <label class="label" for="reinstate_bus">Bus</label>
                                <select id="reinstate_bus" name="bus_id" class="select" required>
                                    @foreach ($student->busCompany->buses()->where('status', 'active')->orderBy('code')->get() as $bus)
                                        <option value="{{ $bus->id }}" @selected($bus->id === $student->bus_id)>{{ $bus->code }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button class="btn btn-primary w-full justify-center">Re-enrol student</button>
                        </form>
                    </div>
                @endif
            @endcan
        </div>
    </div>
@endsection
