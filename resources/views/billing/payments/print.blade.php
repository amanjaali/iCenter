<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $payment->number }} · ALLVA Accounting</title>
    <link rel="icon" href="{{ asset('brand/icons/favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Sora:wght@300;400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4; margin: 16mm; }
        @media print { .no-print { display: none !important; } body { background: #fff; } }
    </style>
</head>
<body class="bg-mist">

<div class="no-print mx-auto flex max-w-[210mm] items-center justify-between gap-3 px-6 pt-6">
    <a href="{{ route('payments.show', $payment) }}" class="btn btn-secondary btn-sm">
        <x-icon name="arrow-left" class="size-3.5"/> Back
    </a>
    <button onclick="window.print()" class="btn btn-primary btn-sm">
        <x-icon name="print" class="size-3.5"/> Print
    </button>
</div>

<div class="mx-auto my-6 max-w-[210mm] bg-white p-10 shadow-sm print:my-0 print:p-0 print:shadow-none">

    {{-- Same stationery lockup as the invoice, so the two documents read as a
         pair when the bus company files them together. --}}
    <div class="flex items-start justify-between border-b-2 border-signal pb-5">
        <div class="flex items-center gap-3">
            <svg width="30" height="30" viewBox="0 0 120 120" fill="none" aria-hidden="true">
                <path d="M54 10 L54 86 L14 108 Z" fill="#0A1F3D"/>
                <path d="M66 10 L106 108 L66 86 Z" fill="#00C2E8"/>
            </svg>
            <div>
                <div class="font-display text-[19px] font-medium leading-none tracking-[0.06em]">ALLVA</div>
                <div class="mt-1.5 text-[6px] font-medium tracking-[0.36em] text-faint ps-[0.36em]">ACCOUNTING</div>
            </div>
        </div>
        <div class="text-end">
            <div class="eyebrow">Official receipt</div>
            <div class="mt-1 font-display text-xl font-medium">{{ $payment->number }}</div>
            <div class="eyebrow mt-1">{{ $payment->payment_date->format('j F Y') }}</div>
        </div>
    </div>

    @if ($payment->status === 'void')
        <div class="mt-6 border border-negative bg-negative-soft px-4 py-3 text-xs font-medium text-negative">
            This receipt has been voided and no longer settles anything. It is reproduced for the record only.
        </div>
    @endif

    <div class="mt-7 grid grid-cols-2 gap-8">
        <div>
            <div class="eyebrow mb-2">Received by</div>
            <div class="text-[13px] font-medium">{{ $company['company.legal_name'] ?: ($company['company.name'] ?? 'ALLVA Company') }}</div>
            <div class="mt-1 text-xs leading-relaxed text-muted">
                {{ $company['company.address'] ?? '' }}<br>
                @if (!empty($company['company.phone'])){{ $company['company.phone'] }}<br>@endif
                @if (!empty($company['company.email'])){{ $company['company.email'] }}<br>@endif
                @if (!empty($company['company.tax_number']))Tax number: {{ $company['company.tax_number'] }}@endif
            </div>
        </div>
        <div>
            <div class="eyebrow mb-2">Received from</div>
            <div class="text-[13px] font-medium">{{ $payment->busCompany->name }}</div>
            <div class="mt-1 text-xs leading-relaxed text-muted">
                {{ $payment->busCompany->address }}<br>
                @if ($payment->busCompany->contact_name){{ $payment->busCompany->contact_name }}<br>@endif
                @if ($payment->busCompany->phone){{ $payment->busCompany->phone }}<br>@endif
                @if ($payment->busCompany->tax_number)Tax number: {{ $payment->busCompany->tax_number }}@endif
            </div>
        </div>
    </div>

    {{-- The sum received, stated once and prominently: this is the line the
         bus company is looking for when they open the page. --}}
    <div class="mt-7 border-y-2 border-navy py-5 text-center">
        <div class="eyebrow">Amount received</div>
        <div class="money mt-1.5 font-display text-3xl font-medium text-navy">
            {{ \App\Support\Money::format($payment->amount, true) }}
        </div>
        <div class="mt-1.5 text-[11px] text-muted">{{ \App\Support\Money::words($payment->amount) }}</div>
    </div>

    <div class="mt-6 grid grid-cols-4 gap-4 border-b border-rule pb-4">
        @foreach ([
            'Payment date' => $payment->payment_date->format('j M Y'),
            'Method' => ucfirst($payment->method),
            'Reference' => $payment->reference ?: '—',
            'Currency' => config('allva.currency.code'),
        ] as $label => $value)
            <div>
                <div class="eyebrow">{{ $label }}</div>
                <div class="mt-1 text-xs">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="eyebrow mt-7 mb-2">Settled against</div>
    <table class="table">
        <thead>
            <tr>
                <th>Invoice</th>
                <th>Service period</th>
                <th class="num">Invoice total</th>
                <th class="num">Applied here</th>
                <th class="num">Still due</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payment->allocations as $allocation)
                <tr>
                    <td class="money text-xs">{{ $allocation->invoice->number }}</td>
                    <td>{{ $allocation->invoice->billing_month->format('F Y') }}</td>
                    <td class="num">{{ \App\Support\Money::format($allocation->invoice->total) }}</td>
                    <td class="num">{{ \App\Support\Money::format($allocation->amount) }}</td>
                    <td class="num">{{ \App\Support\Money::format($allocation->invoice->balance_due) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-xs text-muted">
                        Held as payment in advance. It will be applied to invoices as those months are billed.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-6 flex justify-end">
        <div class="w-[300px]">
            <div class="flex justify-between py-1.5 text-xs">
                <span class="text-muted">Applied to invoices</span>
                <span class="money">{{ \App\Support\Money::format($payment->allocated_amount) }}</span>
            </div>
            @if ((float) $payment->unallocated_amount > 0)
                <div class="flex justify-between py-1.5 text-xs">
                    <span class="text-muted">Held in advance</span>
                    <span class="money">{{ \App\Support\Money::format($payment->unallocated_amount) }}</span>
                </div>
            @endif
            <div class="flex justify-between border-t-2 border-navy py-2.5">
                <span class="font-display text-sm font-medium">Account balance after this receipt</span>
                <span class="money text-sm font-medium">{{ \App\Support\Money::format($balance, true) }}</span>
            </div>
        </div>
    </div>

    <div class="mt-12 grid grid-cols-2 gap-10">
        <div>
            <div class="border-t border-navy pt-2 text-[10px] text-faint">Received by, for ALLVA Company</div>
        </div>
        <div>
            <div class="border-t border-navy pt-2 text-[10px] text-faint">For {{ $payment->busCompany->name }}</div>
        </div>
    </div>

    <div class="mt-8 border-t border-rule pt-5 text-[10px] leading-relaxed text-faint">
        <p>
            This receipt acknowledges the sum shown above, received on
            {{ $payment->payment_date->format('j F Y') }}
            @if ($payment->reference) under reference {{ $payment->reference }}@endif.
            @if ($balance > 0)
                {{ \App\Support\Money::format($balance, true) }} remains outstanding on this account.
            @else
                The account is settled in full as at the date of this receipt.
            @endif
        </p>
        <p class="mt-2">Issued from the ALLVA accounting system. No signature is required for it to be valid.</p>
    </div>
</div>

</body>
</html>
