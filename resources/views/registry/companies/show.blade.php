@extends('layouts.app')
@section('title', $busCompany->name)
@section('subtitle', $busCompany->code.' · '.strtoupper($busCompany->status))

@section('actions')
    @can('view-financials')
        <a href="{{ route('reports.bus-company-statement', $busCompany) }}" class="btn btn-secondary">
            <x-icon name="file" class="size-3.5"/> Statement
        </a>
    @endcan
    @can('manage-registry')
        <a href="{{ route('buses.create', ['bus_company_id' => $busCompany->id]) }}" class="btn btn-secondary">
            <x-icon name="plus" class="size-3.5"/> Add bus
        </a>
        <a href="{{ route('bus-companies.edit', $busCompany) }}" class="btn btn-primary">Edit</a>
    @endcan
@endsection

@section('content')
    <div class="grid gap-4 xl:grid-cols-[1fr_320px]">
        <div class="flex flex-col gap-4">
            @can('view-financials')
                <div class="grid gap-3.5 sm:grid-cols-3">
                    <x-kpi label="Invoiced" :value="\App\Support\Money::format($statement['totals']['invoiced'])"
                           accent="#00C2E8" :delta="$statement['filters']->label()"/>
                    <x-kpi label="Collected" :value="\App\Support\Money::format($statement['totals']['collected'])"
                           accent="#0E7C5A" delta="Received in the period"/>
                    <x-kpi label="Outstanding" :value="\App\Support\Money::format($statement['totals']['outstanding'])"
                           :accent="$statement['totals']['outstanding'] > 0 ? '#B3261E' : '#98A2B3'"
                           delta="Balance on the account"/>
                </div>
            @endcan

            <div class="card">
                <div class="card-head">
                    <div>
                        <div class="card-title">Buses and students</div>
                        <div class="card-sub">
                            Students counted from the enrollment history for {{ now()->format('F Y') }} —
                            the same figure billing charges from.
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Bus</th>
                                <th>Route</th>
                                <th>Driver</th>
                                <th class="num">Capacity</th>
                                <th class="num">Students</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($busCompany->buses as $bus)
                                <tr>
                                    <td>
                                        <a href="{{ route('buses.show', $bus) }}" class="font-medium hover:text-signal">{{ $bus->code }}</a>
                                        @if ($bus->plate_number)
                                            <div class="eyebrow">{{ $bus->plate_number }}</div>
                                        @endif
                                    </td>
                                    <td class="text-muted">{{ $bus->route_name ?: '—' }}</td>
                                    <td class="text-muted">{{ $bus->driver_name ?: '—' }}</td>
                                    <td class="num">{{ $bus->capacity }}</td>
                                    <td class="num">{{ number_format($studentsPerBus[$bus->id] ?? 0) }}</td>
                                    <td><x-badge :status="$bus->status" :tone="$bus->status === 'active' ? 'success' : 'neutral'"/></td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><x-empty title="No buses registered"
                                    message="A bus company with no buses has no students, and nothing to bill."/></td></tr>
                            @endforelse
                        </tbody>
                        @if ($busCompany->buses->isNotEmpty())
                            <tfoot>
                                <tr>
                                    <td colspan="4">{{ $busCompany->buses->count() }} buses</td>
                                    <td class="num">{{ number_format($studentsPerBus->sum()) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>

            @can('view-financials')
                <div class="card">
                    <div class="card-head">
                        <div>
                            <div class="card-title">Account statement</div>
                            <div class="card-sub">{{ $statement['filters']->label() }}</div>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table table-compact">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Reference</th>
                                    <th>Description</th>
                                    <th class="num">Charged</th>
                                    <th class="num">Paid</th>
                                    <th class="num">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="bg-mist">
                                    <td colspan="5" class="text-muted">Opening balance</td>
                                    <td class="num"><x-money :value="$statement['opening_balance']"/></td>
                                </tr>
                                @foreach ($statement['entries'] as $entry)
                                    <tr>
                                        <td class="whitespace-nowrap text-muted">{{ $entry->date->format('j M Y') }}</td>
                                        <td>
                                            <a href="{{ $entry->type === 'invoice'
                                                ? route('invoices.show', $entry->model)
                                                : route('payments.show', $entry->model) }}"
                                               class="money text-xs hover:text-signal">{{ $entry->reference }}</a>
                                        </td>
                                        <td class="text-muted">{{ $entry->description }}</td>
                                        <td class="num">{{ $entry->debit > 0 ? \App\Support\Money::format($entry->debit) : '' }}</td>
                                        <td class="num">{{ $entry->credit > 0 ? \App\Support\Money::format($entry->credit) : '' }}</td>
                                        <td class="num"><x-money :value="$entry->balance"/></td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="5">Closing balance</td>
                                    <td class="num"><x-money :value="$statement['closing_balance']"/></td>
                                </tr>
                            </tfoot>
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
                        'Contact' => $busCompany->contact_name,
                        'Phone' => $busCompany->phone,
                        'Email' => $busCompany->email,
                        'Address' => $busCompany->address,
                        'Tax number' => $busCompany->tax_number,
                        'Payment terms' => $busCompany->payment_terms_days.' days',
                        'Contract start' => $busCompany->contract_start?->format('j M Y'),
                        'Contract end' => $busCompany->contract_end?->format('j M Y'),
                    ] as $label => $value)
                        <div class="flex justify-between gap-3">
                            <dt class="shrink-0 text-faint">{{ $label }}</dt>
                            <dd class="text-end text-body">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($busCompany->notes)
                    <p class="mt-3 border-t border-rule pt-3 text-xs leading-relaxed text-muted">{{ $busCompany->notes }}</p>
                @endif
            </div>

            @can('view-rates')
                @php $rate = app(\App\Services\Billing\RateResolver::class)->resolve($busCompany, now()); @endphp
                <div class="card card-pad">
                    <div class="eyebrow mb-3">Rate in force</div>
                    @if ($rate)
                        <div class="font-display text-2xl font-medium">{{ \App\Support\Money::format($rate->amount) }}</div>
                        <div class="mt-1 text-xs text-muted">per student, per month</div>
                        <div class="mt-3 flex flex-col gap-1.5 border-t border-rule pt-3 text-[11px] text-faint">
                            <div>{{ $rate->isGeneral() ? 'Standard rate for all companies' : 'Special agreement for this company' }}</div>
                            <div>
                                {{ $rate->effective_from->format('j M Y') }} –
                                {{ $rate->effective_to?->format('j M Y') ?? 'open ended' }}
                            </div>
                        </div>
                    @else
                        <p class="text-xs leading-relaxed text-negative">
                            No rate is in force today. Billing for this company will be skipped until one is added.
                        </p>
                    @endif
                    @can('manage-rates')
                        <a href="{{ route('rates.create', ['bus_company_id' => $busCompany->id]) }}"
                           class="btn btn-secondary btn-sm mt-4 w-full justify-center">Manage rates</a>
                    @endcan
                </div>
            @endcan

            <div class="card card-pad">
                <div class="eyebrow mb-3">Registry</div>
                <div class="flex flex-col gap-2">
                    <a href="{{ route('buses.index', ['bus_company_id' => $busCompany->id]) }}"
                       class="btn btn-secondary btn-sm w-full justify-between">
                        Buses <span class="money">{{ $busCompany->buses->count() }}</span>
                    </a>
                    <a href="{{ route('students.index', ['bus_company_id' => $busCompany->id]) }}"
                       class="btn btn-secondary btn-sm w-full justify-between">
                        Students <span class="money">{{ number_format($studentsPerBus->sum()) }}</span>
                    </a>
                    @can('view-financials')
                        <a href="{{ route('invoices.index', ['bus_company_id' => $busCompany->id]) }}"
                           class="btn btn-secondary btn-sm w-full justify-between">
                            Invoices <x-icon name="chevron-right" class="size-3.5"/>
                        </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>
@endsection
