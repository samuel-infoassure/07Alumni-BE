<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuesSchedule extends Model
{
    protected $fillable = [
        'user_id',
        'due_year',
        'due_month',
        'amount',
        'due_date',
        'status',
        'dues_payment_id',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'due_year' => 'integer',
            'due_month' => 'integer',
            'amount' => 'float',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(DuesPayment::class, 'dues_payment_id');
    }
}
