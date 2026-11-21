<?php

namespace App\Services;

use App\Enums\TipeSewaEnum;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Service handling Calendar Month Calculation, Leap Year Awareness,
 * Dynamic Daily Duration Limits (daysInMonth - 1), and Start/End Date Rules (FR-06, BR-04, BR-05).
 */
class RentalCalendarValidator
{
    /**
     * Validate that the rental start date is today or in the future (no past dates allowed) (BR-05).
     */
    public function validateTanggalMulai(Carbon|string $tanggalMulai): bool
    {
        $start = Carbon::parse($tanggalMulai)->startOfDay();
        $today = Carbon::today()->startOfDay();

        return $start->greaterThanOrEqualTo($today);
    }

    /**
     * Get the exact number of days in the calendar month of the start date (including leap years) (BR-04).
     */
    public function getDaysInCalendarMonth(Carbon|string $tanggalMulai): int
    {
        return Carbon::parse($tanggalMulai)->daysInMonth;
    }

    /**
     * Get the maximum allowed daily rental duration for the start date's calendar month: (daysInMonth - 1) (BR-04).
     *
     * Examples:
     * - February 2026 (28 days) -> max 27 days
     * - March 2026 (31 days) -> max 30 days
     * - February 2028 (Leap year, 29 days) -> max 28 days
     */
    public function getMaxDurasiHarian(Carbon|string $tanggalMulai): int
    {
        return $this->getDaysInCalendarMonth($tanggalMulai) - 1;
    }

    /**
     * Validate whether the requested rental duration satisfies the dynamic calendar rules (BR-04).
     */
    public function validateDurasi(Carbon|string $tanggalMulai, TipeSewaEnum $tipeSewa, int $durasi): bool
    {
        if ($durasi < 1) {
            return false;
        }

        if ($tipeSewa === TipeSewaEnum::Bulanan) {
            return $durasi === 1;
        }

        return $durasi <= $this->getMaxDurasiHarian($tanggalMulai);
    }

    /**
     * Calculate the exact rental end date based on actual calendar days (FR-06, BR-04, BR-05).
     *
     * - Daily (n days): Start Date + (n - 1) days.
     * - Monthly (1 calendar month): Start Date + 1 calendar month - 1 day
     *   (e.g., 2026-02-15 -> 2026-03-14 [28 days]; 2026-11-21 -> 2026-12-20 [30 days]).
     */
    public function hitungTanggalSelesai(Carbon|string $tanggalMulai, TipeSewaEnum $tipeSewa, int $durasi): Carbon
    {
        if (! $this->validateDurasi($tanggalMulai, $tipeSewa, $durasi)) {
            $maxHarian = $this->getMaxDurasiHarian($tanggalMulai);

            throw new InvalidArgumentException(
                $tipeSewa === TipeSewaEnum::Harian
                    ? "Durasi sewa harian maksimal adalah jumlah hari bulan kalender dikurangi 1 hari (Maksimal {$maxHarian} hari)."
                    : 'Durasi sewa bulanan dalam satu kali pemesanan maksimal 1 bulan kalender.',
            );
        }

        $start = Carbon::parse($tanggalMulai)->startOfDay();

        if ($tipeSewa === TipeSewaEnum::Harian) {
            return $start->copy()->addDays($durasi - 1);
        }

        return $start->copy()->addMonthNoOverflow()->subDay();
    }

    /**
     * Calculate the total actual calendar days covered between start date and end date (inclusive).
     */
    public function hitungJumlahHariAktual(Carbon|string $tanggalMulai, Carbon|string $tanggalSelesai): int
    {
        $start = Carbon::parse($tanggalMulai)->startOfDay();
        $end = Carbon::parse($tanggalSelesai)->startOfDay();

        return (int) $start->diffInDays($end) + 1;
    }
}
