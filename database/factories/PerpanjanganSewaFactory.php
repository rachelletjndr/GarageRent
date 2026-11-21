<?php

namespace Database\Factories;

use App\Models\Penyewaan;
use App\Models\PerpanjanganSewa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerpanjanganSewa>
 */
class PerpanjanganSewaFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<PerpanjanganSewa>
     */
    protected $model = PerpanjanganSewa::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $oldEnd = now()->startOfDay()->addDays(4);
        $newEnd = $oldEnd->copy()->addDays(5);

        return [
            'nomor_perpanjangan' => 'EXT-'.now()->format('Ymd').'-'.fake()->unique()->numerify('####'),
            'penyewaan_id' => Penyewaan::factory(),
            'admin_id' => User::factory()->admin(),
            'tanggal_selesai_lama' => $oldEnd->toDateString(),
            'tanggal_selesai_baru' => $newEnd->toDateString(),
            'selisih_hari' => 5,
            'tarif_harian_snapshot' => 50000.00,
            'biaya_perpanjangan' => 250000.00,
        ];
    }
}
