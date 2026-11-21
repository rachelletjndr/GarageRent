<?php

namespace App\Services;

use App\Enums\StatusGarasiEnum;
use App\Enums\TipeGarasiEnum;
use App\Models\Garasi;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service handling Bulk Garage Creation, Automatic ID Generation (S001/C001/E001),
 * Quota Validation (Max 999 per type), Pricing Rules, and Status Management (FR-04, BR-03, BR-07, BR-08, BR-09, BR-10).
 */
class GarageFactoryService
{
    /**
     * Maximum number of garage units allowed per garage type (BR-09).
     */
    public const int MAX_GARAGES_PER_TYPE = 999;

    /**
     * Get the current highest sequence number for a given garage type.
     */
    public function getLastSequenceNumber(TipeGarasiEnum $tipeGarasi): int
    {
        $ids = Garasi::query()
            ->where('tipe_garasi', $tipeGarasi->value)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        return (int) $ids
            ->map(fn (string $id): int => (int) substr($id, 1))
            ->max();
    }

    /**
     * Format a sequence number into a 3-digit padded garage ID (e.g., S001, C001, E001) (BR-08).
     */
    public function formatGarageId(TipeGarasiEnum $tipeGarasi, int $sequenceNumber): string
    {
        return $tipeGarasi->prefix().str_pad((string) $sequenceNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Generate the next sequential garage ID for the given garage type (BR-08).
     */
    public function generateNextId(TipeGarasiEnum $tipeGarasi): string
    {
        $nextSequence = $this->getLastSequenceNumber($tipeGarasi) + 1;

        if ($nextSequence > self::MAX_GARAGES_PER_TYPE) {
            throw new InvalidArgumentException(
                "Kapasitas tipe {$tipeGarasi->label()} telah mencapai batas maksimal 999 unit.",
            );
        }

        return $this->formatGarageId($tipeGarasi, $nextSequence);
    }

    /**
     * Calculate the remaining creation quota for a specific garage type (BR-09).
     */
    public function getRemainingQuota(TipeGarasiEnum $tipeGarasi): int
    {
        $existingCount = Garasi::query()
            ->where('tipe_garasi', $tipeGarasi->value)
            ->count();

        $lastSequence = $this->getLastSequenceNumber($tipeGarasi);
        $usedCapacity = max($existingCount, $lastSequence);

        return max(0, self::MAX_GARAGES_PER_TYPE - $usedCapacity);
    }

    /**
     * Create multiple garage units in bulk within an atomic database transaction (FR-04).
     *
     * @return Collection<int, Garasi>
     */
    public function bulkCreate(
        TipeGarasiEnum $tipeGarasi,
        int $jumlah,
        float $hargaSewaHarian,
        ?float $tarifKwh = null,
    ): Collection {
        if ($jumlah < 1) {
            throw new InvalidArgumentException('Jumlah garasi minimal 1 dan total garasi per tipe maksimal 999.');
        }

        if ($hargaSewaHarian <= 0) {
            throw new InvalidArgumentException('Harga sewa harian harus lebih besar dari Rp 0.');
        }

        if ($tipeGarasi === TipeGarasiEnum::MobilListrik && ($tarifKwh === null || $tarifKwh <= 0)) {
            throw new InvalidArgumentException('Harga listrik per kWh wajib diisi untuk garasi mobil listrik (minimal Rp 100).');
        }

        $normalizedTarifKwh = $tipeGarasi === TipeGarasiEnum::MobilListrik ? $tarifKwh : null;

        return DB::transaction(function () use ($tipeGarasi, $jumlah, $hargaSewaHarian, $normalizedTarifKwh): Collection {
            $remainingQuota = $this->getRemainingQuota($tipeGarasi);

            if ($jumlah > $remainingQuota) {
                throw new InvalidArgumentException(
                    "Jumlah garasi minimal 1 dan total garasi per tipe maksimal 999. Sisa kuota untuk {$tipeGarasi->label()} adalah {$remainingQuota} unit.",
                );
            }

            $startSequence = $this->getLastSequenceNumber($tipeGarasi) + 1;
            $createdGarages = collect();

            for ($i = 0; $i < $jumlah; $i++) {
                $sequenceNumber = $startSequence + $i;
                $garageId = $this->formatGarageId($tipeGarasi, $sequenceNumber);

                $garasi = Garasi::create([
                    'id' => $garageId,
                    'tipe_garasi' => $tipeGarasi,
                    'harga_sewa_harian' => $hargaSewaHarian,
                    'tarif_kwh' => $normalizedTarifKwh,
                    'status' => StatusGarasiEnum::Available,
                ]);

                $createdGarages->push($garasi);
            }

            return $createdGarages;
        });
    }

    /**
     * Update an existing garage's daily price, electricity kWh tariff, and status.
     */
    public function updateGarage(
        Garasi $garasi,
        float $hargaSewaHarian,
        ?float $tarifKwh,
        StatusGarasiEnum $status,
    ): Garasi {
        if ($hargaSewaHarian <= 0) {
            throw new InvalidArgumentException('Harga sewa harian harus lebih besar dari Rp 0.');
        }

        if ($garasi->isElectricGarage() && ($tarifKwh === null || $tarifKwh <= 0)) {
            throw new InvalidArgumentException('Harga listrik per kWh wajib diisi untuk garasi mobil listrik (minimal Rp 100).');
        }

        $normalizedTarifKwh = $garasi->isElectricGarage() ? $tarifKwh : null;

        DB::transaction(function () use ($garasi, $hargaSewaHarian, $normalizedTarifKwh, $status): void {
            $garasi->update([
                'harga_sewa_harian' => $hargaSewaHarian,
                'tarif_kwh' => $normalizedTarifKwh,
                'status' => $status,
            ]);
        });

        return $garasi->refresh();
    }

    /**
     * Retrieve paginated list of garages with optional filters for type, status, and ID search.
     *
     * @return LengthAwarePaginator<int, Garasi>
     */
    public function getGarages(
        ?string $tipeFilter = null,
        ?string $statusFilter = null,
        ?string $search = null,
        int $perPage = 12,
    ): LengthAwarePaginator {
        return Garasi::query()
            ->when(
                $tipeFilter && in_array($tipeFilter, ['sepeda', 'mobil', 'mobil_listrik'], true),
                fn (Builder $query) => $query->where('tipe_garasi', $tipeFilter),
            )
            ->when(
                $statusFilter && in_array($statusFilter, ['available', 'used'], true),
                fn (Builder $query) => $query->where('status', $statusFilter),
            )
            ->when(
                $search !== null && trim($search) !== '',
                fn (Builder $query) => $query->where('id', 'like', '%'.strtoupper(trim($search)).'%'),
            )
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Get comprehensive inventory and quota statistics across all garage types.
     *
     * @return array{
     *     total: int,
     *     available: int,
     *     used: int,
     *     by_type: array<string, array{
     *         label: string,
     *         prefix: string,
     *         total: int,
     *         available: int,
     *         used: int,
     *         remaining_quota: int,
     *         next_id: string
     *     }>
     * }
     */
    public function getGarageStatistics(): array
    {
        $rows = Garasi::query()
            ->selectRaw('tipe_garasi, status, COUNT(*) as aggregate')
            ->groupBy('tipe_garasi', 'status')
            ->get();

        $total = 0;
        $available = 0;
        $used = 0;
        $byType = [];

        foreach (TipeGarasiEnum::cases() as $case) {
            $typeAvailable = (int) $rows
                ->where('tipe_garasi', $case)
                ->where('status', StatusGarasiEnum::Available)
                ->sum('aggregate');

            $typeUsed = (int) $rows
                ->where('tipe_garasi', $case)
                ->where('status', StatusGarasiEnum::Used)
                ->sum('aggregate');

            $typeTotal = $typeAvailable + $typeUsed;
            $remainingQuota = $this->getRemainingQuota($case);
            $nextSeq = $this->getLastSequenceNumber($case) + 1;

            $total += $typeTotal;
            $available += $typeAvailable;
            $used += $typeUsed;

            $byType[$case->value] = [
                'label' => $case->label(),
                'prefix' => $case->prefix(),
                'total' => $typeTotal,
                'available' => $typeAvailable,
                'used' => $typeUsed,
                'remaining_quota' => $remainingQuota,
                'next_id' => $nextSeq <= self::MAX_GARAGES_PER_TYPE
                    ? $this->formatGarageId($case, $nextSeq)
                    : 'PENUH (999)',
            ];
        }

        return [
            'total' => $total,
            'available' => $available,
            'used' => $used,
            'by_type' => $byType,
        ];
    }
}
