<?php

namespace App\Services;

use App\Enums\StatusGarasiEnum;
use App\Enums\StatusSewaEnum;
use App\Enums\TipeGarasiEnum;
use App\Models\Garasi;
use App\Models\Penyewa;
use App\Models\Penyewaan;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Service generating Multi-Dimensional Rental Reports (FR-10, UC-08, UC-09):
 * 1. Laporan Bulanan (Per bulan dalam tahun tertentu)
 * 2. Laporan Harian (Per hari dalam bulan tertentu)
 * 3. Laporan Berdasarkan Tipe Garasi (Sepeda 'S', Mobil 'C', Mobil Listrik 'E')
 * 4. Laporan Berdasarkan Penyewa
 */
class RentalReportService
{
    /**
     * Indonesian month names mapping.
     *
     * @var array<int, string>
     */
    private const MONTH_NAMES = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    /**
     * Retrieve filtered rental transactions matching the multi-dimensional filter parameters.
     *
     * @param  array{
     *     mode?: string,
     *     tahun?: int|null,
     *     bulan?: int|null,
     *     tanggal?: string|null,
     *     tipe_garasi?: string|null,
     *     penyewa_id?: int|null,
     *     status_sewa?: string|null
     * }  $filters
     * @return Collection<int, Penyewaan>
     */
    public function getFilteredTransactions(array $filters): Collection
    {
        $mode = $filters['mode'] ?? 'bulanan';
        $tahun = $filters['tahun'] ?? null;
        $bulan = $filters['bulan'] ?? null;
        $tanggal = $filters['tanggal'] ?? null;
        $tipeGarasi = $filters['tipe_garasi'] ?? null;
        $penyewaId = $filters['penyewa_id'] ?? null;
        $statusSewa = $filters['status_sewa'] ?? null;

        return Penyewaan::query()
            ->with(['penyewa.user', 'garasi', 'kendaraan', 'perpanjanganSewa', 'pembayaran'])
            ->when($tahun !== null, function ($query) use ($tahun): void {
                $query->whereYear('tanggal_mulai', $tahun);
            })
            ->when($mode === 'harian' && $bulan !== null, function ($query) use ($bulan): void {
                $query->whereMonth('tanggal_mulai', $bulan);
            })
            ->when($tanggal !== null && $tanggal !== '', function ($query) use ($tanggal): void {
                $query->whereDate('tanggal_mulai', $tanggal);
            })
            ->when($tipeGarasi !== null && $tipeGarasi !== '', function ($query) use ($tipeGarasi): void {
                $query->whereHas('garasi', function ($garageQuery) use ($tipeGarasi): void {
                    $garageQuery->where('tipe_garasi', $tipeGarasi);
                });
            })
            ->when($penyewaId !== null, function ($query) use ($penyewaId): void {
                $query->where('penyewa_id', $penyewaId);
            })
            ->when($statusSewa !== null && $statusSewa !== '', function ($query) use ($statusSewa): void {
                $query->where('status_sewa', $statusSewa);
            })
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * 1. Laporan Bulanan: Rekapitulasi penyewaan per bulan (Januari - Desember) dalam tahun tertentu (FR-10.1).
     *
     * @return array{
     *     year: int,
     *     months: array<int, array{
     *         month: int,
     *         month_name: string,
     *         transaction_count: int,
     *         tarif_pokok: float,
     *         biaya_listrik: float,
     *         total_sewa_baru: float,
     *         extension_count: int,
     *         total_perpanjangan: float,
     *         grand_total: float
     *     }>,
     *     totals: array{
     *         transaction_count: int,
     *         tarif_pokok: float,
     *         biaya_listrik: float,
     *         total_sewa_baru: float,
     *         extension_count: int,
     *         total_perpanjangan: float,
     *         grand_total: float
     *     }
     * }
     */
    public function getMonthlyReport(int $tahun, ?string $tipeGarasi = null, ?int $penyewaId = null): array
    {
        $transactions = Penyewaan::query()
            ->with(['garasi', 'perpanjanganSewa'])
            ->whereYear('tanggal_mulai', $tahun)
            ->when($tipeGarasi !== null && $tipeGarasi !== '', function ($query) use ($tipeGarasi): void {
                $query->whereHas('garasi', function ($garageQuery) use ($tipeGarasi): void {
                    $garageQuery->where('tipe_garasi', $tipeGarasi);
                });
            })
            ->when($penyewaId !== null, function ($query) use ($penyewaId): void {
                $query->where('penyewa_id', $penyewaId);
            })
            ->get();

        $months = [];
        $totals = [
            'transaction_count' => 0,
            'tarif_pokok' => 0.0,
            'biaya_listrik' => 0.0,
            'total_sewa_baru' => 0.0,
            'extension_count' => 0,
            'total_perpanjangan' => 0.0,
            'grand_total' => 0.0,
        ];

        for ($month = 1; $month <= 12; $month++) {
            $monthTransactions = $transactions->filter(
                fn (Penyewaan $item): bool => (int) $item->tanggal_mulai->month === $month,
            );

            $count = $monthTransactions->count();
            $tarifPokok = round((float) $monthTransactions->sum('tarif_pokok'), 2);
            $biayaListrik = round((float) $monthTransactions->sum('biaya_listrik'), 2);
            $totalSewaBaru = round((float) $monthTransactions->sum('total_bayar'), 2);

            $extensionCount = (int) $monthTransactions->sum(
                fn (Penyewaan $item): int => $item->perpanjanganSewa->count(),
            );
            $totalPerpanjangan = round((float) $monthTransactions->sum(
                fn (Penyewaan $item): float => (float) $item->perpanjanganSewa->sum('biaya_perpanjangan'),
            ), 2);

            $grandTotal = round($totalSewaBaru + $totalPerpanjangan, 2);

            $months[$month] = [
                'month' => $month,
                'month_name' => self::MONTH_NAMES[$month],
                'transaction_count' => $count,
                'tarif_pokok' => $tarifPokok,
                'biaya_listrik' => $biayaListrik,
                'total_sewa_baru' => $totalSewaBaru,
                'extension_count' => $extensionCount,
                'total_perpanjangan' => $totalPerpanjangan,
                'grand_total' => $grandTotal,
            ];

            $totals['transaction_count'] += $count;
            $totals['tarif_pokok'] = round($totals['tarif_pokok'] + $tarifPokok, 2);
            $totals['biaya_listrik'] = round($totals['biaya_listrik'] + $biayaListrik, 2);
            $totals['total_sewa_baru'] = round($totals['total_sewa_baru'] + $totalSewaBaru, 2);
            $totals['extension_count'] += $extensionCount;
            $totals['total_perpanjangan'] = round($totals['total_perpanjangan'] + $totalPerpanjangan, 2);
            $totals['grand_total'] = round($totals['grand_total'] + $grandTotal, 2);
        }

        return [
            'year' => $tahun,
            'months' => $months,
            'totals' => $totals,
        ];
    }

    /**
     * 2. Laporan Harian: Rekapitulasi penyewaan per hari dalam bulan dan tahun tertentu (FR-10.2).
     *
     * @return array{
     *     year: int,
     *     month: int,
     *     month_name: string,
     *     days_in_month: int,
     *     days: array<int, array{
     *         date: string,
     *         day_number: int,
     *         day_label: string,
     *         transaction_count: int,
     *         tarif_pokok: float,
     *         biaya_listrik: float,
     *         total_sewa_baru: float,
     *         extension_count: int,
     *         total_perpanjangan: float,
     *         grand_total: float
     *     }>,
     *     totals: array{
     *         transaction_count: int,
     *         tarif_pokok: float,
     *         biaya_listrik: float,
     *         total_sewa_baru: float,
     *         extension_count: int,
     *         total_perpanjangan: float,
     *         grand_total: float
     *     }
     * }
     */
    public function getDailyReport(
        int $tahun,
        int $bulan,
        ?string $tipeGarasi = null,
        ?int $penyewaId = null,
    ): array {
        $periodStart = Carbon::createFromDate($tahun, $bulan, 1)->startOfMonth();
        $daysInMonth = $periodStart->daysInMonth;

        $transactions = Penyewaan::query()
            ->with(['garasi', 'perpanjanganSewa'])
            ->whereYear('tanggal_mulai', $tahun)
            ->whereMonth('tanggal_mulai', $bulan)
            ->when($tipeGarasi !== null && $tipeGarasi !== '', function ($query) use ($tipeGarasi): void {
                $query->whereHas('garasi', function ($garageQuery) use ($tipeGarasi): void {
                    $garageQuery->where('tipe_garasi', $tipeGarasi);
                });
            })
            ->when($penyewaId !== null, function ($query) use ($penyewaId): void {
                $query->where('penyewa_id', $penyewaId);
            })
            ->get();

        $days = [];
        $totals = [
            'transaction_count' => 0,
            'tarif_pokok' => 0.0,
            'biaya_listrik' => 0.0,
            'total_sewa_baru' => 0.0,
            'extension_count' => 0,
            'total_perpanjangan' => 0.0,
            'grand_total' => 0.0,
        ];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $currentDate = Carbon::createFromDate($tahun, $bulan, $day)->startOfDay();
            $dateString = $currentDate->toDateString();

            $dayTransactions = $transactions->filter(
                fn (Penyewaan $item): bool => $item->tanggal_mulai->toDateString() === $dateString,
            );

            $count = $dayTransactions->count();
            $tarifPokok = round((float) $dayTransactions->sum('tarif_pokok'), 2);
            $biayaListrik = round((float) $dayTransactions->sum('biaya_listrik'), 2);
            $totalSewaBaru = round((float) $dayTransactions->sum('total_bayar'), 2);

            $extensionCount = (int) $dayTransactions->sum(
                fn (Penyewaan $item): int => $item->perpanjanganSewa->count(),
            );
            $totalPerpanjangan = round((float) $dayTransactions->sum(
                fn (Penyewaan $item): float => (float) $item->perpanjanganSewa->sum('biaya_perpanjangan'),
            ), 2);

            $grandTotal = round($totalSewaBaru + $totalPerpanjangan, 2);

            $days[$day] = [
                'date' => $dateString,
                'day_number' => $day,
                'day_label' => sprintf('%02d %s %d', $day, self::MONTH_NAMES[$bulan] ?? '', $tahun),
                'transaction_count' => $count,
                'tarif_pokok' => $tarifPokok,
                'biaya_listrik' => $biayaListrik,
                'total_sewa_baru' => $totalSewaBaru,
                'extension_count' => $extensionCount,
                'total_perpanjangan' => $totalPerpanjangan,
                'grand_total' => $grandTotal,
            ];

            $totals['transaction_count'] += $count;
            $totals['tarif_pokok'] = round($totals['tarif_pokok'] + $tarifPokok, 2);
            $totals['biaya_listrik'] = round($totals['biaya_listrik'] + $biayaListrik, 2);
            $totals['total_sewa_baru'] = round($totals['total_sewa_baru'] + $totalSewaBaru, 2);
            $totals['extension_count'] += $extensionCount;
            $totals['total_perpanjangan'] = round($totals['total_perpanjangan'] + $totalPerpanjangan, 2);
            $totals['grand_total'] = round($totals['grand_total'] + $grandTotal, 2);
        }

        return [
            'year' => $tahun,
            'month' => $bulan,
            'month_name' => self::MONTH_NAMES[$bulan] ?? (string) $bulan,
            'days_in_month' => $daysInMonth,
            'days' => $days,
            'totals' => $totals,
        ];
    }

    /**
     * 3. Laporan Berdasarkan Tipe Garasi: Rekapitulasi untuk Sepeda ('S'), Mobil ('C'), dan Mobil Listrik ('E') (FR-10.3).
     *
     * @return array<string, array{
     *     tipe_garasi: string,
     *     label: string,
     *     prefix: string,
     *     total_units: int,
     *     used_units: int,
     *     available_units: int,
     *     transaction_count: int,
     *     tarif_pokok: float,
     *     biaya_listrik: float,
     *     total_sewa_baru: float,
     *     extension_count: int,
     *     total_perpanjangan: float,
     *     grand_total: float
     * }>
     */
    public function getGarageTypeReport(?int $tahun = null, ?int $bulan = null, ?string $selectedType = null): array
    {
        $report = [];

        foreach (TipeGarasiEnum::cases() as $type) {
            if ($selectedType !== null && $selectedType !== '' && $selectedType !== $type->value) {
                continue;
            }

            $totalUnits = Garasi::query()->where('tipe_garasi', $type->value)->count();
            $usedUnits = Garasi::query()
                ->where('tipe_garasi', $type->value)
                ->where('status', StatusGarasiEnum::Used->value)
                ->count();

            $transactions = Penyewaan::query()
                ->with('perpanjanganSewa')
                ->whereHas('garasi', function ($query) use ($type): void {
                    $query->where('tipe_garasi', $type->value);
                })
                ->when($tahun !== null, function ($query) use ($tahun): void {
                    $query->whereYear('tanggal_mulai', $tahun);
                })
                ->when($bulan !== null, function ($query) use ($bulan): void {
                    $query->whereMonth('tanggal_mulai', $bulan);
                })
                ->get();

            $count = $transactions->count();
            $tarifPokok = round((float) $transactions->sum('tarif_pokok'), 2);
            $biayaListrik = round((float) $transactions->sum('biaya_listrik'), 2);
            $totalSewaBaru = round((float) $transactions->sum('total_bayar'), 2);

            $extensionCount = (int) $transactions->sum(
                fn (Penyewaan $item): int => $item->perpanjanganSewa->count(),
            );
            $totalPerpanjangan = round((float) $transactions->sum(
                fn (Penyewaan $item): float => (float) $item->perpanjanganSewa->sum('biaya_perpanjangan'),
            ), 2);

            $report[$type->value] = [
                'tipe_garasi' => $type->value,
                'label' => $type->label(),
                'prefix' => $type->prefix(),
                'total_units' => $totalUnits,
                'used_units' => $usedUnits,
                'available_units' => $totalUnits - $usedUnits,
                'transaction_count' => $count,
                'tarif_pokok' => $tarifPokok,
                'biaya_listrik' => $biayaListrik,
                'total_sewa_baru' => $totalSewaBaru,
                'extension_count' => $extensionCount,
                'total_perpanjangan' => $totalPerpanjangan,
                'grand_total' => round($totalSewaBaru + $totalPerpanjangan, 2),
            ];
        }

        return $report;
    }

    /**
     * 4. Laporan Berdasarkan Penyewa: Rekapitulasi transaksi & pendapatan per penyewa (FR-10.4).
     *
     * @return array<int, array{
     *     penyewa_id: int,
     *     nik: string,
     *     nama_lengkap: string,
     *     no_telp: string,
     *     email: string,
     *     active_rentals_count: int,
     *     total_transactions: int,
     *     tarif_pokok: float,
     *     biaya_listrik: float,
     *     total_sewa_baru: float,
     *     extension_count: int,
     *     total_perpanjangan: float,
     *     grand_total: float
     * }>
     */
    public function getTenantReport(?int $penyewaId = null, ?int $tahun = null, ?int $bulan = null): array
    {
        $tenants = Penyewa::query()
            ->with(['user', 'penyewaan.perpanjanganSewa'])
            ->when($penyewaId !== null, function ($query) use ($penyewaId): void {
                $query->where('id', $penyewaId);
            })
            ->orderBy('nama_lengkap')
            ->get();

        $rows = [];

        foreach ($tenants as $tenant) {
            $filteredRentals = $tenant->penyewaan->filter(function (Penyewaan $rental) use ($tahun, $bulan): bool {
                if ($tahun !== null && (int) $rental->tanggal_mulai->year !== $tahun) {
                    return false;
                }

                if ($bulan !== null && (int) $rental->tanggal_mulai->month !== $bulan) {
                    return false;
                }

                return true;
            });

            $activeCount = $filteredRentals->filter(fn (Penyewaan $r): bool => $r->isActive())->count();
            $totalTransactions = $filteredRentals->count();
            $tarifPokok = round((float) $filteredRentals->sum('tarif_pokok'), 2);
            $biayaListrik = round((float) $filteredRentals->sum('biaya_listrik'), 2);
            $totalSewaBaru = round((float) $filteredRentals->sum('total_bayar'), 2);

            $extensionCount = (int) $filteredRentals->sum(
                fn (Penyewaan $r): int => $r->perpanjanganSewa->count(),
            );
            $totalPerpanjangan = round((float) $filteredRentals->sum(
                fn (Penyewaan $r): float => (float) $r->perpanjanganSewa->sum('biaya_perpanjangan'),
            ), 2);

            $rows[] = [
                'penyewa_id' => $tenant->id,
                'nik' => $tenant->nik,
                'nama_lengkap' => $tenant->nama_lengkap,
                'no_telp' => $tenant->no_telp,
                'email' => $tenant->user?->email ?? '-',
                'active_rentals_count' => $activeCount,
                'total_transactions' => $totalTransactions,
                'tarif_pokok' => $tarifPokok,
                'biaya_listrik' => $biayaListrik,
                'total_sewa_baru' => $totalSewaBaru,
                'extension_count' => $extensionCount,
                'total_perpanjangan' => $totalPerpanjangan,
                'grand_total' => round($totalSewaBaru + $totalPerpanjangan, 2),
            ];
        }

        return $rows;
    }

    /**
     * Get instant monitoring list of all garages currently rented/used (UC-08, FR-10),
     * scoped to the active report filters when provided.
     *
     * @param  array{
     *     mode?: string,
     *     tahun?: int|null,
     *     bulan?: int|null,
     *     tanggal?: string|null,
     *     tipe_garasi?: string|null,
     *     penyewa_id?: int|null
     * }  $filters
     * @return Collection<int, Penyewaan>
     */
    public function getActiveGaragesMonitoring(array $filters = []): Collection
    {
        $mode = $filters['mode'] ?? 'bulanan';
        $tahun = $filters['tahun'] ?? null;
        $bulan = $filters['bulan'] ?? null;
        $tipeGarasi = $filters['tipe_garasi'] ?? null;
        $penyewaId = $filters['penyewa_id'] ?? null;

        return Penyewaan::query()
            ->with(['penyewa.user', 'garasi', 'kendaraan', 'perpanjanganSewa'])
            ->where('status_sewa', StatusSewaEnum::Aktif->value)
            ->when($tahun !== null, function ($query) use ($tahun): void {
                $query->whereYear('tanggal_mulai', $tahun);
            })
            ->when($mode === 'harian' && $bulan !== null, function ($query) use ($bulan): void {
                $query->whereMonth('tanggal_mulai', $bulan);
            })
            ->when($tipeGarasi !== null && $tipeGarasi !== '', function ($query) use ($tipeGarasi): void {
                $query->whereHas('garasi', function ($garageQuery) use ($tipeGarasi): void {
                    $garageQuery->where('tipe_garasi', $tipeGarasi);
                });
            })
            ->when($penyewaId !== null, function ($query) use ($penyewaId): void {
                $query->where('penyewa_id', $penyewaId);
            })
            ->orderBy('garasi_id')
            ->get();
    }

    /**
     * Compute aggregate KPI summary metrics in IDR for a collection of transactions.
     *
     * @param  Collection<int, Penyewaan>  $transactions
     * @return array{
     *     total_transactions: int,
     *     active_transactions: int,
     *     total_tarif_pokok: float,
     *     total_biaya_listrik: float,
     *     total_sewa_baru: float,
     *     total_extensions: int,
     *     total_biaya_perpanjangan: float,
     *     grand_total_pendapatan: float
     * }
     */
    public function getSummaryMetrics(Collection $transactions): array
    {
        $totalTransactions = $transactions->count();
        $activeTransactions = $transactions->filter(fn (Penyewaan $item): bool => $item->isActive())->count();

        $totalTarifPokok = round((float) $transactions->sum('tarif_pokok'), 2);
        $totalBiayaListrik = round((float) $transactions->sum('biaya_listrik'), 2);
        $totalSewaBaru = round((float) $transactions->sum('total_bayar'), 2);

        $totalExtensions = (int) $transactions->sum(
            fn (Penyewaan $item): int => $item->perpanjanganSewa->count(),
        );
        $totalBiayaPerpanjangan = round((float) $transactions->sum(
            fn (Penyewaan $item): float => (float) $item->perpanjanganSewa->sum('biaya_perpanjangan'),
        ), 2);

        return [
            'total_transactions' => $totalTransactions,
            'active_transactions' => $activeTransactions,
            'total_tarif_pokok' => $totalTarifPokok,
            'total_biaya_listrik' => $totalBiayaListrik,
            'total_sewa_baru' => $totalSewaBaru,
            'total_extensions' => $totalExtensions,
            'total_biaya_perpanjangan' => $totalBiayaPerpanjangan,
            'grand_total_pendapatan' => round($totalSewaBaru + $totalBiayaPerpanjangan, 2),
        ];
    }

    /**
     * Get Indonesian month names map (1..12).
     *
     * @return array<int, string>
     */
    public function getMonthNames(): array
    {
        return self::MONTH_NAMES;
    }
}
