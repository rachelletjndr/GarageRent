<?php

namespace Database\Factories;

use App\Enums\StatusSewaEnum;
use App\Enums\TipeSewaEnum;
use App\Models\Garasi;
use App\Models\Kendaraan;
use App\Models\Penyewa;
use App\Models\Penyewaan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Penyewaan>
 */
class PenyewaanFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Penyewaan>
     */
    protected $model = Penyewaan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->startOfDay();
        $end = $start->copy()->addDays(4);

        return [
            'nomor_transaksi' => 'TRX-'.now()->format('Ymd').'-'.fake()->unique()->numerify('####'),
            'penyewa_id' => Penyewa::factory(),
            'garasi_id' => Garasi::factory(),
            'kendaraan_id' => Kendaraan::factory(),
            'tanggal_mulai' => $start->toDateString(),
            'tanggal_selesai' => $end->toDateString(),
            'tipe_sewa' => TipeSewaEnum::Harian,
            'durasi' => 5,
            'tarif_pokok' => 100000.00,
            'biaya_listrik' => 0.00,
            'total_bayar' => 100000.00,
            'status_sewa' => StatusSewaEnum::Aktif,
        ];
    }
}
