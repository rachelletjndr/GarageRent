<?php

namespace Database\Factories;

use App\Models\Pembayaran;
use App\Models\Penyewaan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pembayaran>
 */
class PembayaranFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Pembayaran>
     */
    protected $model = Pembayaran::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nomor_pembayaran' => 'PAY-'.now()->format('Ymd').'-'.fake()->unique()->numerify('####'),
            'penyewaan_id' => Penyewaan::factory(),
            'perpanjangan_id' => null,
            'jenis_pembayaran' => 'sewa_baru',
            'nominal' => 100000.00,
            'bukti_transfer' => 'payments/proof_trx_sample.jpg',
            'waktu_bayar' => now(),
            'status_verifikasi' => 'valid',
            'diverifikasi_oleh' => null,
        ];
    }
}
