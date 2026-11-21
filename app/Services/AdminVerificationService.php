<?php

namespace App\Services;

use App\Enums\VerificationStatusEnum;
use App\Models\Penyewa;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service handling Admin KTP Verification and Tenant Management (FR-03).
 */
class AdminVerificationService
{
    /**
     * Retrieve paginated list of tenant registrations with optional status filter and search query.
     *
     * @return LengthAwarePaginator<int, Penyewa>
     */
    public function getTenantRegistrations(?string $statusFilter = null, ?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        return Penyewa::query()
            ->with(['user', 'verifier'])
            ->when(
                $statusFilter && in_array($statusFilter, ['pending', 'approved', 'rejected'], true),
                fn (Builder $query) => $query->where('status_verifikasi', $statusFilter),
            )
            ->when(
                $search !== null && trim($search) !== '',
                function (Builder $query) use ($search): void {
                    $term = '%'.trim($search).'%';
                    $query->where(function (Builder $subQuery) use ($term): void {
                        $subQuery->where('nik', 'like', $term)
                            ->orWhere('nama_lengkap', 'like', $term)
                            ->orWhere('no_telp', 'like', $term)
                            ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->where('email', 'like', $term));
                    });
                },
            )
            ->orderByRaw("CASE WHEN status_verifikasi = 'pending' THEN 0 WHEN status_verifikasi = 'rejected' THEN 1 ELSE 2 END")
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Get summary counts for tenant verification statuses.
     *
     * @return array{total: int, pending: int, approved: int, rejected: int}
     */
    public function getVerificationStatistics(): array
    {
        $counts = Penyewa::query()
            ->selectRaw('status_verifikasi, COUNT(*) as aggregate')
            ->groupBy('status_verifikasi')
            ->pluck('aggregate', 'status_verifikasi');

        $pending = (int) ($counts[VerificationStatusEnum::Pending->value] ?? 0);
        $approved = (int) ($counts[VerificationStatusEnum::Approved->value] ?? 0);
        $rejected = (int) ($counts[VerificationStatusEnum::Rejected->value] ?? 0);

        return [
            'total' => $pending + $approved + $rejected,
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
        ];
    }

    /**
     * Parse and validate the 16-digit Indonesian KTP NIK structure to assist Admin verification.
     *
     * Format NIK KTP Standar Dukcapil (16 Digit):
     * - Digit 1-2: Kode Provinsi
     * - Digit 3-4: Kode Kabupaten/Kota
     * - Digit 5-6: Kode Kecamatan
     * - Digit 7-12: Tanggal Lahir (DDMMYY; untuk perempuan DD ditambah 40)
     * - Digit 13-16: Nomor Urut Registrasi
     *
     * @return array{
     *     is_valid_format: bool,
     *     kode_wilayah: string,
     *     tanggal_lahir_kode: string,
     *     jenis_kelamin_estimasi: string,
     *     nomor_urut: string
     * }
     */
    public function inspectKtpNik(string $nik): array
    {
        if (preg_match('/^\d{16}$/', $nik) !== 1) {
            return [
                'is_valid_format' => false,
                'kode_wilayah' => '-',
                'tanggal_lahir_kode' => '-',
                'jenis_kelamin_estimasi' => 'Tidak Valid',
                'nomor_urut' => '-',
            ];
        }

        $provinsi = substr($nik, 0, 2);
        $kota = substr($nik, 2, 2);
        $kecamatan = substr($nik, 4, 2);
        $hari = (int) substr($nik, 6, 2);
        $bulan = (int) substr($nik, 8, 2);
        $tahun = substr($nik, 10, 2);
        $urut = substr($nik, 12, 4);

        $isFemale = $hari > 40;
        $normalizedDay = $isFemale ? $hari - 40 : $hari;

        $isDateLogical = $normalizedDay >= 1 && $normalizedDay <= 31 && $bulan >= 1 && $bulan <= 12 && $urut !== '0000';

        return [
            'is_valid_format' => $isDateLogical,
            'kode_wilayah' => "{$provinsi}.{$kota}.{$kecamatan}",
            'tanggal_lahir_kode' => sprintf('%02d-%02d-%s', $normalizedDay, $bulan, $tahun),
            'jenis_kelamin_estimasi' => $isFemale ? 'Perempuan' : 'Laki-laki',
            'nomor_urut' => $urut,
        ];
    }

    /**
     * Approve a tenant registration and mark their KTP as verified.
     * Only pending tenant registrations can be approved.
     */
    public function approveTenant(Penyewa $penyewa, User $admin): Penyewa
    {
        if (! $admin->isAdmin()) {
            throw new InvalidArgumentException('Hanya pengguna dengan role Admin yang dapat memverifikasi pendaftaran penyewa.');
        }

        if (! $penyewa->isPending()) {
            throw new InvalidArgumentException('Status verifikasi penyewa yang sudah diputuskan tidak dapat diubah kembali.');
        }

        DB::transaction(function () use ($penyewa, $admin): void {
            $penyewa->approve($admin);
        });

        return $penyewa->refresh();
    }

    /**
     * Reject a tenant registration with an optional rejection note.
     * Only pending tenant registrations can be rejected.
     */
    public function rejectTenant(Penyewa $penyewa, User $admin, ?string $catatanPenolakan = null): Penyewa
    {
        if (! $admin->isAdmin()) {
            throw new InvalidArgumentException('Hanya pengguna dengan role Admin yang dapat menolak pendaftaran penyewa.');
        }

        if (! $penyewa->isPending()) {
            throw new InvalidArgumentException('Status verifikasi penyewa yang sudah diputuskan tidak dapat diubah kembali.');
        }

        DB::transaction(function () use ($penyewa, $admin, $catatanPenolakan): void {
            $note = $catatanPenolakan !== null && trim($catatanPenolakan) !== ''
                ? trim($catatanPenolakan)
                : 'Data identitas KTP tidak sesuai atau tidak memenuhi syarat verifikasi.';

            $penyewa->reject($admin, $note);
        });

        return $penyewa->refresh();
    }
}
