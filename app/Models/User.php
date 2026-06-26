<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'email',
        'password',
        'api_token',
        'first_name',
        'last_name',
        'phone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
    ];

    /**
     * Virtual full-name accessor derived from the users table columns.
     * Falls back to the email prefix when first/last name is not yet set.
     */
    public function getNameAttribute(): string
    {
        $first = trim($this->first_name ?? '');
        $last = trim($this->last_name ?? '');
        $full = trim("$first $last");

        return $full ?: explode('@', $this->email)[0];
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function profile(): HasOne
    {
        return $this->hasOne(AlumniProfile::class);
    }

    public function meetingAttendances(): HasMany
    {
        return $this->hasMany(MeetingAttendance::class);
    }

    public function eventRegistrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function excoRoles(): HasMany
    {
        return $this->hasMany(Exco::class);
    }

    public function chatGroups(): BelongsToMany
    {
        return $this->belongsToMany(ChatGroup::class, 'chat_group_members', 'user_id', 'group_id')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function accountTransactions(): HasMany
    {
        return $this->hasMany(AccountTransaction::class, 'recorded_by');
    }

    public function duesSchedules(): HasMany
    {
        return $this->hasMany(DuesSchedule::class);
    }

    public function duesPayments(): HasMany
    {
        return $this->hasMany(DuesPayment::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withTimestamps();
    }

    public function hasRole(string $role): bool
    {
        return $this->roles->contains('name', $role);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->roles->isEmpty()) {
            $this->load('roles.permissions');
        }

        if ($this->hasRole('super_admin')) {
            return true;
        }

        return $this->roles
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->contains($permission);
    }

    public function roleNames(): array
    {
        return $this->roles->pluck('name')->toArray();
    }

    public function permissionNames(): array
    {
        if ($this->roles->isEmpty()) {
            $this->load('roles.permissions');
        }

        if ($this->hasRole('super_admin')) {
            return ['*'];
        }

        return $this->roles
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->unique()
            ->values()
            ->toArray();
    }
}
