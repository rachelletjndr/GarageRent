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
 * Service handling Tenant Registration, Re-registration for Rejected Accounts,
 * and Multi-Role Authentication Rules (FR-01 & FR-02).
 */
class AuthService
{
    /**
     * Register a new tenant user or allow a previously rejected tenant to re-register
     * using their previous email and/or NIK KTP atomically.
     *
     * @param  array{nik: string, name: string, no_telp: string, email: string, password: string}  $data
     */
    public function registerTenant(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $existingUserByEmail = User::query()
                ->with('penyewa')
                ->where('email', $data['email'])
                ->first();

            $existingPenyewaByNik = Penyewa::query()
                ->with('user')
                ->where('nik', $data['nik'])
                ->first();

            // If both exist on different rejected records, remove the old NIK-bound rejected user record first
            if (
                $existingUserByEmail
                && $existingPenyewaByNik
                && $existingPenyewaByNik->user_id !== $existingUserByEmail->id
                && $existingPenyewaByNik->isRejected()
            ) {
                $oldNikUser = $existingPenyewaByNik->user;
                $existingPenyewaByNik->delete();
                $oldNikUser?->delete();
                $existingPenyewaByNik = null;
            }

            // Case 1: Re-registering with an email from a previously rejected account
            if ($existingUserByEmail && $existingUserByEmail->penyewa?->isRejected()) {
                $existingUserByEmail->update([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                    'role' => RoleEnum::Penyewa,
                    'email_verified_at' => now(),
                ]);

                $existingUserByEmail->penyewa->update([
                    'nik' => $data['nik'],
                    'nama_lengkap' => $data['name'],
                    'no_telp' => $data['no_telp'],
                    'status_verifikasi' => VerificationStatusEnum::Pending,
                    'diverifikasi_oleh' => null,
                    'tanggal_verifikasi' => null,
                    'catatan_penolakan' => null,
                ]);

                return $existingUserByEmail->fresh(['penyewa']) ?? $existingUserByEmail;
            }

            // Case 2: Re-registering with a NIK from a previously rejected account under a new email
            if ($existingPenyewaByNik && $existingPenyewaByNik->isRejected() && $existingPenyewaByNik->user) {
                $user = $existingPenyewaByNik->user;
                $user->update([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                    'role' => RoleEnum::Penyewa,
                    'email_verified_at' => now(),
                ]);

                $existingPenyewaByNik->update([
                    'nik' => $data['nik'],
                    'nama_lengkap' => $data['name'],
                    'no_telp' => $data['no_telp'],
                    'status_verifikasi' => VerificationStatusEnum::Pending,
                    'diverifikasi_oleh' => null,
                    'tanggal_verifikasi' => null,
                    'catatan_penolakan' => null,
                ]);

                return $user->fresh(['penyewa']) ?? $user;
            }

            // Case 3: Fresh registration
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
                    'Maaf, pendaftaran akun Anda belum disetujui oleh Admin.'.$alasan.' Silakan lakukan pendaftaran kembali dengan data yang sesuai.',
                ],
            ]);
        }
    }
}
