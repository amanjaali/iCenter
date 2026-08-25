<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\Period;
use App\Services\Accounting\PeriodService;
use Illuminate\Http\Request;

class PeriodController extends Controller
{
    public function __construct(private readonly PeriodService $periods) {}

    public function index()
    {
        $this->authorize('view-financials');

        $years = FiscalYear::with(['periods' => fn ($q) => $q->orderBy('start_date')])
            ->orderByDesc('start_date')
            ->get();

        // Draft counts drive the "cannot close yet" warning on each row.
        $drafts = Journal::where('status', 'draft')
            ->selectRaw('period_id, COUNT(*) as total')
            ->groupBy('period_id')
            ->pluck('total', 'period_id');

        $postings = Journal::posted()
            ->selectRaw('period_id, COUNT(*) as total, SUM(total_debit) as amount')
            ->groupBy('period_id')
            ->get()
            ->keyBy('period_id');

        return view('accounting.periods.index', compact('years', 'drafts', 'postings'));
    }

    public function createYear(Request $request)
    {
        $this->authorize('close-periods');

        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
        ]);

        $year = $this->periods->createFiscalYear($data['year']);

        return back()->with('success', "Fiscal year {$year->name} and its twelve periods are ready.");
    }

    public function close(Period $period)
    {
        $this->authorize('close-periods');

        $result = $this->periods->close($period);

        return back()->with($result['closed'] ? 'success' : 'warning', $result['message']);
    }

    public function reopen(Request $request, Period $period)
    {
        $this->authorize('close-periods');

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $result = $this->periods->reopen($period, $data['reason']);

        return back()->with($result['reopened'] ? 'success' : 'warning', $result['message']);
    }

    public function lock(Period $period)
    {
        $this->authorize('close-periods');

        $this->periods->lock($period);

        return back()->with('success', "Period {$period->label()} is locked and can no longer be reopened.");
    }
}
