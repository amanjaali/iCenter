<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->number }} · ALLVA Accounting</title>
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
    <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-secondary btn-sm">
        <x-icon name="arrow-left" class="size-3.5"/> Back
    </a>
    <button onclick="window.print()" class="btn btn-primary btn-sm">
        <x-icon name="print" class="size-3.5"/> Print
    </button>
</div>

<div class="mx-auto my-6 max-w-[210mm] bg-white p-10 shadow-sm print:my-0 print:p-0 print:shadow-none">

    {{-- Invoice mark: the brand's stationery lockup, wordmark above the
         hairline rule with the product name beside it. --}}
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
            <div class="eyebrow">Invoice</div>
            <div class="mt-1 font-display text-xl font-medium">{{ $invoice->number }}</div>
            <div class="eyebrow mt-1">{{ $invoice->billing_month->format('F Y') }}</div>
        </div>
    </div>

    <div class="mt-7 grid grid-cols-2 gap-8">
        <div>
            <div class="eyebrow mb-2">From</div>
            <div class="text-[13px] font-medium">{{ $company['company.legal_name'] ?: ($company['company.name'] ?? 'ALLVA Company') }}</div>
            <div class="mt-1 text-xs leading-relaxed text-muted">
                {{ $company['company.address'] ?? '' }}<br>
                @if (!empty($company['company.phone'])){{ $company['company.phone'] }}<br>@endif
                @if (!empty($company['company.email'])){{ $company['company.email'] }}<br>@endif
                @if (!empty($company['company.tax_number']))Tax number: {{ $company['company.tax_number'] }}@endif
            </div>
        </div>
        <div>
            <div class="eyebrow mb-2">Billed to</div>
            <div class="text-[13px] font-medium">{{ $invoice->busCompany->name }}</div>
            <div class="mt-1 text-xs leading-relaxed text-muted">
                {{ $invoice->busCompany->address }}<br>
                @if ($invoice->busCompany->contact_name){{ $invoice->busCompany->contact_name }}<br>@endif
                @if ($invoice->busCompany->phone){{ $invoice->busCompany->phone }}<br>@endif
                @if ($invoice->busCompany->tax_number)Tax number: {{ $invoice->busCompany->tax_number }}@endif
            </div>
        </div>
    </div>

    <div class="mt-7 grid grid-cols-4 gap-4 border-y border-rule py-4">
        @foreach ([
            'Issue date' => $invoice->issue_date->format('j M Y'),
            'Due date' => $invoice->due_date->format('j M Y'),
            'Service period' => $invoice->billing_month->format('F Y'),
            'Currency' => config('allva.currency.code'),
        ] as $label => $value)
            <div>
                <div class="eyebrow">{{ $label }}</div>
                <div class="mt-1 text-xs">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <table class="table mt-6">
        <thead>
            <tr>
                <th>Description</th>
                <th class="num">Students</th>
                <th class="num">Months</th>
                <th class="num">Rate</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                <tr>
                    <td>
                        <div>{{ $line->bus?->label() ?? 'Unassigned students' }}</div>
                        <div class="eyebrow mt-0.5">Student transport subscriptions · {{ $invoice->billing_month->format('M Y') }}</div>
                    </td>
                    <td class="num">{{ number_format($line->student_count) }}</td>
                    <td class="num">{{ number_format($line->billable_units, 2) }}</td>
                    <td class="num">{{ \App\Support\Money::format($line->unit_price) }}</td>
                    <td class="num">{{ \App\Support\Money::format($line->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-6 flex justify-end">
        <div class="w-[280px]">
            @if ((float) $invoice->discount_amount > 0)
                <div class="flex justify-between py-1.5 text-xs">
                    <span class="text-muted">Subtotal</span>
                    <span class="money">{{ \App\Support\Money::format($invoice->subtotal) }}</span>
                </div>
                <div class="flex justify-between py-1.5 text-xs">
                    <span class="text-muted">Discount</span>
                    <span class="money">({{ \App\Support\Money::format($invoice->discount_amount) }})</span>
                </div>
            @endif
            <div class="flex justify-between border-t-2 border-navy py-2.5">
                <span class="font-display text-sm font-medium">Total due</span>
                <span class="money text-sm font-medium">{{ \App\Support\Money::format($invoice->total, true) }}</span>
            </div>
            @if ((float) $invoice->amount_paid > 0)
                <div class="flex justify-between py-1.5 text-xs">
                    <span class="text-muted">Paid to date</span>
                    <span class="money">({{ \App\Support\Money::format($invoice->amount_paid) }})</span>
                </div>
                <div class="flex justify-between border-t border-rule py-1.5 text-xs font-medium">
                    <span>Outstanding</span>
                    <span class="money">{{ \App\Support\Money::format($invoice->balance_due) }}</span>
                </div>
            @endif
        </div>
    </div>

    <div class="mt-10 border-t border-rule pt-5 text-[10px] leading-relaxed text-faint">
        <p>
            Billed at {{ \App\Support\Money::format($invoice->lines->first()?->unit_price ?? 0, true) }} per student per month
            for the {{ $invoice->academicYear?->name ?? 'current' }} academic year.
            Part months are charged on the basis of {{ strtolower($invoice->proration_method->label()) }}.
        </p>
        <p class="mt-2">Payment is due by {{ $invoice->due_date->format('j F Y') }}.</p>
    </div>
</div>

</body>
</html>
