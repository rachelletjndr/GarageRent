<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusGarasiEnum;
use App\Enums\TipeGarasiEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkCreateGarageRequest;
use App\Http\Requests\UpdateGarageRequest;
use App\Models\Garasi;
use App\Services\GarageFactoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controller for Admin Master Garage Management & Bulk Garage Creation (FR-04).
 */
class GarageManagementController extends Controller
{
    public function __construct(private GarageFactoryService $garageService) {}

    /**
     * Display the garage management dashboard, bulk creation form, and garage inventory list.
     */
    public function index(Request $request): View
    {
        $tipeFilter = $request->query('tipe');
        $statusFilter = $request->query('status');
        $search = $request->query('search');

        $garages = $this->garageService->getGarages(
            is_string($tipeFilter) ? $tipeFilter : null,
            is_string($statusFilter) ? $statusFilter : null,
            is_string($search) ? $search : null,
        );

        $statistics = $this->garageService->getGarageStatistics();

        return view('admin.garages.index', [
            'garages' => $garages,
            'statistics' => $statistics,
            'tipeFilter' => is_string($tipeFilter) ? $tipeFilter : 'all',
            'statusFilter' => is_string($statusFilter) ? $statusFilter : 'all',
            'search' => is_string($search) ? $search : '',
        ]);
    }

    /**
     * Store newly generated garage units in bulk (FR-04, BR-03, BR-08, BR-09, BR-10).
     */
    public function store(BulkCreateGarageRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $tipeGarasi = TipeGarasiEnum::from((string) $validated['tipe_garasi']);
        $jumlah = (int) $validated['jumlah'];
        $hargaHarian = (float) $validated['harga_sewa_harian'];
        $tarifKwh = isset($validated['harga_kwh']) && $validated['harga_kwh'] !== null
            ? (float) $validated['harga_kwh']
            : null;

        $createdUnits = $this->garageService->bulkCreate(
            $tipeGarasi,
            $jumlah,
            $hargaHarian,
            $tarifKwh,
        );

        /** @var Garasi $firstUnit */
        $firstUnit = $createdUnits->first();
        /** @var Garasi $lastUnit */
        $lastUnit = $createdUnits->last();

        $idRange = $createdUnits->count() === 1
            ? $firstUnit->id
            : "{$firstUnit->id} s/d {$lastUnit->id}";

        $hargaBulananFormatted = number_format($firstUnit->getHargaSewaBulanan(), 0, ',', '.');

        return redirect()
            ->route('admin.garages.index', ['tipe' => $tipeGarasi->value])
            ->with(
                'status',
                "Berhasil membuat {$createdUnits->count()} unit {$tipeGarasi->label()} baru (Kode ID: {$idRange}) dengan tarif bulanan otomatis Rp {$hargaBulananFormatted}.",
            );
    }

    /**
     * Update an existing garage's daily tariff, electricity tariff, or status.
     */
    public function update(UpdateGarageRequest $request, Garasi $garasi): RedirectResponse
    {
        $validated = $request->validated();

        $hargaHarian = (float) $validated['harga_sewa_harian'];
        $tarifKwh = isset($validated['harga_kwh']) && $validated['harga_kwh'] !== null
            ? (float) $validated['harga_kwh']
            : null;
        $status = StatusGarasiEnum::from((string) $validated['status']);

        $updated = $this->garageService->updateGarage($garasi, $hargaHarian, $tarifKwh, $status);
        $hargaBulananFormatted = number_format($updated->getHargaSewaBulanan(), 0, ',', '.');

        return redirect()
            ->route('admin.garages.index', $request->only(['tipe', 'status_filter', 'search']))
            ->with(
                'status',
                "Data garasi {$updated->id} berhasil diperbarui (Tarif Bulanan Otomatis: Rp {$hargaBulananFormatted}, Status: {$updated->status->value}).",
            );
    }
}
