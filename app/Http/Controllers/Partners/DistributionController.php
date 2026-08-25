<?php

namespace App\Http\Controllers\Partners;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\DistributionLine;
use App\Models\DistributionRun;
use App\Services\Accounting\PostingException;
use App\Services\Partners\DistributionService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Scope of work §5.2 — distributing net profit to the partners. */
class DistributionController extends Controller
{
    public function __construct(private readonly DistributionService $distributions) {}

    public function index()
    {
        $this->authorize('view-financials');

        return view('partners.distributions.index', [
            'runs' => DistributionRun::with('lines.partner')->orderByDesc('period_end')->paginate(20),
            'totalOwnership' => $this->distributions->totalOwnership(),
            'totals' => [
                'declared' => Money::round(DistributionRun::where('status', '!=', 'draft')->sum('distributable_amount')),
                'paid' => Money::round(DistributionRun::where('status', '!=', 'draft')->sum('distributed_amount')),
            ],
        ]);
    }

    /** The preview screen: what is distributable for a chosen window. */
    public function create(Request $request)
    {
        $this->authorize('manage-distributions');

        $from = $request->filled('from')
            ? Carbon::parse($request->string('from')->toString())->startOfDay()
            : now()->startOfYear();

        $to = $request->filled('to')
            ? Carbon::parse($request->string('to')->toString())->endOfDay()
            : now()->endOfMonth();

        $retained = (float) $request->input('retained', 0);

        return view('partners.distributions.create', [
            'from' => $from,
            'to' => $to,
            'retained' => $retained,
            'calculation' => $this->distributions->calculate($from, $to, $retained),
            'totalOwnership' => $this->distributions->totalOwnership(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-distributions');

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'retained' => ['nullable', 'numeric', 'min:0'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $run = $this->distributions->prepare(
            Carbon::parse($data['from'])->startOfDay(),
            Carbon::parse($data['to'])->endOfDay(),
            (float) ($data['retained'] ?? 0),
            $data['title'] ?? null,
        );

        return redirect()->route('distributions.show', $run)
            ->with('success', 'Draft distribution prepared. Review each partner’s share before posting.');
    }

    public function show(DistributionRun $distribution)
    {
        $this->authorize('view-financials');

        $distribution->load(['lines.partner', 'lines.payouts.sourceAccount', 'journal.lines.account', 'period']);

        return view('partners.distributions.show', [
            'run' => $distribution,
            'cashAccounts' => Account::where('is_cash_equivalent', true)->postable()->orderBy('code')->get(),
        ]);
    }

    public function post(DistributionRun $distribution)
    {
        $this->authorize('manage-distributions');

        try {
            $this->distributions->post($distribution);
        } catch (PostingException $e) {
            return back()->withErrors(['posting' => $e->getMessage()]);
        }

        return back()->with('success', "Distribution {$distribution->reference} has been declared and posted.");
    }

    public function payout(Request $request, DistributionLine $line)
    {
        $this->authorize('manage-distributions');

        $data = $request->validate([
            'payout_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:1'],
            'source_account_id' => ['required', 'exists:accounts,id'],
            'method' => ['required', 'in:cash,bank,transfer'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $this->distributions->payout(
                $line,
                Carbon::parse($data['payout_date']),
                (float) $data['amount'],
                Account::findOrFail($data['source_account_id']),
                $data['method'],
                $data['reference'] ?? null,
            );
        } catch (PostingException $e) {
            return back()->withErrors(['posting' => $e->getMessage()]);
        }

        return back()->with('success', Money::format($data['amount'], true)
            .' paid to '.$line->partner->name.'.');
    }

    public function destroy(DistributionRun $distribution)
    {
        $this->authorize('manage-distributions');

        if (! $distribution->isDraft()) {
            return back()->with('error', 'A posted distribution cannot be deleted. Reverse its journal instead.');
        }

        $reference = $distribution->reference;
        $distribution->delete();

        return redirect()->route('distributions.index')->with('success', "Draft distribution {$reference} has been deleted.");
    }
}
