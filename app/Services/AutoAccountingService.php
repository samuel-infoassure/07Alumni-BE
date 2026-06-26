<?php

namespace App\Services;

use App\Models\AccountCategory;
use App\Models\AccountTransaction;

class AutoAccountingService
{
    public static function recordIncome(
        string $categoryName,
        float $amount,
        int $recordedBy,
        string $description,
        string $reference,
        ?string $date = null
    ): void {
        $category = AccountCategory::where('name', $categoryName)->first();

        AccountTransaction::create([
            'category_id'      => $category?->id,
            'recorded_by'      => $recordedBy,
            'amount'           => $amount,
            'type'             => 'income',
            'description'      => $description,
            'reference'        => $reference,
            'transaction_date' => $date ?? now()->toDateString(),
        ]);
    }
}
