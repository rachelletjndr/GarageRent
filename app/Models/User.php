<?php

namespace App\Models;

use App\Enums\RoleEnum;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property RoleEnum $role
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Penyewa|null $penyewa
 */
#[Fillable(['name', 'email', 'password', 'role', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => RoleEnum::class,
        ];
    }

    /**
     * Check if the user has an administrator role.
     */
    public function isAdmin(): bool
    {
        return $this->role === RoleEnum::Admin;
    }

    /**
     * Check if the user has a penyewa (tenant) role.
     */
    public function isPenyewa(): bool
    {
        return $this->role === RoleEnum::Penyewa;
    }

    /**
     * Get the tenant profile associated with the user.
     *
     * @return HasOne<Penyewa, $this>
     */
    public function penyewa(): HasOne
    {
        return $this->hasOne(Penyewa::class);
    }

    /**
     * Get the user's clean two-letter initials (ignoring parenthetical suffixes).
     */
    public function initials(): string
    {
        $cleanName = trim((string) preg_replace('/\s*\([^)]*\)/', '', $this->name));
        $initials = Str::initials($cleanName !== '' ? $cleanName : $this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
