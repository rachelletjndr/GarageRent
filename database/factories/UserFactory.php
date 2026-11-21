<?php

namespace Database\Factories;

use App\Enums\RoleEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Penyewa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => RoleEnum::Penyewa,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user has an admin role.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => RoleEnum::Admin,
        ]);
    }

    /**
     * Attach an approved Penyewa profile to the user.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function withApprovedPenyewa(array $attributes = []): static
    {
        return $this->afterCreating(function (User $user) use ($attributes): void {
            Penyewa::factory()->create(array_merge([
                'user_id' => $user->id,
                'nama_lengkap' => $user->name,
                'status_verifikasi' => VerificationStatusEnum::Approved,
                'tanggal_verifikasi' => now(),
            ], $attributes));
        });
    }

    /**
     * Attach a pending Penyewa profile to the user.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function withPendingPenyewa(array $attributes = []): static
    {
        return $this->afterCreating(function (User $user) use ($attributes): void {
            Penyewa::factory()->pending()->create(array_merge([
                'user_id' => $user->id,
                'nama_lengkap' => $user->name,
            ], $attributes));
        });
    }

    /**
     * Attach a rejected Penyewa profile to the user.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function withRejectedPenyewa(string $reason = 'NIK KTP tidak valid.', array $attributes = []): static
    {
        return $this->afterCreating(function (User $user) use ($reason, $attributes): void {
            Penyewa::factory()->rejected($reason)->create(array_merge([
                'user_id' => $user->id,
                'nama_lengkap' => $user->name,
            ], $attributes));
        });
    }
}
