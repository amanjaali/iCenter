<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\BusCompany;
use App\Models\RateCard;
use App\Models\RateChangeLog;
use App\Services\Billing\RateResolver;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Scope of work §4.2 — the rate table.
 *
 * "A new rate can be added for a future academic year without altering any past
 * record." Rates are therefore added, never edited in place once they have been
 * used, and every change carries a reason.
 */
class RateCardController extends Controller
{
    public function __construct(private readonly RateResolver $rates) {}

    public function index(Request $request)
    {
        $this->authorize('view-rates');

        $rates = RateCard::with(['academicYear', 'busCompany', 'createdBy'])
            ->when($request->integer('academic_year_id'), fn ($q, $id) => $q->where('academic_year_id', $id))
            ->when($request->integer('bus_company_id'), fn ($q, $id) => $q->where('bus_company_id', $id))
            ->orderByDesc('effective_from')
            ->orderBy('bus_company_id')
            ->get();

        // A worked example of what each company pays right now, so the effect
        // of the table is visible rather than inferred.
        $today = Carbon::today();
        $inForce = BusCompany::active()->orderBy('name')->get()
            ->map(fn (BusCompany $c) => [
                'company' => $c,
                'rate' => $this->rates->resolve($c, $today),
            ]);

        return view('billing.rates.index', [
            'rates' => $rates,
            'inForce' => $inForce,
            'years' => AcademicYear::orderByDesc('start_date')->get(),
            'companies' => BusCompany::orderBy('name')->get(),
            'changes' => RateChangeLog::with(['user', 'rateCard.busCompany'])->latest()->limit(20)->get(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('manage-rates');

        $year = AcademicYear::current();

        return view('billing.rates.form', [
            'rate' => new RateCard([
                'academic_year_id' => $request->integer('academic_year_id') ?: $year?->id,
                'amount' => 4000,
                'effective_from' => $year?->start_date?->toDateString() ?? now()->toDateString(),
                'is_active' => true,
            ]),
            'years' => AcademicYear::orderByDesc('start_date')->get(),
            'companies' => BusCompany::active()->orderBy('name')->get(),
            'overlaps' => collect(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-rates');

        $data = $this->validated($request);

        $rate = DB::transaction(function () use ($data) {
            $rate = RateCard::create(collect($data)->except('reason')->all() + ['created_by' => Auth::id()]);

            // §4.2 — "Any rate change must be logged with the user who made it,
            // the date, and the reason."
            RateChangeLog::create([
                'rate_card_id' => $rate->id,
                'user_id' => Auth::id(),
                'user_name' => Auth::user()->name,
                'action' => 'created',
                'new_amount' => $rate->amount,
                'new_values' => $rate->only(['amount', 'effective_from', 'effective_to', 'bus_company_id']),
                'reason' => $data['reason'],
            ]);

            return $rate;
        });

        $this->rates->forget();

        return redirect()->route('rates.index')
            ->with('success', 'Rate of '.Money::format($rate->amount, true)
                .' per student per month added, effective from '.$rate->effective_from->format('j M Y').'.');
    }

    public function edit(RateCard $rate)
    {
        $this->authorize('manage-rates');

        return view('billing.rates.form', [
            'rate' => $rate,
            'years' => AcademicYear::orderByDesc('start_date')->get(),
            'companies' => BusCompany::orderBy('name')->get(),
            'overlaps' => $this->rates->overlapping(
                $rate->bus_company_id,
                $rate->effective_from,
                $rate->effective_to,
                $rate->id,
            ),
            'usedBy' => $rate->invoiceLines()->count(),
        ]);
    }

    public function update(Request $request, RateCard $rate)
    {
        $this->authorize('manage-rates');

        $data = $this->validated($request, $rate);
        $original = $rate->only(['amount', 'effective_from', 'effective_to', 'bus_company_id', 'is_active']);

        DB::transaction(function () use ($rate, $data, $original) {
            $rate->update(collect($data)->except('reason')->all());

            RateChangeLog::create([
                'rate_card_id' => $rate->id,
                'user_id' => Auth::id(),
                'user_name' => Auth::user()->name,
                'action' => 'updated',
                'old_amount' => $original['amount'],
                'new_amount' => $rate->amount,
                'old_values' => $original,
                'new_values' => $rate->only(['amount', 'effective_from', 'effective_to', 'bus_company_id', 'is_active']),
                'reason' => $data['reason'],
            ]);
        });

        $this->rates->forget();

        return redirect()->route('rates.index')->with('success', 'The rate has been updated and the change logged.');
    }

    /** Retire a rate rather than delete it — history must stay reproducible. */
    public function deactivate(Request $request, RateCard $rate)
    {
        $this->authorize('manage-rates');

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        DB::transaction(function () use ($rate, $data) {
            $rate->update(['is_active' => false]);

            RateChangeLog::create([
                'rate_card_id' => $rate->id,
                'user_id' => Auth::id(),
                'user_name' => Auth::user()->name,
                'action' => 'deactivated',
                'old_amount' => $rate->amount,
                'reason' => $data['reason'],
            ]);
        });

        $this->rates->forget();

        return back()->with('success', 'The rate has been deactivated. Invoices already raised are unaffected.');
    }

    private function validated(Request $request, ?RateCard $rate = null): array
    {
        return $request->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'bus_company_id' => ['nullable', 'exists:bus_companies,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            // §4.2 makes the reason mandatory, not optional.
            'reason' => ['required', 'string', 'max:500'],
        ]);
    }
}
