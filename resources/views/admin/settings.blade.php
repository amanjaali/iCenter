@extends('layouts.app')
@section('title', 'Settings')
@section('subtitle', 'THE THREE OPEN DECISIONS FROM §10')

@section('content')
    @php $s = app(\App\Services\SettingsService::class); @endphp

    <form method="PUT" action="{{ route('settings.update') }}" class="flex max-w-4xl flex-col gap-4"
          x-data="{ basis: '{{ $basis->value }}', proration: '{{ $proration->value }}' }">
        @csrf @method('PUT')

        <div class="flex items-start gap-2.5 rounded-md border border-line bg-white px-4 py-3 text-xs text-body">
            <x-icon name="info" class="mt-px size-4 shrink-0 text-signal"/>
            <div class="leading-relaxed">
                Scope of work §10 leaves three decisions open, each of which changes the accounting logic.
                All three are implemented as switches here rather than as constants in the code, so whichever
                way the client decides, the system does not need rebuilding.
                <strong class="font-medium">Changing a setting never restates what is already posted</strong> —
                {{ number_format($postedInvoices) }} issued invoices and {{ number_format($postedShares) }} revenue
                share runs keep the rules that were in force when they were posted.
            </div>
        </div>

        {{-- §10.1 --}}
        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title">Revenue share basis</div>
                    <div class="card-sub">
                        Scope of work §10.1 — "Are operating expenses deducted before the 50/50 split with
                        Cyber Gate, or does Cyber Gate receive 50% of gross revenue with expenses then deducted
                        from ALLVA's 50%?" This decision directly changes every partner's figure.
                    </div>
                </div>
            </div>
            <div class="flex flex-col gap-3 p-5">
                @foreach ($basisOptions as $option)
                    <label class="flex cursor-pointer items-start gap-3 rounded-md border p-3.5 transition-colors"
                           :class="basis === '{{ $option->value }}' ? 'border-signal bg-[#E6F8FD]/40' : 'border-line hover:border-line-strong'">
                        <input type="radio" name="revenue_share_basis" value="{{ $option->value }}" x-model="basis"
                               class="mt-0.5 size-3.5 accent-navy">
                        <span>
                            <span class="block text-xs font-medium">
                                {{ $option->label() }}
                                @if ($option === \App\Enums\RevenueShareBasis::Gross)
                                    <span class="badge badge-neutral ms-1.5">Assumed by the scope of work</span>
                                @endif
                            </span>
                            <span class="mt-1 block text-[11px] leading-relaxed text-muted">{{ $option->description() }}</span>
                        </span>
                    </label>
                @endforeach

                <div class="mt-1 grid gap-4 border-t border-rule pt-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="revenue_share_partner_name">Revenue partner</label>
                        <input id="revenue_share_partner_name" name="revenue_share_partner_name" class="input" required
                               value="{{ old('revenue_share_partner_name', $s->revenueSharePartnerName()) }}">
                    </div>
                    <div>
                        <label class="label" for="revenue_share_percent">Share percentage</label>
                        <input id="revenue_share_percent" name="revenue_share_percent" type="number" step="0.01" min="0" max="100"
                               class="input input-num" required value="{{ old('revenue_share_percent', $s->revenueSharePercent()) }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- §10.2 --}}
        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title">Mid-month join and leave rule</div>
                    <div class="card-sub">
                        Scope of work §10.2 — "One rule must be chosen and applied to all bus companies."
                        The rule in force is frozen onto every invoice as it is raised.
                    </div>
                </div>
            </div>
            <div class="flex flex-col gap-3 p-5">
                @foreach ($prorationOptions as $option)
                    <label class="flex cursor-pointer items-start gap-3 rounded-md border p-3.5 transition-colors"
                           :class="proration === '{{ $option->value }}' ? 'border-signal bg-[#E6F8FD]/40' : 'border-line hover:border-line-strong'">
                        <input type="radio" name="proration_method" value="{{ $option->value }}" x-model="proration"
                               class="mt-0.5 size-3.5 accent-navy">
                        <span>
                            <span class="block text-xs font-medium">{{ $option->label() }}</span>
                            <span class="mt-1 block text-[11px] leading-relaxed text-muted">{{ $option->description() }}</span>
                        </span>
                    </label>
                @endforeach

                <div class="mt-1 border-t border-rule pt-4">
                    <label class="label" for="invoice_due_days">Default payment terms (days)</label>
                    <input id="invoice_due_days" name="invoice_due_days" type="number" min="0" max="180"
                           class="input input-num w-[160px]" required value="{{ old('invoice_due_days', $s->invoiceDueDays()) }}">
                    <div class="hint">Used when a bus company has no terms of its own.</div>
                </div>
            </div>
        </div>

        {{-- §10.3 --}}
        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title">Holiday and summer billing</div>
                    <div class="card-sub">
                        Scope of work §10.3 — "This must be set per academic year." The setting below is only
                        the default applied to a newly created year.
                    </div>
                </div>
            </div>
            <div class="grid gap-4 p-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="default_billing_mode">Default for a new academic year</label>
                    <select id="default_billing_mode" name="default_billing_mode" class="select">
                        @foreach ($billingModeOptions as $value => $label)
                            <option value="{{ $value }}" @selected($s->get('billing.default_billing_mode') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <a href="{{ route('academic-years.index') }}" class="btn btn-secondary w-full justify-center">
                        Set it per year on Academic years
                    </a>
                </div>
            </div>
        </div>

        {{-- §7 --}}
        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title">Overhead allocation</div>
                    <div class="card-sub">
                        Scope of work §7 — "Where an overhead cannot be attributed to a single project, the
                        system must apply a defined allocation method so that eTrackify project profitability
                        remains meaningful." Allocation happens at reporting time; the ledger keeps overhead
                        where it was incurred.
                    </div>
                </div>
            </div>
            <div class="p-5">
                <label class="label" for="overhead_method">Method</label>
                <select id="overhead_method" name="overhead_method" class="select max-w-md">
                    @foreach ($allocationOptions as $value => $label)
                        <option value="{{ $value }}" @selected($allocation->value === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><div class="card-title">Company details</div></div>
            <div class="grid gap-4 p-5 sm:grid-cols-2">
                @foreach ([
                    'company_name' => ['Company name', 'company.name', true],
                    'company_legal_name' => ['Legal name', 'company.legal_name', false],
                    'company_tax_number' => ['Tax number', 'company.tax_number', false],
                    'company_phone' => ['Phone', 'company.phone', false],
                    'company_email' => ['Email', 'company.email', false],
                    'company_address' => ['Address', 'company.address', false],
                ] as $field => [$label, $key, $required])
                    <div>
                        <label class="label" for="{{ $field }}">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" class="input"
                               value="{{ old($field, $s->get($key)) }}" @required($required)>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title">Reason for this change</div>
                    <div class="card-sub">Recorded against every setting that changes, in the audit trail.</div>
                </div>
            </div>
            <div class="p-5">
                <textarea name="reason" rows="2" class="textarea"
                          placeholder="For example: confirmed with the partners on 12 March"></textarea>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('dashboard') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save settings</button>
        </div>
    </form>
@endsection
