<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view-financials');

        $suppliers = Supplier::query()
            ->withCount('expenses')
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")
            ))
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        $balances = Supplier::whereIn('id', $suppliers->pluck('id'))->get()
            ->mapWithKeys(fn (Supplier $s) => [$s->id => $s->payableBalance()]);

        return view('expenses.suppliers.index', compact('suppliers', 'balances'));
    }

    public function create()
    {
        $this->authorize('manage-expenses');

        return view('expenses.suppliers.form', ['supplier' => new Supplier(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-expenses');

        $supplier = Supplier::create($this->validated($request));

        return redirect()->route('suppliers.index')->with('success', "{$supplier->name} has been added.");
    }

    public function edit(Supplier $supplier)
    {
        $this->authorize('manage-expenses');

        return view('expenses.suppliers.form', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $this->authorize('manage-expenses');

        $supplier->update($this->validated($request, $supplier));

        return redirect()->route('suppliers.index')->with('success', "{$supplier->name} has been updated.");
    }

    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:suppliers,code'.($supplier ? ",{$supplier->id}" : '')],
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'tax_number' => ['nullable', 'string', 'max:60'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:180'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
