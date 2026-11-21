<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Penyewa;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with SIM-Garasi baseline accounts.
     */
    public function run(): void
    {
        $hashedPassword = Hash::make('password');

        // 1. Admin Account
        $admin = User::updateOrCreate(
            ['email' => 'admin@garasi.test'],
            [
                'name' => 'Citra Lestari (Admin)',
                'password' => $hashedPassword,
                'role' => RoleEnum::Admin,
                'email_verified_at' => now(),
            ],
        );

        // 2. Approved Tenant 1: Budi Santoso
        $budiUser = User::updateOrCreate(
            ['email' => 'budi@gmail.test'],
            [
                'name' => 'Budi Santoso',
                'password' => $hashedPassword,
                'role' => RoleEnum::Penyewa,
                'email_verified_at' => now(),
            ],
        );

        Penyewa::updateOrCreate(
            ['user_id' => $budiUser->id],
            [
                'nik' => '3171010105900001',
                'nama_lengkap' => 'Budi Santoso',
                'no_telp' => '081234567890',
                'status_verifikasi' => VerificationStatusEnum::Approved,
                'diverifikasi_oleh' => $admin->id,
                'tanggal_verifikasi' => now(),
                'catatan_penolakan' => null,
            ],
        );

        // 3. Approved Tenant 2: Siti Rahmawati
        $sitiUser = User::updateOrCreate(
            ['email' => 'siti@gmail.test'],
            [
                'name' => 'Siti Rahmawati',
                'password' => $hashedPassword,
                'role' => RoleEnum::Penyewa,
                'email_verified_at' => now(),
            ],
        );

        Penyewa::updateOrCreate(
            ['user_id' => $sitiUser->id],
            [
                'nik' => '3171014208920002',
                'nama_lengkap' => 'Siti Rahmawati',
                'no_telp' => '081398765432',
                'status_verifikasi' => VerificationStatusEnum::Approved,
                'diverifikasi_oleh' => $admin->id,
                'tanggal_verifikasi' => now(),
                'catatan_penolakan' => null,
            ],
        );

        // 4. Pending Tenant 3: Ahmad Fauzi
        $ahmadUser = User::updateOrCreate(
            ['email' => 'ahmad@gmail.test'],
            [
                'name' => 'Ahmad Fauzi',
                'password' => $hashedPassword,
                'role' => RoleEnum::Penyewa,
                'email_verified_at' => now(),
            ],
        );

        Penyewa::updateOrCreate(
            ['user_id' => $ahmadUser->id],
            [
                'nik' => '3171010311950003',
                'nama_lengkap' => 'Ahmad Fauzi',
                'no_telp' => '081512345678',
                'status_verifikasi' => VerificationStatusEnum::Pending,
                'diverifikasi_oleh' => null,
                'tanggal_verifikasi' => null,
                'catatan_penolakan' => null,
            ],
        );
    }
}
