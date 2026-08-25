<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BillingMode;
use App\Enums\OverheadAllocationMethod;
use App\Enums\ProrationMethod;
use App\Enums\RevenueShareBasis;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\RevenueShareRun;
use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Http\Request;

/**
 * Scope of work §10 — the three open decisions, plus the configuration §4.1 and
 * §7 require. Each is a switch here rather than a constant in the code.
 */
class SettingController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function edit()
    {
        $this->authorize('manage-settings');

        return view('admin.settings', [
            'settings' => Setting::orderBy('group')->orderBy('key')->get()->groupBy('group'),
            'basis' => $this->settings->revenueShareBasis(),
            'proration' => $this->settings->prorationMethod(),
            'allocation' => $this->settings->overheadAllocationMethod(),
            'basisOptions' => RevenueShareBasis::cases(),
            'prorationOptions' => ProrationMethod::cases(),
            'allocationOptions' => OverheadAllocationMethod::options(),
            'billingModeOptions' => BillingMode::options(),
            // Changing a decision does not restate what is already posted, and
            // saying so on the screen is better than a support call later.
            'postedInvoices' => Invoice::issued()->count(),
            'postedShares' => RevenueShareRun::where('status', '!=', 'draft')->count(),
        ]);
    }

    public function update(Request $request)
    {
        $this->authorize('manage-settings');

        $data = $request->validate([
            'revenue_share_basis' => ['required', 'in:gross,net'],
            'revenue_share_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'revenue_share_partner_name' => ['required', 'string', 'max:255'],
            'proration_method' => ['required', 'in:daily,full_month,half_month'],
            'invoice_due_days' => ['required', 'integer', 'min:0', 'max:180'],
            'default_billing_mode' => ['required', 'in:continue,pause'],
            'overhead_method' => ['required', 'in:revenue,equal,headcount,none'],
            'company_name' => ['required', 'string', 'max:255'],
            'company_legal_name' => ['nullable', 'string', 'max:255'],
            'company_tax_number' => ['nullable', 'string', 'max:60'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'company_phone' => ['nullable', 'string', 'max:32'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $reason = $data['reason'] ?? null;

        // Each write is audited individually, so the trail shows which decision
        // changed rather than "settings were saved".
        $map = [
            'revenue_share.basis' => $data['revenue_share_basis'],
            'revenue_share.percent' => $data['revenue_share_percent'],
            'revenue_share.partner_name' => $data['revenue_share_partner_name'],
            'billing.proration_method' => $data['proration_method'],
            'billing.invoice_due_days' => $data['invoice_due_days'],
            'billing.default_billing_mode' => $data['default_billing_mode'],
            'allocation.overhead_method' => $data['overhead_method'],
            'company.name' => $data['company_name'],
            'company.legal_name' => $data['company_legal_name'] ?? '',
            'company.tax_number' => $data['company_tax_number'] ?? '',
            'company.address' => $data['company_address'] ?? '',
            'company.phone' => $data['company_phone'] ?? '',
            'company.email' => $data['company_email'] ?? '',
        ];

        foreach ($map as $key => $value) {
            $this->settings->set($key, $value, $reason);
        }

        return redirect()->route('settings.edit')
            ->with('success', 'Settings saved. Entries already posted keep the rules that were in force when they were posted.');
    }
}
