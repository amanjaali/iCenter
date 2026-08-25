<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payslip · {{ $line->employee->name }} · {{ $line->run->payroll_month->format('F Y') }}</title>
    <link rel="icon" href="{{ asset('brand/icons/favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Sora:wght@300;400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>@page { size: A5; margin: 12mm; }</style>
</head>
<body class="bg-mist">

<div class="no-print mx-auto flex max-w-[148mm] justify-end px-6 pt-6">
    <button onclick="window.print()" class="btn btn-primary btn-sm">
        <x-icon name="print" class="size-3.5"/> Print
    </button>
</div>

<div class="mx-auto my-6 max-w-[148mm] bg-white p-8 shadow-sm print:my-0 print:p-0 print:shadow-none">
    <div class="flex items-start justify-between border-b-2 border-signal pb-4">
        <div class="flex items-center gap-2.5">
            <svg width="24" height="24" viewBox="0 0 120 120" fill="none">
                <path d="M54 10 L54 86 L14 108 Z" fill="#0A1F3D"/>
                <path d="M66 10 L106 108 L66 86 Z" fill="#00C2E8"/>
            </svg>
            <div>
                <div class="font-display text-[15px] font-medium leading-none tracking-[0.06em]">ALLVA</div>
                <div class="mt-1 text-[5px] font-medium tracking-[0.36em] text-faint ps-[0.36em]">ACCOUNTING</div>
            </div>
        </div>
        <div class="text-end">
            <div class="eyebrow">Payslip</div>
            <div class="mt-0.5 text-xs font-medium">{{ $line->run->payroll_month->format('F Y') }}</div>
        </div>
    </div>

    <div class="mt-5 grid grid-cols-2 gap-4 text-xs">
        @foreach ([
            'Employee' => $line->employee->name,
            'Code' => $line->employee->code,
            'Position' => $line->employee->position,
            'Cost centre' => $line->employee->department->name,
        ] as $label => $value)
            <div>
                <div class="eyebrow">{{ $label }}</div>
                <div class="mt-0.5">{{ $value ?: '—' }}</div>
            </div>
        @endforeach
    </div>

    <table class="table mt-6">
        <thead><tr><th>Earnings</th><th class="num">Amount</th></tr></thead>
        <tbody>
            @foreach ([
                'Base salary' => $line->base_salary,
                'Overtime' => $line->overtime,
                'Bonus' => $line->bonus,
                'Allowances' => $line->allowances,
            ] as $label => $amount)
                @if ((float) $amount > 0)
                    <tr><td>{{ $label }}</td><td class="num">{{ \App\Support\Money::format($amount) }}</td></tr>
                @endif
            @endforeach
        </tbody>
        <tfoot><tr><td>Gross pay</td><td class="num">{{ \App\Support\Money::format($line->gross) }}</td></tr></tfoot>
    </table>

    <table class="table mt-4">
        <thead><tr><th>Deductions</th><th class="num">Amount</th></tr></thead>
        <tbody>
            @foreach ([
                'Social security' => $line->employee_ss,
                'Income tax' => $line->income_tax,
                'Advances recovered' => $line->advances_deducted,
                'Other deductions' => $line->other_deductions,
            ] as $label => $amount)
                @if ((float) $amount > 0)
                    <tr><td>{{ $label }}</td><td class="num">({{ \App\Support\Money::format($amount) }})</td></tr>
                @endif
            @endforeach
            @if ($line->totalDeductions() <= 0)
                <tr><td colspan="2" class="text-faint">No deductions</td></tr>
            @endif
        </tbody>
        <tfoot><tr><td>Total deductions</td><td class="num">({{ \App\Support\Money::format($line->totalDeductions()) }})</td></tr></tfoot>
    </table>

    <div class="mt-6 flex items-center justify-between border-t-2 border-navy pt-3">
        <span class="font-display text-sm font-medium">Net pay</span>
        <span class="money text-base font-medium">{{ \App\Support\Money::format($line->net, true) }}</span>
    </div>

    <p class="mt-8 border-t border-rule pt-4 text-[10px] leading-relaxed text-faint">
        The employer additionally contributes {{ \App\Support\Money::format($line->employer_ss, true) }}
        in social security, which is not deducted from the pay above.
        Payslip generated {{ now()->format('j F Y') }}.
    </p>
</div>

</body>
</html>
