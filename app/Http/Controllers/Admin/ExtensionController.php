<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TipeGarasiEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExtensionRequest;
use App\Models\Penyewaan;
use App\Models\PerpanjanganSewa;
use App\Services\RentalExtensionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controller managing Rental Extension (Perpanjangan Masa Sewa Garasi)
 * exclusively for Admin users (FR-09, AC-15, AC-16).
 */
class ExtensionController extends Controller
{
    public function __construct(
        private RentalExtensionService $extensionService,
    ) {}

    /**
     * Display the active rented garages list, interactive extension form,
     * and extension history table.
     */
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $tipeGarasi = $request->query('tipe_garasi');
        $selectedRentalId = $request->query('penyewaan_id');

        $activeRentals = $this->extensionService->getActiveRentals(
            is_string($search) ? $search : null,
            is_string($tipeGarasi) ? $tipeGarasi : null,
        );

        $extensionHistory = $this->extensionService->getExtensionHistory(
            is_string($search) ? $search : null,
        );

        $statistics = $this->extensionService->getExtensionStatistics();

        $activeRentalsPayload = $activeRentals->map(fn (Penyewaan $rental): array => [
            'id' => $rental->id,
            'nomor_transaksi' => $rental->nomor_transaksi,
            'garasi_id' => $rental->garasi_id,
            'tipe_garasi_label' => $rental->garasi?->tipe_garasi?->label() ?? '-',
            'harga_sewa_harian' => (float) ($rental->garasi?->harga_sewa_harian ?? 0),
            'penyewa_nama' => $rental->penyewa?->nama_lengkap ?? '-',
            'penyewa_nik' => $rental->penyewa?->nik ?? '-',
            'kendaraan_plat' => $rental->kendaraan?->plat_nomor ?? '-',
            'kendaraan_merek' => $rental->kendaraan?->merek ?? '-',
            'tanggal_mulai' => $rental->tanggal_mulai->toDateString(),
            'tanggal_mulai_formatted' => $rental->tanggal_mulai->format('d M Y'),
            'tanggal_selesai_lama' => $rental->tanggal_selesai->toDateString(),
            'tanggal_selesai_lama_formatted' => $rental->tanggal_selesai->format('d M Y'),
            'min_tanggal_selesai_baru' => $rental->tanggal_selesai->copy()->addDay()->toDateString(),
            'default_tanggal_selesai_baru' => $rental->tanggal_selesai->copy()->addDays(5)->toDateString(),
            'extension_count' => $rental->perpanjanganSewa->count(),
        ])->values()->all();

        $lastExtendedRentalId = PerpanjanganSewa::query()
            ->whereIn('penyewaan_id', $activeRentals->pluck('id'))
            ->latest('id')
            ->value('penyewaan_id');

        $defaultRentalId = is_numeric($selectedRentalId)
            ? (int) $selectedRentalId
            : ($lastExtendedRentalId ?? ($activeRentalsPayload[0]['id'] ?? ''));

        $initialRentalId = old('penyewaan_id', $defaultRentalId);

        return view('admin.extensions.index', [
            'activeRentals' => $activeRentals,
            'activeRentalsPayload' => $activeRentalsPayload,
            'extensionHistory' => $extensionHistory,
            'statistics' => $statistics,
            'garageTypes' => TipeGarasiEnum::cases(),
            'currentSearch' => $search,
            'currentType' => $tipeGarasi,
            'initialRentalId' => $initialRentalId,
        ]);
    }

    /**
     * Process and persist a rental extension transaction (FR-09).
     */
    public function store(StoreExtensionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        /** @var Penyewaan $penyewaan */
        $penyewaan = Penyewaan::query()->with(['garasi', 'penyewa'])->findOrFail((int) $validated['penyewaan_id']);

        $perpanjangan = $this->extensionService->extendRental(
            $penyewaan,
            $request->user(),
            (string) $validated['tanggal_selesai_baru'],
            $request->file('bukti_bayar_perpanjangan'),
        );

        $formattedBiaya = number_format($perpanjangan->biaya_perpanjangan, 0, ',', '.');

        return redirect()
            ->route('admin.extensions.index')
            ->with(
                'status',
                "Perpanjangan sewa {$perpanjangan->nomor_perpanjangan} untuk Garasi {$penyewaan->garasi_id} ({$penyewaan->penyewa?->nama_lengkap}) berhasil disimpan! Tanggal selesai diperbarui dari {$perpanjangan->tanggal_selesai_lama->format('d/m/Y')} menjadi {$perpanjangan->tanggal_selesai_baru->format('d/m/Y')} (+{$perpanjangan->selisih_hari} hari). Biaya Perpanjangan: Rp {$formattedBiaya} (Lunas).",
            );
    }
}
