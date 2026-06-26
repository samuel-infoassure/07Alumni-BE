<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DuesPayment extends Model
{
    protected $fillable = [
        'user_id',
        'paystack_reference',
        'amount_kobo',
        'months_count',
        'schedule_ids',
        'status',
        'paid_at',
        'paystack_data',
    ];

    protected function casts(): array
    {
        return [
            'schedule_ids' => 'array',
            'paystack_data' => 'array',
            'paid_at' => 'datetime',
            'amount_kobo' => 'integer',
            'months_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(DuesSchedule::class);
    }

    public function getAmountNairaAttribute(): float
    {
        return $this->amount_kobo / 100;
    }
}
