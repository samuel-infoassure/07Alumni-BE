<?php

namespace App\Http\Controllers\Api;

use App\Models\AccountCategory;
use App\Models\AccountTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingController extends ApiController
{
    public function index(Request $request)
    {
        $year = $request->integer('year', now()->year);
        $month = $request->integer('month', 0);

        $query = AccountTransaction::with(['category:id,name,type,color,icon', 'recorder:id,first_name,last_name'])
            ->whereYear('transaction_date', $year);

        if ($month > 0) {
            $query->whereMonth('transaction_date', $month);
        }

        $transactions = $query->orderByDesc('transaction_date')->get();

        $totalIncome = $transactions->where('type', 'income')->sum('amount');
        $totalExpense = $transactions->where('type', 'expense')->sum('amount');

        $monthlyBreakdown = AccountTransaction::select(
            DB::raw('MONTH(transaction_date) as month'),
            DB::raw('SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as income'),
            DB::raw('SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as expense')
        )
            ->whereYear('transaction_date', $year)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return $this->success([
            'transactions' => $transactions->toArray(),
            'summary' => [
                'total_income' => $totalIncome,
                'total_expense' => $totalExpense,
                'balance' => $totalIncome - $totalExpense,
            ],
            'monthly_breakdown' => $monthlyBreakdown->toArray(),
        ], 'Account data loaded.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:account_categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'type' => ['required', 'in:income,expense'],
            'description' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
            'transaction_date' => ['required', 'date'],
        ]);

        $transaction = AccountTransaction::create([
            ...$validated,
            'recorded_by' => $request->user()->id,
        ]);

        return $this->success($transaction->toArray(), 'Transaction recorded.', 201);
    }

    public function categories()
    {
        $categories = AccountCategory::withCount('transactions')->get();

        return $this->success($categories->toArray(), 'Categories loaded.');
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:income,expense'],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:100'],
        ]);

        $category = AccountCategory::create($validated);

        return $this->success($category->toArray(), 'Category created.', 201);
    }
}
