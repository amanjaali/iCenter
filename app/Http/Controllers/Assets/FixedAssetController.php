<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\DepreciationRun;
use App\Models\FixedAsset;
use App\Services\Accounting\PostingException;
use App\Services\Assets\DepreciationService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Scope of work §7 — capex capitalised and depreciated, not expensed. */
class FixedAssetController extends Controller
{
    public function __construct(private readonly DepreciationService $depreciation) {}

    public function index(Request $request)
    {
        $this->authorize('view-financials');

        $assets = FixedAsset::query()
            ->with(['assetAccount', 'department', 'project'])
            ->when($request->string('category')->toString(), fn ($q, $c) => $q->where('category', $c))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('code')
            ->paginate(30)
            ->withQueryString();

        $all = FixedAsset::all();

        return view('assets.index', [
            'assets' => $assets,
            'totals' => [
                'cost' => Money::round($all->sum('cost')),
                'accumulated' => Money::round($all->sum('accumulated_depreciation')),
                'net_book_value' => Money::round($all->sum(fn (FixedAsset $a) => $a->netBookValue())),
                'monthly_charge' => Money::round($all->where('status', 'active')->sum(fn (FixedAsset $a) => $a->monthlyCharge())),
            ],
            'runs' => DepreciationRun::orderByDesc('run_month')->limit(12)->get(),
        ]);
    }

    public function show(FixedAsset $asset)
    {
        $this->authorize('view-financials');

        $asset->load([
            'assetAccount', 'depreciationExpenseAccount', 'department', 'project',
            'depreciationLines.run',
        ]);

        return view('assets.show', [
            'asset' => $asset,
            'cashAccounts' => Account::where('is_cash_equivalent', true)->postable()->orderBy('code')->get(),
        ]);
    }

    /** Prepare the month's depreciation schedule. */
    public function prepareRun(Request $request)
    {
        $this->authorize('manage-assets');

        $month = $request->filled('month')
            ? Carbon::parse($request->string('month')->toString())->startOfMonth()
            : now()->startOfMonth();

        $run = $this->depreciation->prepare($month);

        if ($run->lines()->doesntExist()) {
            return back()->with('warning', 'No asset has depreciation due for '.$month->format('F Y').'.');
        }

        return redirect()->route('assets.run', $run);
    }

    public function showRun(DepreciationRun $run)
    {
        $this->authorize('view-financials');

        $run->load(['lines.asset.depreciationExpenseAccount', 'journal', 'period']);

        return view('assets.run', compact('run'));
    }

    public function postRun(DepreciationRun $run)
    {
        $this->authorize('manage-assets');

        try {
            $this->depreciation->post($run);
        } catch (PostingException $e) {
            return back()->withErrors(['posting' => $e->getMessage()]);
        }

        return back()->with('success', 'Depreciation for '.$run->run_month->format('F Y').' has been posted.');
    }

    public function dispose(Request $request, FixedAsset $asset)
    {
        $this->authorize('manage-assets');

        $data = $request->validate([
            'disposal_date' => ['required', 'date'],
            'proceeds' => ['required', 'numeric', 'min:0'],
            'proceeds_account_id' => ['required', 'exists:accounts,id'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->depreciation->dispose(
                $asset,
                Carbon::parse($data['disposal_date']),
                (float) $data['proceeds'],
                Account::findOrFail($data['proceeds_account_id']),
                $data['reason'],
            );
        } catch (PostingException $e) {
            return back()->withErrors(['posting' => $e->getMessage()]);
        }

        return back()->with('success', "{$asset->name} has been disposed of and removed from the register.");
    }
}
