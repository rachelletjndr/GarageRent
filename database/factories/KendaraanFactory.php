<?php

namespace Database\Factories;

use App\Enums\TipeKendaraanEnum;
use App\Models\Kendaraan;
use App\Models\Penyewa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kendaraan>
 */
class KendaraanFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Kendaraan>
     */
    protected $model = Kendaraan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'penyewa_id' => Penyewa::factory(),
            'plat_nomor' => 'B '.fake()->unique()->numerify('####').' '.strtoupper(fake()->lexify('???')),
            'tipe_kendaraan' => TipeKendaraanEnum::SepedaMotor,
            'merek' => 'Honda Vario 160',
            'kapasitas_kwh' => null,
        ];
    }

    /**
     * State for Electric Vehicle (Mobil Listrik).
     */
    public function mobilListrik(float $kapasitasKwh = 50.0, string $merek = 'Hyundai Ioniq 5'): static
    {
        return $this->state(fn (array $attributes): array => [
            'tipe_kendaraan' => TipeKendaraanEnum::MobilListrik,
            'merek' => $merek,
            'kapasitas_kwh' => $kapasitasKwh,
        ]);
    }

    /**
     * State for Conventional Car (Mobil Bensin).
     */
    public function mobilBensin(string $merek = 'Toyota Avanza 1.5'): static
    {
        return $this->state(fn (array $attributes): array => [
            'tipe_kendaraan' => TipeKendaraanEnum::MobilBensin,
            'merek' => $merek,
            'kapasitas_kwh' => null,
        ]);
    }
}
