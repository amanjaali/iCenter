@php
    /**
     * Navigation is filtered by ability, not merely disabled: an operations
     * user should not see a Reports section they may not open (§9.2).
     */
    $sections = [
        [
            'label' => null,
            'items' => [
                ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'grid', 'can' => null],
            ],
        ],
        [
            'label' => 'Registry',
            'items' => [
                ['route' => 'bus-companies.index', 'label' => 'Bus companies', 'icon' => 'building', 'can' => 'view-registry', 'active' => 'bus-companies.*'],
                ['route' => 'buses.index', 'label' => 'Buses', 'icon' => 'bus', 'can' => 'view-registry', 'active' => 'buses.*'],
                ['route' => 'students.index', 'label' => 'Students', 'icon' => 'users', 'can' => 'view-registry', 'active' => 'students.*'],
            ],
        ],
        [
            'label' => 'Revenue',
            'items' => [
                ['route' => 'invoices.index', 'label' => 'Invoices', 'icon' => 'file', 'can' => 'view-financials', 'active' => 'invoices.*'],
                ['route' => 'payments.index', 'label' => 'Receipts', 'icon' => 'wallet', 'can' => 'view-financials', 'active' => 'payments.*'],
                ['route' => 'rates.index', 'label' => 'Rates', 'icon' => 'tag', 'can' => 'view-rates', 'active' => 'rates.*'],
                ['route' => 'academic-years.index', 'label' => 'Academic years', 'icon' => 'calendar', 'can' => 'view-rates', 'active' => 'academic-years.*'],
            ],
        ],
        [
            'label' => 'Costs',
            'items' => [
                ['route' => 'expenses.index', 'label' => 'Expenses', 'icon' => 'receipt', 'can' => 'view-financials', 'active' => 'expenses.*'],
                ['route' => 'suppliers.index', 'label' => 'Suppliers', 'icon' => 'truck', 'can' => 'view-financials', 'active' => 'suppliers.*'],
                ['route' => 'payroll.index', 'label' => 'Payroll', 'icon' => 'badge', 'can' => 'view-financials', 'active' => 'payroll.*'],
                ['route' => 'employees.index', 'label' => 'Employees', 'icon' => 'user-square', 'can' => 'view-financials', 'active' => 'employees.*'],
                ['route' => 'assets.index', 'label' => 'Fixed assets', 'icon' => 'box', 'can' => 'view-financials', 'active' => 'assets.*'],
            ],
        ],
        [
            'label' => 'Ledger',
            'items' => [
                ['route' => 'journals.index', 'label' => 'Journals', 'icon' => 'book', 'can' => 'view-financials', 'active' => 'journals.*'],
                ['route' => 'accounts.index', 'label' => 'Chart of accounts', 'icon' => 'list', 'can' => 'view-financials', 'active' => 'accounts.*'],
                ['route' => 'periods.index', 'label' => 'Periods', 'icon' => 'lock', 'can' => 'view-financials', 'active' => 'periods.*'],
            ],
        ],
        [
            'label' => 'Partners',
            'items' => [
                ['route' => 'revenue-share.index', 'label' => 'Revenue share', 'icon' => 'split', 'can' => 'view-financials', 'active' => 'revenue-share.*'],
                ['route' => 'distributions.index', 'label' => 'Distributions', 'icon' => 'pie', 'can' => 'view-financials', 'active' => 'distributions.*'],
                ['route' => 'partners.index', 'label' => 'Partners', 'icon' => 'handshake', 'can' => 'view-financials', 'active' => 'partners.*'],
            ],
        ],
        [
            'label' => 'Reports',
            'items' => [
                ['route' => 'reports.index', 'label' => 'All reports', 'icon' => 'chart', 'can' => 'view-reports', 'active' => 'reports.*'],
            ],
        ],
        [
            'label' => 'Administration',
            'items' => [
                ['route' => 'users.index', 'label' => 'Users', 'icon' => 'shield', 'can' => 'manage-users', 'active' => 'users.*'],
                ['route' => 'settings.edit', 'label' => 'Settings', 'icon' => 'sliders', 'can' => 'manage-settings', 'active' => 'settings.*'],
                ['route' => 'audit.index', 'label' => 'Audit trail', 'icon' => 'history', 'can' => 'view-audit-trail', 'active' => 'audit.*'],
            ],
        ],
    ];

    $period = \App\Models\Period::forDate(now());
    $user = auth()->user();
@endphp

{{-- Brand lockup: symbol, wordmark, then the product name behind a hairline
     rule — never restyled, never bolder than the wordmark (brand rules 1-2). --}}
<a href="{{ route('dashboard') }}" class="mb-6 flex items-center gap-[11px] px-2">
    <svg width="24" height="24" viewBox="0 0 120 120" fill="none" class="shrink-0" aria-hidden="true">
        <path d="M54 10 L54 86 L14 108 Z" fill="#FFFFFF"/>
        <path d="M66 10 L106 108 L66 86 Z" fill="#4FE3FF"/>
    </svg>
    <span class="flex flex-col gap-1">
        <span class="font-display text-[15px] font-medium leading-none tracking-[0.06em] text-white">ALLVA</span>
        <span class="text-[6px] font-medium leading-none tracking-[0.34em] text-dark-muted ps-[0.34em]">ACCOUNTING</span>
    </span>
</a>

<nav class="flex-1 overflow-y-auto pb-4">
    @foreach ($sections as $section)
        @php
            $visible = collect($section['items'])
                ->filter(fn ($item) => ! $item['can'] || auth()->user()->can($item['can']));
        @endphp

        @if ($visible->isNotEmpty())
            @if ($section['label'])
                <div class="nav-section">{{ $section['label'] }}</div>
            @endif

            <div class="flex flex-col gap-0.5">
                @foreach ($visible as $item)
                    @php $active = request()->routeIs($item['active'] ?? $item['route']); @endphp
                    <a href="{{ route($item['route']) }}"
                       class="nav-link {{ $active ? 'nav-link-active' : '' }}"
                       @if ($active) aria-current="page" @endif>
                        <x-icon :name="$item['icon']" class="size-[15px] shrink-0"/>
                        <span class="truncate">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    @endforeach
</nav>

<div class="mt-auto flex flex-col gap-3.5 pt-3">
    @if ($period && $user->can('view-financials'))
        <a href="{{ route('periods.index') }}"
           class="flex flex-col gap-2 rounded-md border border-dark-border p-3.5 transition-colors hover:border-navy-700">
            <span class="eyebrow !text-dark-muted">Period</span>
            <span class="text-xs text-white">
                {{ $period->shortLabel() }} · {{ $period->status->value }}
            </span>
            @php
                // How far through the month we are — a quiet cue that month end
                // is approaching.
                $progress = min(100, (int) round(now()->day / $period->end_date->day * 100));
            @endphp
            <span class="block h-[3px] rounded-sm bg-dark-border">
                <span class="block h-[3px] rounded-sm bg-signal" style="width: {{ $period->isOpen() ? $progress : 100 }}%"></span>
            </span>
        </a>
    @endif

    <form method="POST" action="{{ route('logout') }}" class="contents">
        @csrf
        <div class="flex items-center gap-2.5 p-2">
            <span class="flex size-[30px] shrink-0 items-center justify-center rounded-full bg-navy-700
                         font-display text-xs font-medium text-signal-light">
                {{ $user->initials() }}
            </span>
            <span class="flex min-w-0 flex-col gap-0.5">
                <span class="truncate text-xs text-white">{{ $user->name }}</span>
                <span class="truncate text-[10px] text-dark-muted">{{ $user->role->label() }}</span>
            </span>
            <button type="submit" class="ms-auto text-dark-muted transition-colors hover:text-white"
                    aria-label="Sign out" title="Sign out">
                <x-icon name="logout" class="size-4"/>
            </button>
        </div>
    </form>
</div>
