<?php

namespace App\Http\Controllers\Partners;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Partner;
use App\Models\User;
use App\Services\Partners\DistributionService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Scope of work §2 — the five partners and their configurable ownership. */
class PartnerController extends Controller
{
    public function __construct(private readonly DistributionService $distributions) {}

    public function index()
    {
        $this->authorize('view-financials');

        $partners = Partner::with(['user', 'capitalAccount'])
            ->orderBy('sort_order')->orderBy('name')->get();

        return view('partners.index', [
            'partners' => $partners,
            'totalOwnership' => $this->distributions->totalOwnership(),
            'outstanding' => $partners->mapWithKeys(
                fn (Partner $p) => [$p->id => $p->outstandingAmount()]
            ),
            'declared' => $partners->mapWithKeys(
                fn (Partner $p) => [$p->id => Money::round($p->distributionLines()->sum('share_amount'))]
            ),
        ]);
    }

    public function show(Partner $partner)
    {
        $this->authorize('view-financials');

        $partner->load([
            'user', 'capitalAccount', 'ownershipChanges.user',
            'distributionLines.run', 'payouts.sourceAccount',
        ]);

        return view('partners.show', [
            'partner' => $partner,
            'declared' => Money::round($partner->distributionLines->sum('share_amount')),
            'paid' => Money::round($partner->distributionLines->sum('paid_amount')),
            'outstanding' => Money::round($partner->distributionLines->sum('outstanding_amount')),
        ]);
    }

    public function create()
    {
        $this->authorize('manage-partners');

        return view('partners.form', $this->formData() + [
            'partner' => new Partner(['is_active' => true, 'ownership_percent' => 0, 'joined_on' => now()->toDateString()]),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-partners');

        $data = $this->validated($request);
        $partner = Partner::create(collect($data)->except('reason')->all());

        return redirect()->route('partners.index')->with('success', "{$partner->name} has been added.");
    }

    public function edit(Partner $partner)
    {
        $this->authorize('manage-partners');

        return view('partners.form', $this->formData() + ['partner' => $partner]);
    }

    public function update(Request $request, Partner $partner)
    {
        $this->authorize('manage-partners');

        $data = $this->validated($request, $partner);
        $newPercent = (float) $data['ownership_percent'];

        // The ownership percentage is not an ordinary field: §9.3 requires the
        // change to be logged with a reason and an effective date, so it goes
        // through the distribution service rather than a plain update.
        $partner->update(collect($data)->except(['ownership_percent', 'reason', 'effective_date'])->all());

        if (abs((float) $partner->ownership_percent - $newPercent) > 0.00005) {
            $this->distributions->changeOwnership(
                $partner,
                $newPercent,
                Carbon::parse($data['effective_date'] ?? now()),
                $data['reason'],
            );
        }

        return redirect()->route('partners.show', $partner)->with('success', "{$partner->name} has been updated.");
    }

    private function formData(): array
    {
        return [
            'capitalAccounts' => Account::where('subtype', 'partner_capital')->orderBy('code')->get(),
            'users' => User::where('role', UserRole::Partner->value)->orderBy('name')->get(),
        ];
    }

    private function validated(Request $request, ?Partner $partner = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:partners,code'.($partner ? ",{$partner->id}" : '')],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'ownership_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'user_id' => ['nullable', 'exists:users,id'],
            'capital_account_id' => ['nullable', 'exists:accounts,id'],
            'joined_on' => ['nullable', 'date'],
            'left_on' => ['nullable', 'date', 'after_or_equal:joined_on'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'effective_date' => ['nullable', 'date'],
            'reason' => ['required_with:ownership_percent', 'string', 'max:500'],
        ], [
            'reason.required_with' => 'A reason is required for any change to ownership (scope of work §9.3).',
        ]);
    }
}
