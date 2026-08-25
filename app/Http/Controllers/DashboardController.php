<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\BusCompany;
use App\Models\Invoice;
use App\Models\Period;
use App\Models\Student;
use App\Services\Accounting\LedgerService;
use App\Services\Billing\BillingService;
use App\Services\Billing\RevenueRecognitionService;
use App\Services\Partners\DistributionService;
use App\Services\Reports\OperationalReportService;
use App\Services\SettingsService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly BillingService $billing,
        private readonly OperationalReportService $reports,
        private readonly SettingsService $settings,
        private readonly DistributionService $distributions,
    ) {}

    public function __invoke()
    {
        $user = Auth::user();

        // Operations may not see money at all (§9.2), so they get a registry
        // dashboard rather than a redacted financial one.
        if ($user->role === UserRole::Operations) {
            return view('dashboard.operations', $this->registrySnapshot());
        }

        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();
        $yearStart = $this->ledger->fiscalYearStart($today);

        $thisMonth = $this->ledger->profitSummary($monthStart, $monthEnd);
        $lastMonth = $this->ledger->profitSummary(
            $monthStart->copy()->subMonth(),
            $monthStart->copy()->subMonth()->endOfMonth(),
        );
        $yearToDate = $this->ledger->profitSummary($yearStart, $today);

        return view('dashboard.index', [
            'period' => Period::forDate($today),
            'thisMonth' => $thisMonth,
            'lastMonth' => $lastMonth,
            'yearToDate' => $yearToDate,
            'revenueChange' => $this->percentChange($thisMonth['revenue'], $lastMonth['revenue']),
            'profitChange' => $this->percentChange($thisMonth['net_profit'], $lastMonth['net_profit']),
            'cash' => $this->ledger->cashPosition($today),
            'cashSeries' => $this->cashSeries($today),
            'receivables' => $this->reports->receivablesAgeing($today),
            'attention' => $this->attentionItems($today),
            'activeStudents' => Student::active()->count(),
            'busCompanies' => BusCompany::active()->count(),
            'recentInvoices' => Invoice::with('busCompany')->issued()->latest('issue_date')->limit(6)->get(),
            'partnerShare' => $this->distributions->calculate($yearStart, $today),
            'sharePartner' => $this->settings->revenueSharePartnerName(),
        ]);
    }

    /** Twelve months of cash and profit for the dashboard chart. */
    private function cashSeries(Carbon $today): array
    {
        $series = [];
        $cursor = $today->copy()->startOfMonth()->subMonths(11);

        while ($cursor->lessThanOrEqualTo($today)) {
            $monthEnd = $cursor->copy()->endOfMonth();
            $summary = $this->ledger->profitSummary($cursor->copy()->startOfMonth(), $monthEnd);

            $series[] = [
                'label' => $cursor->format('M'),
                'full_label' => $cursor->format('F Y'),
                'cash' => $this->ledger->cashPosition($monthEnd->min($today)),
                'revenue' => $summary['revenue'],
                'costs' => Money::round($summary['direct_costs'] + $summary['operating_expenses']),
            ];

            $cursor->addMonth();
        }

        return $series;
    }

    /**
     * What an accountant needs to act on. Deliberately specific: a count with
     * no link is a nag, not a to-do.
     */
    private function attentionItems(Carbon $today): array
    {
        $items = [];

        $missing = $this->billing->companiesMissingInvoice($today->copy()->startOfMonth());

        if ($missing->isNotEmpty()) {
            $items[] = [
                'title' => $missing->count().' bus '.str('company')->plural($missing->count()).' not yet invoiced',
                'meta' => $today->format('F Y').' — '.$missing->take(3)->pluck('name')->implode(', ')
                    .($missing->count() > 3 ? ' and others' : ''),
                'href' => route('invoices.generate'),
            ];
        }

        $overdue = Invoice::outstanding()->whereDate('due_date', '<', $today)->get();

        if ($overdue->isNotEmpty()) {
            $items[] = [
                'title' => $overdue->count().' overdue '.str('invoice')->plural($overdue->count()),
                'meta' => Money::format($overdue->sum('balance_due'), true).' past its due date',
                'href' => route('reports.receivables-ageing'),
            ];
        }

        $deferred = app(RevenueRecognitionService::class)->due($today);

        if ($deferred->isNotEmpty()) {
            $items[] = [
                'title' => $deferred->count().' '.str('invoice')->plural($deferred->count()).' ready to recognise',
                'meta' => 'Deferred revenue whose month has now arrived',
                'href' => route('invoices.index', ['status' => 'deferred']),
            ];
        }

        $drafts = Invoice::where('status', 'draft')->count();

        if ($drafts > 0) {
            $items[] = [
                'title' => $drafts.' draft '.str('invoice')->plural($drafts).' not yet issued',
                'meta' => 'Generated but not posted to the ledger',
                'href' => route('invoices.index', ['status' => 'draft']),
            ];
        }

        $ownership = $this->distributions->totalOwnership();

        if (abs($ownership - 100) > 0.0001) {
            $items[] = [
                'title' => 'Partner ownership totals '.rtrim(rtrim(number_format($ownership, 4), '0'), '.').'%',
                'meta' => 'It should total 100% before a distribution is declared',
                'href' => route('partners.index'),
            ];
        }

        return $items;
    }

    private function registrySnapshot(): array
    {
        return [
            'busCompanies' => BusCompany::active()->count(),
            'buses' => \App\Models\Bus::where('status', 'active')->count(),
            'activeStudents' => Student::active()->count(),
            'joinedThisMonth' => \App\Models\StudentEnrollment::whereBetween('start_date', [
                now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString(),
            ])->count(),
            'leftThisMonth' => \App\Models\StudentEnrollment::whereNotNull('end_date')->whereBetween('end_date', [
                now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString(),
            ])->count(),
            'companies' => BusCompany::withCount(['buses', 'students' => fn ($q) => $q->where('status', 'active')])
                ->active()->orderBy('name')->get(),
        ];
    }

    private function percentChange(float $current, float $previous): ?float
    {
        if (abs($previous) < 0.005) {
            return null;
        }

        return round(($current - $previous) / abs($previous) * 100, 1);
    }
}
