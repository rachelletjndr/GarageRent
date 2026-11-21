<?php

namespace App\Services;

use App\Enums\StatusSewaEnum;
use App\Enums\TipeKendaraanEnum;
use App\Enums\TipeSewaEnum;
use App\Models\Garasi;
use App\Models\Kendaraan;
use App\Models\Pembayaran;
use App\Models\Penyewa;
use App\Models\Penyewaan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Service orchestrating New Garage Rental Transactions, Vehicle Registration,
 * Pricing Snapshot Calculation, Payment Receipt Recording, and Garage Status Transition (FR-06, FR-07, FR-08, BR-06, BR-07).
 */
class RentalBookingService
{
    public function __construct(
        private RentalCalendarValidator $calendarValidator,
        private GarageAvailabilityService $availabilityService,
        private PricingCalculatorService $pricingCalculator,
        private PaymentProofService $paymentProofService,
    ) {}

    /**
     * Create a new garage rental transaction atomically (FR-06).
     *
     * @param  array{
     *     tanggal_mulai: string,
     *     tipe_sewa: string,
     *     durasi_hari?: int|string|null,
     *     durasi_bulan?: int|string|null,
     *     tipe_kendaraan: string,
     *     plat_nomor: string,
     *     merek: string,
     *     kapasitas_kwh?: float|int|string|null,
     *     garasi_id: string
     * }  $data
     */
    public function createNewRental(Penyewa $penyewa, array $data, UploadedFile $buktiBayarFile): Penyewaan
    {
        if (! $penyewa->isApproved()) {
            throw new InvalidArgumentException('Hanya penyewa yang telah diverifikasi (approved) yang dapat menyewa garasi.');
        }

        $tanggalMulai = (string) $data['tanggal_mulai'];

        if (! $this->calendarValidator->validateTanggalMulai($tanggalMulai)) {
            throw new InvalidArgumentException('Tanggal mulai sewa tidak boleh merupakan tanggal yang telah berlalu.');
        }

        $tipeSewa = TipeSewaEnum::from((string) $data['tipe_sewa']);
        $durasi = $tipeSewa === TipeSewaEnum::Harian
            ? (int) ($data['durasi_hari'] ?? 0)
            : (int) ($data['durasi_bulan'] ?? 1);

        $tanggalSelesai = $this->calendarValidator->hitungTanggalSelesai($tanggalMulai, $tipeSewa, $durasi);

        $tipeKendaraan = TipeKendaraanEnum::from((string) $data['tipe_kendaraan']);
        $kapasitasKwh = $tipeKendaraan === TipeKendaraanEnum::MobilListrik && isset($data['kapasitas_kwh']) && $data['kapasitas_kwh'] !== ''
            ? (float) $data['kapasitas_kwh']
            : null;

        if ($tipeKendaraan === TipeKendaraanEnum::MobilListrik && ($kapasitasKwh === null || $kapasitasKwh <= 0)) {
            throw new InvalidArgumentException('Kapasitas baterai kWh wajib diisi untuk kendaraan mobil listrik.');
        }

        return DB::transaction(function () use (
            $penyewa,
            $data,
            $tanggalMulai,
            $tanggalSelesai,
            $tipeSewa,
            $durasi,
            $tipeKendaraan,
            $kapasitasKwh,
            $buktiBayarFile,
        ): Penyewaan {
            /** @var Garasi|null $garasi */
            $garasi = Garasi::query()->lockForUpdate()->find((string) $data['garasi_id']);

            if (! $garasi) {
                throw new InvalidArgumentException('Garasi yang dipilih tidak ditemukan.');
            }

            if (! $garasi->isCompatibleWith($tipeKendaraan)) {
                throw new InvalidArgumentException('Garasi yang dipilih tidak cocok atau tidak tersedia pada rentang tanggal tersebut.');
            }

            if (! $this->availabilityService->isGarageAvailableForRange($garasi, $tanggalMulai, $tanggalSelesai)) {
                throw new InvalidArgumentException('Garasi yang dipilih tidak cocok atau tidak tersedia pada rentang tanggal tersebut.');
            }

            $rawPlat = trim((string) ($data['plat_nomor'] ?? ''));
            $normalizedPlat = $rawPlat !== '' ? strtoupper($rawPlat) : null;

            if ($normalizedPlat === null && $tipeKendaraan !== TipeKendaraanEnum::Sepeda) {
                throw new InvalidArgumentException('Plat nomor kendaraan wajib diisi.');
            }

            if ($normalizedPlat !== null) {
                $existingVehicle = Kendaraan::query()->where('plat_nomor', $normalizedPlat)->first();
                if ($existingVehicle && $existingVehicle->penyewa_id !== $penyewa->id) {
                    throw new InvalidArgumentException('Plat nomor kendaraan tersebut sudah terdaftar milik penyewa lain.');
                }

                $kendaraan = Kendaraan::updateOrCreate(
                    [
                        'plat_nomor' => $normalizedPlat,
                        'penyewa_id' => $penyewa->id,
                    ],
                    [
                        'tipe_kendaraan' => $tipeKendaraan,
                        'merek' => trim((string) $data['merek']),
                        'kapasitas_kwh' => $kapasitasKwh,
                    ],
                );
            } else {
                $kendaraan = Kendaraan::updateOrCreate(
                    [
                        'penyewa_id' => $penyewa->id,
                        'tipe_kendaraan' => TipeKendaraanEnum::Sepeda,
                        'merek' => trim((string) $data['merek']),
                        'plat_nomor' => null,
                    ],
                    [
                        'kapasitas_kwh' => null,
                    ],
                );
            }

            $rincianBiaya = $this->pricingCalculator->hitungRincianBiaya(
                $garasi,
                $kendaraan,
                $tipeSewa,
                $durasi,
            );

            $storedProofPath = $this->paymentProofService->storePaymentProof($buktiBayarFile, 'proof_trx');

            try {
                $penyewaan = Penyewaan::create([
                    'nomor_transaksi' => $this->generateTransactionNumber(),
                    'penyewa_id' => $penyewa->id,
                    'garasi_id' => $garasi->id,
                    'kendaraan_id' => $kendaraan->id,
                    'tanggal_mulai' => $tanggalMulai,
                    'tanggal_selesai' => $tanggalSelesai->toDateString(),
                    'tipe_sewa' => $tipeSewa,
                    'durasi' => $durasi,
                    'tarif_pokok' => $rincianBiaya['tarif_pokok'],
                    'biaya_listrik' => $rincianBiaya['biaya_listrik'],
                    'total_bayar' => $rincianBiaya['total_bayar'],
                    'status_sewa' => StatusSewaEnum::Aktif,
                ]);

                Pembayaran::create([
                    'nomor_pembayaran' => $this->generatePaymentNumber(),
                    'penyewaan_id' => $penyewaan->id,
                    'perpanjangan_id' => null,
                    'jenis_pembayaran' => 'sewa_baru',
                    'nominal' => $rincianBiaya['total_bayar'],
                    'bukti_transfer' => $storedProofPath,
                    'waktu_bayar' => now(),
                    'status_verifikasi' => 'valid',
                    'diverifikasi_oleh' => null,
                ]);

                // BR-07 & AC-17: Transition garage status to 'used' once rental transaction is saved
                $garasi->markAsUsed();

                return $penyewaan->load(['garasi', 'kendaraan', 'pembayaran']);
            } catch (\Throwable $exception) {
                $this->paymentProofService->deletePaymentProof($storedProofPath);

                throw $exception;
            }
        });
    }

    /**
     * Retrieve paginated list of rental transactions for a specific tenant.
     *
     * @return LengthAwarePaginator<int, Penyewaan>
     */
    public function getTenantRentals(Penyewa $penyewa, int $perPage = 10): LengthAwarePaginator
    {
        return Penyewaan::query()
            ->with(['garasi', 'kendaraan', 'pembayaran', 'perpanjanganSewa'])
            ->where('penyewa_id', $penyewa->id)
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Generate a unique transaction invoice number (e.g., TRX-20261121-0004).
     */
    private function generateTransactionNumber(): string
    {
        $datePart = now()->format('Ymd');
        $sequence = Penyewaan::query()->count() + 1;

        do {
            $candidate = sprintf('TRX-%s-%03d', $datePart, $sequence);
            $exists = Penyewaan::query()->where('nomor_transaksi', $candidate)->exists();
            $sequence++;
        } while ($exists);

        return $candidate;
    }

    /**
     * Generate a unique payment receipt number (e.g., PAY-20261121-0004).
     */
    private function generatePaymentNumber(): string
    {
        $datePart = now()->format('Ymd');
        $sequence = Pembayaran::query()->count() + 1;

        do {
            $candidate = sprintf('PAY-%s-%03d', $datePart, $sequence);
            $exists = Pembayaran::query()->where('nomor_pembayaran', $candidate)->exists();
            $sequence++;
        } while ($exists);

        return $candidate ?: 'PAY-'.$datePart.'-'.Str::upper(Str::random(4));
    }
}
