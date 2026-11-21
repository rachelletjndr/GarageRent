<?php

namespace Database\Factories;

use App\Enums\StatusGarasiEnum;
use App\Enums\TipeGarasiEnum;
use App\Models\Garasi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Garasi>
 */
class GarasiFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Garasi>
     */
    protected $model = Garasi::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 'S'.str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'tipe_garasi' => TipeGarasiEnum::Sepeda,
            'harga_sewa_harian' => 20000.00,
            'tarif_kwh' => null,
            'status' => StatusGarasiEnum::Available,
        ];
    }

    /**
     * State for Sepeda garage ('S' prefix).
     */
    public function sepeda(?string $id = null, float $hargaHarian = 20000.00): static
    {
        return $this->state(fn (array $attributes): array => [
            'id' => $id ?? 'S'.str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'tipe_garasi' => TipeGarasiEnum::Sepeda,
            'harga_sewa_harian' => $hargaHarian,
            'tarif_kwh' => null,
        ]);
    }

    /**
     * State for Mobil Bensin garage ('C' prefix).
     */
    public function mobil(?string $id = null, float $hargaHarian = 50000.00): static
    {
        return $this->state(fn (array $attributes): array => [
            'id' => $id ?? 'C'.str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'tipe_garasi' => TipeGarasiEnum::Mobil,
            'harga_sewa_harian' => $hargaHarian,
            'tarif_kwh' => null,
        ]);
    }

    /**
     * State for Mobil Listrik garage ('E' prefix).
     */
    public function mobilListrik(?string $id = null, float $hargaHarian = 100000.00, float $tarifKwh = 2000.00): static
    {
        return $this->state(fn (array $attributes): array => [
            'id' => $id ?? 'E'.str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'tipe_garasi' => TipeGarasiEnum::MobilListrik,
            'harga_sewa_harian' => $hargaHarian,
            'tarif_kwh' => $tarifKwh,
        ]);
    }

    /**
     * Indicate that the garage is currently used.
     */
    public function used(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusGarasiEnum::Used,
        ]);
    }

    /**
     * Indicate that the garage is currently available.
     */
    public function available(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusGarasiEnum::Available,
        ]);
    }
}
