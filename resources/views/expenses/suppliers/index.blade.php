@extends('layouts.app')
@section('title', 'Suppliers')
@section('subtitle', $suppliers->total().' SUPPLIERS')

@section('actions')
    @can('manage-expenses')
        <a href="{{ route('suppliers.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-3.5"/> Add supplier</a>
    @endcan
@endsection

@section('content')
    <form method="GET" class="card mb-4 flex flex-wrap items-end gap-3 p-3.5">
        <div class="min-w-[220px] flex-1">
            <label class="label" for="q">Search</label>
            <input id="q" name="q" class="input" value="{{ request('q') }}" placeholder="Name or code">
        </div>
        <button class="btn btn-primary"><x-icon name="search" class="size-3.5"/> Search</button>
        <a href="{{ route('suppliers.index') }}" class="btn btn-secondary">Reset</a>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Supplier</th><th>Contact</th><th>Terms</th>
                        <th class="num">Expenses</th><th class="num">Payable</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $supplier)
                        <tr>
                            <td>
                                <span class="font-medium">{{ $supplier->name }}</span>
                                <div class="eyebrow">{{ $supplier->code }}</div>
                            </td>
                            <td class="text-muted">
                                {{ $supplier->contact_name ?: '—' }}
                                @if ($supplier->phone)<div class="text-[11px] text-faint">{{ $supplier->phone }}</div>@endif
                            </td>
                            <td class="text-muted">{{ $supplier->payment_terms_days }} days</td>
                            <td class="num">{{ number_format($supplier->expenses_count) }}</td>
                            <td class="num"><x-money :value="$balances[$supplier->id] ?? 0" muted/></td>
                            <td><x-badge :tone="$supplier->is_active ? 'success' : 'neutral'" :label="$supplier->is_active ? 'Active' : 'Inactive'"/></td>
                            <td class="text-end">
                                @can('manage-expenses')
                                    <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-ghost btn-sm">
                                        <x-icon name="edit" class="size-3.5"/>
                                    </a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty title="No suppliers"
                            message="Suppliers make an unpaid expense chaseable through accounts payable."/></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($suppliers->hasPages())<div class="border-t border-rule px-5 py-3">{{ $suppliers->links() }}</div>@endif
    </div>
@endsection
