<?php

namespace App\Services;

use App\Enums\VerificationStatusEnum;
use App\Models\Penyewa;
use App\Models\Penyewaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service aggregating cross-module data for the unified Admin and Tenant Dashboard
 * (integrating Authentication & KTP Verification, Garage Management, New Rentals,
 * Rental Extensions, and Financial Reports).
 */
class DashboardService
{
    public function __construct(
        private AdminVerificationService $verificationService,
        private GarageFactoryService $garageService,
        private RentalExtensionService $extensionService,
        private RentalReportService $reportService,
    ) {}

    /**
     * Build complete dashboard payload tailored to the authenticated user's role.
     *
     * @return array<string, mixed>
     */
    public function getDashboardData(User $user): array
    {
        $user->loadMissing('penyewa');
        $garageStats = $this->garageService->getGarageStatistics();

        if ($user->isAdmin()) {
            $allTransactions = $this->reportService->getFilteredTransactions([]);
            $financialMetrics = $this->reportService->getSummaryMetrics($allTransactions);
            $activeRentals = $this->extensionService->getActiveRentals();
            $pendingTenants = Penyewa::query()
                ->with('user')
                ->where('status_verifikasi', VerificationStatusEnum::Pending->value)
                ->latest('id')
                ->limit(5)
                ->get();

            return [
                'user' => $user,
                'penyewa' => null,
                'garageStats' => $garageStats,
                'verificationStats' => $this->verificationService->getVerificationStatistics(),
                'extensionStats' => $this->extensionService->getExtensionStatistics(),
                'financialMetrics' => $financialMetrics,
                'activeRentals' => $activeRentals,
                'pendingTenants' => $pendingTenants,
                'tenantSummary' => null,
                'tenantActiveRentals' => new Collection,
            ];
        }

        $penyewa = $user->penyewa;
        $tenantRentals = $penyewa
            ? Penyewaan::query()
                ->with(['garasi', 'kendaraan', 'pembayaran', 'perpanjanganSewa'])
                ->where('penyewa_id', $penyewa->id)
                ->latest('id')
                ->get()
            : new Collection;

        $tenantActiveRentals = $tenantRentals->filter(fn (Penyewaan $r): bool => $r->isActive())->values();
        $totalExtensionCount = (int) $tenantRentals->sum(fn (Penyewaan $r): int => $r->perpanjanganSewa->count());
        $totalExtensionFee = round((float) $tenantRentals->sum(
            fn (Penyewaan $r): float => (float) $r->perpanjanganSewa->sum('biaya_perpanjangan'),
        ), 2);
        $totalSewaBaru = round((float) $tenantRentals->sum('total_bayar'), 2);

        return [
            'user' => $user,
            'penyewa' => $penyewa,
            'garageStats' => $garageStats,
            'verificationStats' => null,
            'extensionStats' => null,
            'financialMetrics' => null,
            'activeRentals' => new Collection,
            'pendingTenants' => new Collection,
            'tenantSummary' => [
                'active_count' => $tenantActiveRentals->count(),
                'total_transactions' => $tenantRentals->count(),
                'vehicle_count' => $penyewa ? $penyewa->kendaraan()->count() : 0,
                'extension_count' => $totalExtensionCount,
                'total_sewa_baru' => $totalSewaBaru,
                'total_extension_fee' => $totalExtensionFee,
                'grand_total_spent' => round($totalSewaBaru + $totalExtensionFee, 2),
            ],
            'tenantActiveRentals' => $tenantActiveRentals,
        ];
    }
}
