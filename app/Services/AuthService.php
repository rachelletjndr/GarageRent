<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Penyewa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

/**
 * Service handling Tenant Registration and Multi-Role Authentication Rules (FR-01 & FR-02).
 */
class AuthService
{
    /**
     * Register a new tenant user and create their pending KTP profile atomically.
     *
     * @param  array{nik: string, name: string, no_telp: string, email: string, password: string}  $data
     */
    public function registerTenant(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => RoleEnum::Penyewa,
                'email_verified_at' => now(),
            ]);

            Penyewa::create([
                'user_id' => $user->id,
                'nik' => $data['nik'],
                'nama_lengkap' => $data['name'],
                'no_telp' => $data['no_telp'],
                'status_verifikasi' => VerificationStatusEnum::Pending,
            ]);

            return $user;
        });
    }

    /**
     * Validate whether an authenticated user is eligible to start an active login session (FR-02).
     *
     * @throws ValidationException
     */
    public function validateLoginEligibility(User $user): void
    {
        if (! $user->isPenyewa()) {
            return;
        }

        $penyewa = $user->penyewa;

        if (! $penyewa) {
            return;
        }

        if ($penyewa->isPending()) {
            throw ValidationException::withMessages([
                Fortify::username() => [
                    'Akun Anda sedang menunggu persetujuan dan verifikasi KTP oleh Admin.',
                ],
            ]);
        }

        if ($penyewa->isRejected()) {
            $alasan = $penyewa->catatan_penolakan
                ? ' Alasan penolakan: '.$penyewa->catatan_penolakan
                : '';

            throw ValidationException::withMessages([
                Fortify::username() => [
                    'Pendaftaran akun Anda telah ditolak oleh Admin.'.$alasan,
                ],
            ]);
        }
    }
}
