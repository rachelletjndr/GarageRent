<?php

namespace Database\Factories;

use App\Enums\VerificationStatusEnum;
use App\Models\Penyewa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Penyewa>
 */
class PenyewaFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Penyewa>
     */
    protected $model = Penyewa::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nik' => fake()->unique()->numerify('317101##########'),
            'nama_lengkap' => fake()->name(),
            'no_telp' => fake()->numerify('0812########'),
            'status_verifikasi' => VerificationStatusEnum::Approved,
            'diverifikasi_oleh' => null,
            'tanggal_verifikasi' => now(),
            'catatan_penolakan' => null,
        ];
    }

    /**
     * Indicate that the tenant registration is pending verification.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status_verifikasi' => VerificationStatusEnum::Pending,
            'diverifikasi_oleh' => null,
            'tanggal_verifikasi' => null,
            'catatan_penolakan' => null,
        ]);
    }

    /**
     * Indicate that the tenant registration is approved.
     */
    public function approved(?User $admin = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status_verifikasi' => VerificationStatusEnum::Approved,
            'diverifikasi_oleh' => $admin?->id,
            'tanggal_verifikasi' => now(),
            'catatan_penolakan' => null,
        ]);
    }

    /**
     * Indicate that the tenant registration is rejected.
     */
    public function rejected(string $reason = 'NIK KTP tidak sesuai atau tidak terbaca.', ?User $admin = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status_verifikasi' => VerificationStatusEnum::Rejected,
            'diverifikasi_oleh' => $admin?->id,
            'tanggal_verifikasi' => now(),
            'catatan_penolakan' => $reason,
        ]);
    }
}
