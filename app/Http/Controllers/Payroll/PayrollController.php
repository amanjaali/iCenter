<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Services\Accounting\PostingException;
use App\Services\Payroll\PayrollService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Scope of work §6.6 and §8.3. */
class PayrollController extends Controller
{
    public function __construct(private readonly PayrollService $payroll) {}

    public function index()
    {
        $this->authorize('view-financials');

        return view('payroll.index', [
            'runs' => PayrollRun::withCount('lines')->orderByDesc('payroll_month')->paginate(24),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('manage-payroll');

        $month = $request->filled('month')
            ? Carbon::parse($request->string('month')->toString())->startOfMonth()
            : now()->startOfMonth();

        try {
            $run = $this->payroll->prepare($month);
        } catch (PostingException $e) {
            return back()->withErrors(['posting' => $e->getMessage()]);
        }

        return redirect()->route('payroll.show', $run)->with(
            $run->wasRecentlyCreated ? 'success' : 'info',
            $run->wasRecentlyCreated
                ? "Draft payroll prepared for {$month->format('F Y')} from the employee register."
                : "Payroll for {$month->format('F Y')} already exists."
        );
    }

    public function show(PayrollRun $payroll)
    {
        $this->authorize('view-financials');

        $payroll->load(['lines.employee', 'lines.department', 'lines.salaryAccount', 'journal', 'payments', 'period']);

        return view('payroll.show', [
            'run' => $payroll,
            'cashAccounts' => Account::where('is_cash_equivalent', true)->postable()->orderBy('code')->get(),
        ]);
    }

    public function updateLine(Request $request, PayrollLine $line)
    {
        $this->authorize('manage-payroll');

        $data = $request->validate([
            'base_salary' => ['required', 'numeric', 'min:0'],
            'overtime' => ['nullable', 'numeric', 'min:0'],
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'allowances' => ['nullable', 'numeric', 'min:0'],
            'employee_ss' => ['nullable', 'numeric', 'min:0'],
            'income_tax' => ['nullable', 'numeric', 'min:0'],
            'advances_deducted' => ['nullable', 'numeric', 'min:0'],
            'other_deductions' => ['nullable', 'numeric', 'min:0'],
            'employer_ss' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);

        try {
            $this->payroll->updateLine($line, $data);
        } catch (PostingException $e) {
            return back()->withErrors(['posting' => $e->getMessage()]);
        }

        return back()->with('success', "{$line->employee->name}'s line has been updated.");
    }

    public function approve(PayrollRun $payroll)
    {
        $this->authorize('manage-payroll');

        try {
            $this->payroll->approve($payroll);
        } catch (PostingException $e) {
            return back()->withErrors(['posting' => $e->getMessage()]);
        }

        return back()->with('success', 'The payroll run has been approved and is ready to post.');
    }

    public function post(PayrollRun $payroll)
    {
        $this->authorize('manage-payroll');

        try {
            $this->payroll->post($payroll);
        } catch (PostingException $e) {
            return back()->withErrors(['posting' => $e->getMessage()]);
        }

        return back()->with('success', "Payroll {$payroll->reference} has been posted to the ledger.");
    }

    public function pay(Request $request, PayrollRun $payroll)
    {
        $this->authorize('manage-payroll');

        $data = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:1'],
            'source_account_id' => ['required', 'exists:accounts,id'],
            'method' => ['required', 'in:cash,bank,transfer'],
        ]);

        try {
            $this->payroll->pay(
                $payroll,
                Carbon::parse($data['payment_date']),
                (float) $data['amount'],
                Account::findOrFail($data['source_account_id']),
                $data['method'],
            );
        } catch (PostingException $e) {
            return back()->withErrors(['posting' => $e->getMessage()]);
        }

        return back()->with('success', Money::format($data['amount'], true).' in salaries has been paid.');
    }

    /** A printable payslip for one employee. */
    public function payslip(PayrollLine $line)
    {
        $this->authorize('view-financials');

        $line->load(['employee.department', 'run', 'salaryAccount']);

        return view('payroll.payslip', ['line' => $line]);
    }
}
