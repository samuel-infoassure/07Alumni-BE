<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlumniProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        // Personal Information
        'nick_name',
        'gender',
        'birthday',
        // Contact Details
        'city',
        'state',
        'latitude',
        'longitude',
        'next_of_kin',
        'kin_relationship',
        'kin_phone',
        // Academic Details
        'department',
        'matric_number',
        'graduation_year',
        // Professional Details
        'current_employer',
        'current_position',
        // Declaration
        'accepted_constitution',
        'committed_dues',
        'consented_data',
        // Legacy / misc
        'address',
        'bio',
        'profile_photo',
        'linkedin_url',
        'twitter_url',
        'membership_status',
    ];

    protected function casts(): array
    {
        return [
            'graduation_year' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'accepted_constitution' => 'boolean',
            'committed_dues' => 'boolean',
            'consented_data' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
