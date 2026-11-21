<?php

namespace App\Services;

use App\Enums\TipeGarasiEnum;
use App\Enums\TipeKendaraanEnum;
use App\Enums\TipeSewaEnum;
use App\Models\Garasi;
use App\Models\Kendaraan;
use InvalidArgumentException;

/**
 * Service handling Rental Price Calculation in Rupiah (IDR) (FR-07, BR-02, BR-03).
 *
 * Rules (SRS v1.2.0):
 * 1. Tarif Pokok Harian = Tarif Harian x Jumlah Hari
 * 2. Tarif Pokok Bulanan = 25 x Tarif Harian (Tetap, dihitung otomatis)
 * 3. Biaya Listrik = Kapasitas Baterai (kWh) x Tarif Listrik/kWh (Khusus Mobil Listrik di Garasi 'E'; Rp 0 di Garasi 'C')
 * 4. Total Bayar = Tarif Pokok + Biaya Listrik (Murni tanpa tambahan surcharge)
 */
class PricingCalculatorService
{
    /**
     * Calculate the base rental tariff in IDR (BR-03 & FR-07).
     */
    public function hitungTarifPokok(Garasi $garasi, TipeSewaEnum $tipeSewa, int $durasi): float
    {
        if ($durasi < 1) {
            throw new InvalidArgumentException('Durasi penyewaan minimal adalah 1.');
        }

        if ($tipeSewa === TipeSewaEnum::Bulanan) {
            return round($garasi->getHargaSewaBulanan() * $durasi, 2);
        }

        return round((float) $garasi->harga_sewa_harian * $durasi, 2);
    }

    /**
     * Calculate the full EV battery charging cost in IDR (BR-02 & FR-07).
     *
     * Note: If an electric car is parked in a conventional car garage ('C'), charging cost is Rp 0.
     */
    public function hitungBiayaListrik(
        Garasi $garasi,
        TipeKendaraanEnum|Kendaraan $kendaraanOrTipe,
        ?float $kapasitasKwh = null,
    ): float {
        $tipeKendaraan = $kendaraanOrTipe instanceof Kendaraan
            ? $kendaraanOrTipe->tipe_kendaraan
            : $kendaraanOrTipe;

        $kwh = $kendaraanOrTipe instanceof Kendaraan
            ? $kendaraanOrTipe->getKapasitasKwh()
            : (float) ($kapasitasKwh ?? 0.0);

        if ($tipeKendaraan !== TipeKendaraanEnum::MobilListrik || $garasi->tipe_garasi !== TipeGarasiEnum::MobilListrik) {
            return 0.0;
        }

        if ($kwh <= 0) {
            throw new InvalidArgumentException('Kapasitas baterai kWh wajib diisi untuk kendaraan mobil listrik.');
        }

        return round($kwh * (float) ($garasi->tarif_kwh ?? 0.0), 2);
    }

    /**
     * Calculate total payment in IDR (Total Bayar = Tarif Pokok + Biaya Listrik) (FR-07).
     */
    public function hitungTotalBayar(
        Garasi $garasi,
        TipeKendaraanEnum|Kendaraan $kendaraanOrTipe,
        TipeSewaEnum $tipeSewa,
        int $durasi,
        ?float $kapasitasKwh = null,
    ): float {
        $tarifPokok = $this->hitungTarifPokok($garasi, $tipeSewa, $durasi);
        $biayaListrik = $this->hitungBiayaListrik($garasi, $kendaraanOrTipe, $kapasitasKwh);

        return round($tarifPokok + $biayaListrik, 2);
    }

    /**
     * Calculate full itemized breakdown of rental pricing in IDR.
     *
     * @return array{
     *     tarif_pokok: float,
     *     biaya_listrik: float,
     *     total_bayar: float
     * }
     */
    public function hitungRincianBiaya(
        Garasi $garasi,
        TipeKendaraanEnum|Kendaraan $kendaraanOrTipe,
        TipeSewaEnum $tipeSewa,
        int $durasi,
        ?float $kapasitasKwh = null,
    ): array {
        $tarifPokok = $this->hitungTarifPokok($garasi, $tipeSewa, $durasi);
        $biayaListrik = $this->hitungBiayaListrik($garasi, $kendaraanOrTipe, $kapasitasKwh);

        return [
            'tarif_pokok' => $tarifPokok,
            'biaya_listrik' => $biayaListrik,
            'total_bayar' => round($tarifPokok + $biayaListrik, 2),
        ];
    }
}
