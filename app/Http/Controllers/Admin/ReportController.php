<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusSewaEnum;
use App\Enums\TipeGarasiEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\FilterRentalReportRequest;
use App\Models\Penyewa;
use App\Services\RentalReportService;
use Illuminate\Contracts\View\View;

/**
 * Controller managing Multi-Dimensional Rental Reports (FR-10, UC-08, UC-09)
 * exclusively for Admin users:
 * - Laporan Bulanan (Per bulan dalam tahun tertentu)
 * - Laporan Harian (Per hari dalam bulan tertentu)
 * - Laporan Berdasarkan Tipe Garasi (Sepeda, Mobil, Mobil Listrik)
 * - Laporan Berdasarkan Penyewa
 */
class ReportController extends Controller
{
    public function __construct(
        private RentalReportService $reportService,
    ) {}

    /**
     * Render the multi-dimensional rental report page with interactive filters and summaries.
     */
    public function index(FilterRentalReportRequest $request): View
    {
        $filters = $request->normalizedFilters();

        $transactions = $this->reportService->getFilteredTransactions($filters);
        $summaryMetrics = $this->reportService->getSummaryMetrics($transactions);

        $monthlyReport = $this->reportService->getMonthlyReport(
            $filters['tahun'],
            $filters['tipe_garasi'],
            $filters['penyewa_id'],
        );

        $dailyReport = $this->reportService->getDailyReport(
            $filters['tahun'],
            $filters['bulan'],
            $filters['tipe_garasi'],
            $filters['penyewa_id'],
        );

        $garageTypeReport = $this->reportService->getGarageTypeReport(
            $filters['tahun'],
            $filters['mode'] === 'harian' ? $filters['bulan'] : null,
            $filters['tipe_garasi'],
        );

        $tenantReport = $this->reportService->getTenantReport(
            $filters['penyewa_id'],
            $filters['tahun'],
            $filters['mode'] === 'harian' ? $filters['bulan'] : null,
        );

        $activeMonitoring = $this->reportService->getActiveGaragesMonitoring($filters);

        $allTenants = Penyewa::query()
            ->orderBy('nama_lengkap')
            ->get(['id', 'nik', 'nama_lengkap']);

        return view('admin.reports.index', [
            'filters' => $filters,
            'transactions' => $transactions,
            'summaryMetrics' => $summaryMetrics,
            'monthlyReport' => $monthlyReport,
            'dailyReport' => $dailyReport,
            'garageTypeReport' => $garageTypeReport,
            'tenantReport' => $tenantReport,
            'activeMonitoring' => $activeMonitoring,
            'allTenants' => $allTenants,
            'garageTypes' => TipeGarasiEnum::cases(),
            'rentalStatuses' => StatusSewaEnum::cases(),
            'monthNames' => $this->reportService->getMonthNames(),
        ]);
    }
}
