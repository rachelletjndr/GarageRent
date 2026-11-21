<?php

namespace App\Services;

use App\Enums\StatusSewaEnum;
use App\Models\Pembayaran;
use App\Models\Penyewaan;
use App\Models\PerpanjanganSewa;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Service handling Rental Extension (Perpanjangan Masa Sewa) exclusively by Admin,
 * including end-date adjustment, extension cost calculation, and payment receipt storage (FR-09).
 */
class RentalExtensionService
{
    public function __construct(
        private PaymentProofService $paymentProofService,
    ) {}

    /**
     * Calculate the extension days and extension cost in IDR (FR-09).
     *
     * Formula:
     * - Selisih Hari = Tanggal Selesai Baru - Tanggal Selesai Lama
     * - Biaya Perpanjangan = Selisih Hari * Tarif Sewa Harian Garasi
     *
     * @return array{
     *     tanggal_selesai_lama: string,
     *     tanggal_selesai_baru: string,
     *     selisih_hari: int,
     *     tarif_harian: float,
     *     biaya_perpanjangan: float
     * }
     */
    public function calculateExtensionBreakdown(Penyewaan $penyewaan, Carbon|string $tanggalSelesaiBaru): array
    {
        $penyewaan->loadMissing('garasi');

        $oldEnd = $penyewaan->tanggal_selesai->copy()->startOfDay();
        $newEnd = ($tanggalSelesaiBaru instanceof Carbon ? $tanggalSelesaiBaru->copy() : Carbon::parse($tanggalSelesaiBaru))->startOfDay();

        if ($newEnd->lessThanOrEqualTo($oldEnd)) {
            throw new InvalidArgumentException('Tanggal selesai baru harus lebih besar dari tanggal selesai saat ini.');
        }

        $selisihHari = (int) $oldEnd->diffInDays($newEnd);
        $tarifHarian = (float) ($penyewaan->garasi?->harga_sewa_harian ?? 0);
        $biayaPerpanjangan = round($selisihHari * $tarifHarian, 2);

        return [
            'tanggal_selesai_lama' => $oldEnd->toDateString(),
            'tanggal_selesai_baru' => $newEnd->toDateString(),
            'selisih_hari' => $selisihHari,
            'tarif_harian' => $tarifHarian,
            'biaya_perpanjangan' => $biayaPerpanjangan,
        ];
    }

    /**
     * Check whether the garage is free from other active bookings during the extension window
     * [tanggal_selesai_lama + 1 day, tanggal_selesai_baru].
     */
    public function isExtensionRangeAvailable(Penyewaan $penyewaan, Carbon|string $tanggalSelesaiBaru): bool
    {
        $oldEnd = $penyewaan->tanggal_selesai->copy()->startOfDay();
        $newEnd = ($tanggalSelesaiBaru instanceof Carbon ? $tanggalSelesaiBaru->copy() : Carbon::parse($tanggalSelesaiBaru))->startOfDay();

        if ($newEnd->lessThanOrEqualTo($oldEnd)) {
            return false;
        }

        $extensionStart = $oldEnd->copy()->addDay()->toDateString();
        $extensionEnd = $newEnd->toDateString();

        return ! Penyewaan::query()
            ->where('garasi_id', $penyewaan->garasi_id)
            ->where('id', '!=', $penyewaan->id)
            ->where('status_sewa', StatusSewaEnum::Aktif->value)
            ->whereDate('tanggal_mulai', '<=', $extensionEnd)
            ->whereDate('tanggal_selesai', '>=', $extensionStart)
            ->exists();
    }

    /**
     * Execute a rental extension transaction atomically (FR-09).
     *
     * Updates penyewaan.tanggal_selesai, records perpanjangan_sewa,
     * and stores the extension payment receipt in pembayaran.
     */
    public function extendRental(
        Penyewaan $penyewaan,
        User $admin,
        Carbon|string $tanggalSelesaiBaru,
        UploadedFile $buktiBayarFile,
    ): PerpanjanganSewa {
        if (! $admin->isAdmin()) {
            throw new InvalidArgumentException('Hanya Admin yang memiliki hak akses untuk memperpanjang masa sewa garasi.');
        }

        if (! $penyewaan->isActive()) {
            throw new InvalidArgumentException('Hanya transaksi penyewaan berstatus aktif yang dapat diperpanjang.');
        }

        $breakdown = $this->calculateExtensionBreakdown($penyewaan, $tanggalSelesaiBaru);

        if (! $this->isExtensionRangeAvailable($penyewaan, $tanggalSelesaiBaru)) {
            throw new InvalidArgumentException('Garasi sudah dipesan pada rentang tanggal perpanjangan tersebut.');
        }

        return DB::transaction(function () use (
            $penyewaan,
            $admin,
            $breakdown,
            $buktiBayarFile,
        ): PerpanjanganSewa {
            $storedProofPath = $this->paymentProofService->storePaymentProof($buktiBayarFile, 'proof_ext');

            try {
                $perpanjangan = PerpanjanganSewa::create([
                    'nomor_perpanjangan' => $this->generateExtensionNumber(),
                    'penyewaan_id' => $penyewaan->id,
                    'admin_id' => $admin->id,
                    'tanggal_selesai_lama' => $breakdown['tanggal_selesai_lama'],
                    'tanggal_selesai_baru' => $breakdown['tanggal_selesai_baru'],
                    'selisih_hari' => $breakdown['selisih_hari'],
                    'tarif_harian_snapshot' => $breakdown['tarif_harian'],
                    'biaya_perpanjangan' => $breakdown['biaya_perpanjangan'],
                ]);

                Pembayaran::create([
                    'nomor_pembayaran' => $this->generatePaymentNumber(),
                    'penyewaan_id' => $penyewaan->id,
                    'perpanjangan_id' => $perpanjangan->id,
                    'jenis_pembayaran' => 'perpanjangan',
                    'nominal' => $breakdown['biaya_perpanjangan'],
                    'bukti_transfer' => $storedProofPath,
                    'waktu_bayar' => now(),
                    'status_verifikasi' => 'valid',
                    'diverifikasi_oleh' => $admin->id,
                ]);

                $penyewaan->update([
                    'tanggal_selesai' => $breakdown['tanggal_selesai_baru'],
                ]);

                $penyewaan->garasi?->markAsUsed();

                return $perpanjangan->load(['penyewaan.penyewa', 'penyewaan.garasi', 'penyewaan.kendaraan', 'admin', 'pembayaran']);
            } catch (\Throwable $exception) {
                $this->paymentProofService->deletePaymentProof($storedProofPath);

                throw $exception;
            }
        });
    }

    /**
     * Get all currently active rental transactions eligible for extension (FR-09).
     *
     * @return Collection<int, Penyewaan>
     */
    public function getActiveRentals(?string $search = null, ?string $tipeGarasi = null): Collection
    {
        return Penyewaan::query()
            ->with(['penyewa.user', 'garasi', 'kendaraan', 'perpanjanganSewa'])
            ->where('status_sewa', StatusSewaEnum::Aktif->value)
            ->when($tipeGarasi !== null && $tipeGarasi !== '', function ($query) use ($tipeGarasi): void {
                $query->whereHas('garasi', function ($garageQuery) use ($tipeGarasi): void {
                    $garageQuery->where('tipe_garasi', $tipeGarasi);
                });
            })
            ->when($search !== null && trim($search) !== '', function ($query) use ($search): void {
                $keyword = trim($search);
                $query->where(function ($subQuery) use ($keyword): void {
                    $subQuery->where('nomor_transaksi', 'like', "%{$keyword}%")
                        ->orWhere('garasi_id', 'like', "%{$keyword}%")
                        ->orWhereHas('penyewa', function ($tenantQuery) use ($keyword): void {
                            $tenantQuery->where('nama_lengkap', 'like', "%{$keyword}%")
                                ->orWhere('nik', 'like', "%{$keyword}%");
                        })
                        ->orWhereHas('kendaraan', function ($vehicleQuery) use ($keyword): void {
                            $vehicleQuery->where('plat_nomor', 'like', "%{$keyword}%")
                                ->orWhere('merek', 'like', "%{$keyword}%");
                        });
                });
            })
            ->orderBy('tanggal_selesai')
            ->orderBy('garasi_id')
            ->get();
    }

    /**
     * Get paginated history of rental extensions executed by Admin.
     *
     * @return LengthAwarePaginator<int, PerpanjanganSewa>
     */
    public function getExtensionHistory(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        return PerpanjanganSewa::query()
            ->with(['penyewaan.penyewa', 'penyewaan.garasi', 'penyewaan.kendaraan', 'admin', 'pembayaran'])
            ->when($search !== null && trim($search) !== '', function ($query) use ($search): void {
                $keyword = trim($search);
                $query->where(function ($subQuery) use ($keyword): void {
                    $subQuery->where('nomor_perpanjangan', 'like', "%{$keyword}%")
                        ->orWhereHas('penyewaan', function ($rentalQuery) use ($keyword): void {
                            $rentalQuery->where('nomor_transaksi', 'like', "%{$keyword}%")
                                ->orWhere('garasi_id', 'like', "%{$keyword}%")
                                ->orWhereHas('penyewa', function ($tenantQuery) use ($keyword): void {
                                    $tenantQuery->where('nama_lengkap', 'like', "%{$keyword}%");
                                });
                        });
                });
            })
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Get summary statistics for the Admin Rental Extension page & Dashboard.
     *
     * @return array{
     *     active_rentals: int,
     *     total_extensions: int,
     *     total_extension_days: int,
     *     total_extension_revenue: float
     * }
     */
    public function getExtensionStatistics(): array
    {
        $activeRentals = Penyewaan::query()
            ->where('status_sewa', StatusSewaEnum::Aktif->value)
            ->count();

        $totalExtensions = PerpanjanganSewa::query()->count();
        $totalExtensionDays = (int) PerpanjanganSewa::query()->sum('selisih_hari');
        $totalRevenue = (float) PerpanjanganSewa::query()->sum('biaya_perpanjangan');

        return [
            'active_rentals' => $activeRentals,
            'total_extensions' => $totalExtensions,
            'total_extension_days' => $totalExtensionDays,
            'total_extension_revenue' => $totalRevenue,
        ];
    }

    /**
     * Generate a unique extension code (e.g., EXT-20261121-001).
     */
    private function generateExtensionNumber(): string
    {
        $datePart = now()->format('Ymd');
        $sequence = PerpanjanganSewa::query()->count() + 1;

        do {
            $candidate = sprintf('EXT-%s-%03d', $datePart, $sequence);
            $exists = PerpanjanganSewa::query()->where('nomor_perpanjangan', $candidate)->exists();
            $sequence++;
        } while ($exists);

        return $candidate;
    }

    /**
     * Generate a unique payment receipt number (e.g., PAY-20261121-004).
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
