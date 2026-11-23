<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'pin',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'pin',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)->withTimestamps();
    }

    public function cashShifts(): HasMany
    {
        return $this->hasMany(CashShift::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['owner', 'admin'], true);
    }

    public function isKasir(): bool
    {
        return $this->role === 'kasir';
    }

    public function isGudang(): bool
    {
        return $this->role === 'gudang';
    }

    public function accessibleBranches(): Collection
    {
        if ($this->isOwner()) {
            return Branch::query()->where('is_active', true)->orderBy('code')->get();
        }

        return $this->branches()->where('is_active', true)->orderBy('code')->get();
    }

    public function canAccessBranch(int $branchId): bool
    {
        if ($this->isOwner()) {
            return true;
        }

        return $this->branches()->where('branches.id', $branchId)->exists();
    }

    public function canAccessModule(string $module): bool
    {
        return match ($this->role) {
            'owner' => true,
            'admin' => in_array($module, ['pos', 'products', 'inventory', 'purchasing', 'reports', 'settings'], true),
            'kasir' => in_array($module, ['pos'], true),
            'gudang' => in_array($module, ['products', 'inventory', 'purchasing'], true),
            default => false,
        };
    }

    public function verifyPin(string $pin): bool
    {
        if (! $this->pin) {
            return false;
        }

        return Hash::check($pin, $this->pin);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: ['U'];

        return strtoupper(collect($parts)->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode(''));
    }
}
