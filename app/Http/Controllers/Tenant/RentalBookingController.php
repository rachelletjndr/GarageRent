<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\StatusSewaEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRentalRequest;
use App\Models\Garasi;
use App\Models\Penyewaan;
use App\Services\RentalBookingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Controller handling Tenant New Garage Rental Booking, Availability Filtering,
 * Pricing Breakdown Preview, and Active Rental History (FR-05, FR-06, FR-07, FR-08).
 */
class RentalBookingController extends Controller
{
    public function __construct(private RentalBookingService $rentalBookingService) {}

    /**
     * Display the authenticated tenant's active and historical garage rentals.
     */
    public function index(Request $request): View
    {
        $penyewa = $request->user()?->penyewa;
        abort_if(! $penyewa, 403);

        $rentals = $this->rentalBookingService->getTenantRentals($penyewa);

        $activeCount = Penyewaan::query()
            ->where('penyewa_id', $penyewa->id)
            ->where('status_sewa', StatusSewaEnum::Aktif->value)
            ->count();

        $totalSpent = (float) Penyewaan::query()
            ->where('penyewa_id', $penyewa->id)
            ->where('status_sewa', '!=', StatusSewaEnum::Dibatalkan->value)
            ->sum('total_bayar');

        return view('tenant.rentals.index', [
            'penyewa' => $penyewa,
            'rentals' => $rentals,
            'activeCount' => $activeCount,
            'totalSpent' => $totalSpent,
        ]);
    }

    /**
     * Display the new garage rental booking form with interactive availability and pricing data.
     */
    public function create(Request $request): View
    {
        $penyewa = $request->user()?->penyewa;
        abort_if(! $penyewa, 403);

        $garages = Garasi::query()
            ->with(['penyewaan' => function ($query): void {
                $query->where('status_sewa', StatusSewaEnum::Aktif->value)
                    ->select(['id', 'garasi_id', 'tanggal_mulai', 'tanggal_selesai', 'status_sewa']);
            }])
            ->orderBy('id')
            ->get();

        $garagesPayload = $garages->map(fn (Garasi $garasi): array => [
            'id' => $garasi->id,
            'tipe_garasi' => $garasi->tipe_garasi->value,
            'label' => $garasi->tipe_garasi->label(),
            'harga_harian' => (float) $garasi->harga_sewa_harian,
            'harga_bulanan' => $garasi->getHargaSewaBulanan(),
            'tarif_kwh' => $garasi->tarif_kwh !== null ? (float) $garasi->tarif_kwh : 0.0,
            'status' => $garasi->status->value,
            'active_bookings' => $garasi->penyewaan->map(fn (Penyewaan $p): array => [
                'start' => $p->tanggal_mulai->toDateString(),
                'end' => $p->tanggal_selesai->toDateString(),
            ])->values()->all(),
        ])->values()->all();

        $savedVehicles = $penyewa->kendaraan()->latest('id')->get();

        return view('tenant.rentals.create', [
            'penyewa' => $penyewa,
            'defaultTanggalMulai' => now()->toDateString(),
            'garagesPayload' => $garagesPayload,
            'savedVehicles' => $savedVehicles,
            'selectedGarageId' => is_string($request->query('garasi_id')) ? $request->query('garasi_id') : '',
        ]);
    }

    /**
     * Validate and store a new garage rental transaction along with payment proof receipt.
     */
    public function store(StoreRentalRequest $request): RedirectResponse
    {
        $penyewa = $request->user()?->penyewa;
        abort_if(! $penyewa, 403);

        /** @var UploadedFile $buktiBayar */
        $buktiBayar = $request->file('bukti_bayar');

        $this->rentalBookingService->createNewRental(
            $penyewa,
            $request->validated(),
            $buktiBayar,
        );

        return redirect()
            ->route('tenant.rentals.index')
            ->with(
                'status',
                'Transaksi sewa garasi baru berhasil disimpan.',
            );
    }
}
