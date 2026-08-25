<?php

namespace App\Http\Controllers\Partners;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Period;
use App\Models\Project;
use App\Models\RevenueShareRun;
use App\Services\Accounting\PostingException;
use App\Services\Partners\RevenueShareService;
use App\Services\SettingsService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Scope of work §5.1 and §10.1 — the Cyber Gate share. */
class RevenueShareController extends Controller
{
    public function __construct(
        private readonly RevenueShareService $shares,
        private readonly SettingsService $settings,
    ) {}

    public function index()
    {
        $this->authorize('view-financials');

        $runs = RevenueShareRun::with(['period', 'project', 'journal'])
            ->orderByDesc('period_id')
            ->paginate(24);

        // Months with revenue but no share run yet — the thing most likely to
        // be forgotten at month end.
        $currentPeriod = Period::forDate(now());

        return view('partners.revenue-share.index', [
            'runs' => $runs,
            'partnerName' => $this->settings->revenueSharePartnerName(),
            'basis' => $this->settings->revenueShareBasis(),
            'percent' => $this->settings->revenueSharePercent(),
            'currentPeriod' => $currentPeriod,
            'preview' => $currentPeriod
                ? $this->shares->calculate($currentPeriod, Project::default())
                : null,
            'totals' => [
                'declared' => Money::round(RevenueShareRun::where('status', '!=', 'draft')->sum('share_amount')),
                'paid' => Money::round(RevenueShareRun::where('status', '!=', 'draft')->sum('paid_amount')),
            ],
        ]);
    }

    public function prepare(Request $request)
    {
        $this->authorize('manage-revenue-share');

        $data = $request->validate([
            'period_id' => ['required', 'exists:periods,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
        ]);

        $run = $this->shares->prepare(
            Period::findOrFail($data['period_id']),
            isset($data['project_id']) ? Project::find($data['project_id']) : Project::default(),
        );

        return redirect()->route('revenue-share.show', $run);
    }

    public function show(RevenueShareRun $revenueShare)
    {
        $this->authorize('view-financials');

        $revenueShare->load(['period', 'project', 'journal.lines.account', 'payments.sourceAccount']);

        return view('partners.revenue-share.show', [
            'run' => $revenueShare,
            'cashAccounts' => Account::where('is_cash_equivalent', true)->postable()->orderBy('code')->get(),
        ]);
    }

    public function post(RevenueShareRun $revenueShare)
    {
        $this->authorize('manage-revenue-share');

        try {
            $this->shares->post($revenueShare);
        } catch (PostingException $e) {
            return back()->withErrors(['posting' => $e->getMessage()]);
        }

        return back()->with('success', "{$revenueShare->partner_name}'s share for "
            .$revenueShare->period->label().' has been posted.');
    }

    public function pay(Request $request, RevenueShareRun $revenueShare)
    {
        $this->authorize('manage-revenue-share');

        $data = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:1'],
            'source_account_id' => ['required', 'exists:accounts,id'],
            'method' => ['required', 'in:cash,bank,transfer'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $this->shares->pay(
                $revenueShare,
                Carbon::parse($data['payment_date']),
                (float) $data['amount'],
                Account::findOrFail($data['source_account_id']),
                $data['method'],
                $data['reference'] ?? null,
            );
        } catch (PostingException $e) {
            return back()->withErrors(['posting' => $e->getMessage()]);
        }

        return back()->with('success', Money::format($data['amount'], true)
            ." paid to {$revenueShare->partner_name}.");
    }
}
