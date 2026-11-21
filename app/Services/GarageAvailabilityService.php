<?php

namespace App\Services;

use App\Enums\StatusGarasiEnum;
use App\Enums\StatusSewaEnum;
use App\Enums\TipeGarasiEnum;
use App\Enums\TipeKendaraanEnum;
use App\Models\Garasi;
use App\Models\Penyewaan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Service handling Real-Time and Scheduled Garage Availability and Vehicle Compatibility (FR-05, BR-01, BR-07).
 */
class GarageAvailabilityService
{
    /**
     * Get the list of compatible garage types for a given vehicle type (BR-01).
     *
     * - Sepeda / Sepeda Motor -> Garasi Sepeda ('S')
     * - Mobil Bensin -> Garasi Mobil ('C')
     * - Mobil Listrik -> Garasi Mobil ('C') OR Garasi Mobil Listrik ('E')
     *
     * @return array<int, TipeGarasiEnum>
     */
    public function getCompatibleGarageTypes(TipeKendaraanEnum $tipeKendaraan): array
    {
        return match ($tipeKendaraan) {
            TipeKendaraanEnum::Sepeda, TipeKendaraanEnum::SepedaMotor => [TipeGarasiEnum::Sepeda],
            TipeKendaraanEnum::MobilBensin => [TipeGarasiEnum::Mobil],
            TipeKendaraanEnum::MobilListrik => [TipeGarasiEnum::Mobil, TipeGarasiEnum::MobilListrik],
        };
    }

    /**
     * Check whether a specific garage is available on the requested date range [startDate, endDate] (FR-05).
     */
    public function isGarageAvailableForRange(Garasi $garasi, Carbon|string $startDate, Carbon|string $endDate): bool
    {
        $start = Carbon::parse($startDate)->toDateString();
        $end = Carbon::parse($endDate)->toDateString();

        $hasOverlap = Penyewaan::query()
            ->where('garasi_id', $garasi->id)
            ->where('status_sewa', StatusSewaEnum::Aktif->value)
            ->where('tanggal_mulai', '<=', $end)
            ->where('tanggal_selesai', '>=', $start)
            ->exists();

        if ($hasOverlap) {
            return false;
        }

        if ($garasi->status === StatusGarasiEnum::Used) {
            $hasAnyActiveRental = Penyewaan::query()
                ->where('garasi_id', $garasi->id)
                ->where('status_sewa', StatusSewaEnum::Aktif->value)
                ->exists();

            return $hasAnyActiveRental;
        }

        return true;
    }

    /**
     * Retrieve all garages that are compatible with the vehicle type and available during the date range (FR-05).
     *
     * @return Collection<int, Garasi>
     */
    public function getAvailableGarages(
        ?TipeKendaraanEnum $tipeKendaraan = null,
        Carbon|string|null $startDate = null,
        Carbon|string|null $endDate = null,
    ): Collection {
        $start = $startDate ? Carbon::parse($startDate)->toDateString() : Carbon::today()->toDateString();
        $end = $endDate ? Carbon::parse($endDate)->toDateString() : $start;

        $compatibleTypeValues = $tipeKendaraan
            ? array_map(fn (TipeGarasiEnum $t): string => $t->value, $this->getCompatibleGarageTypes($tipeKendaraan))
            : null;

        return Garasi::query()
            ->when(
                $compatibleTypeValues !== null,
                fn (Builder $query) => $query->whereIn('tipe_garasi', $compatibleTypeValues),
            )
            ->whereDoesntHave('penyewaan', function (Builder $query) use ($start, $end): void {
                $query->where('status_sewa', StatusSewaEnum::Aktif->value)
                    ->where('tanggal_mulai', '<=', $end)
                    ->where('tanggal_selesai', '>=', $start);
            })
            ->where(function (Builder $query): void {
                $query->where('status', StatusGarasiEnum::Available->value)
                    ->orWhereHas('penyewaan', function (Builder $subQuery): void {
                        $subQuery->where('status_sewa', StatusSewaEnum::Aktif->value);
                    });
            })
            ->orderBy('id')
            ->get();
    }
}
