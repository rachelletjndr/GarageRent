<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Enums\StatusGarasiEnum;
use App\Enums\StatusSewaEnum;
use App\Enums\TipeGarasiEnum;
use App\Enums\TipeKendaraanEnum;
use App\Enums\TipeSewaEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Garasi;
use App\Models\Kendaraan;
use App\Models\Pembayaran;
use App\Models\Penyewa;
use App\Models\Penyewaan;
use App\Models\PerpanjanganSewa;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with SIM-Garasi baseline accounts, garages, vehicles, and rentals.
     */
    public function run(): void
    {
        $hashedPassword = Hash::make('password');

        // 1. Admin Account
        $admin = User::updateOrCreate(
            ['email' => 'admin@garasi.test'],
            [
                'name' => 'Susi Susanti',
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

        $budiPenyewa = Penyewa::updateOrCreate(
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

        $sitiPenyewa = Penyewa::updateOrCreate(
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

        // 5. Master Garasi Baseline Units
        $garages = [
            ['id' => 'S001', 'tipe_garasi' => TipeGarasiEnum::Sepeda, 'harga_sewa_harian' => 20000.00, 'tarif_kwh' => null, 'status' => StatusGarasiEnum::Used],
            ['id' => 'S002', 'tipe_garasi' => TipeGarasiEnum::Sepeda, 'harga_sewa_harian' => 20000.00, 'tarif_kwh' => null, 'status' => StatusGarasiEnum::Available],
            ['id' => 'C001', 'tipe_garasi' => TipeGarasiEnum::Mobil, 'harga_sewa_harian' => 50000.00, 'tarif_kwh' => null, 'status' => StatusGarasiEnum::Used],
            ['id' => 'C002', 'tipe_garasi' => TipeGarasiEnum::Mobil, 'harga_sewa_harian' => 50000.00, 'tarif_kwh' => null, 'status' => StatusGarasiEnum::Available],
            ['id' => 'E001', 'tipe_garasi' => TipeGarasiEnum::MobilListrik, 'harga_sewa_harian' => 100000.00, 'tarif_kwh' => 2000.00, 'status' => StatusGarasiEnum::Used],
            ['id' => 'E002', 'tipe_garasi' => TipeGarasiEnum::MobilListrik, 'harga_sewa_harian' => 100000.00, 'tarif_kwh' => 2000.00, 'status' => StatusGarasiEnum::Available],
        ];

        foreach ($garages as $garageData) {
            Garasi::updateOrCreate(['id' => $garageData['id']], $garageData);
        }

        // 6. Kendaraan Baseline
        $vario = Kendaraan::updateOrCreate(
            ['plat_nomor' => 'B 1234 XYZ'],
            [
                'penyewa_id' => $budiPenyewa->id,
                'tipe_kendaraan' => TipeKendaraanEnum::SepedaMotor,
                'merek' => 'Honda Vario 160',
                'kapasitas_kwh' => null,
            ],
        );

        Kendaraan::updateOrCreate(
            ['plat_nomor' => 'B 5678 ABC'],
            [
                'penyewa_id' => $budiPenyewa->id,
                'tipe_kendaraan' => TipeKendaraanEnum::MobilListrik,
                'merek' => 'Hyundai Ioniq 5',
                'kapasitas_kwh' => 58.00,
            ],
        );

        $wuling = Kendaraan::updateOrCreate(
            ['plat_nomor' => 'B 9999 EV'],
            [
                'penyewa_id' => $sitiPenyewa->id,
                'tipe_kendaraan' => TipeKendaraanEnum::MobilListrik,
                'merek' => 'Wuling Air EV',
                'kapasitas_kwh' => 26.70,
            ],
        );

        $avanza = Kendaraan::updateOrCreate(
            ['plat_nomor' => 'B 4321 CAR'],
            [
                'penyewa_id' => $budiPenyewa->id,
                'tipe_kendaraan' => TipeKendaraanEnum::MobilBensin,
                'merek' => 'Toyota Avanza 1.5',
                'kapasitas_kwh' => null,
            ],
        );

        // 7. Sample Active Rental Transactions & Payments
        $trx1 = Penyewaan::updateOrCreate(
            ['nomor_transaksi' => 'TRX-20261121-001'],
            [
                'penyewa_id' => $budiPenyewa->id,
                'garasi_id' => 'S001',
                'kendaraan_id' => $vario->id,
                'tanggal_mulai' => '2026-11-21',
                'tanggal_selesai' => '2026-11-25',
                'tipe_sewa' => TipeSewaEnum::Harian,
                'durasi' => 5,
                'tarif_pokok' => 100000.00,
                'biaya_listrik' => 0.00,
                'total_bayar' => 100000.00,
                'status_sewa' => StatusSewaEnum::Aktif,
            ],
        );

        Pembayaran::updateOrCreate(
            ['nomor_pembayaran' => 'PAY-20261121-001'],
            [
                'penyewaan_id' => $trx1->id,
                'perpanjangan_id' => null,
                'jenis_pembayaran' => 'sewa_baru',
                'nominal' => 100000.00,
                'bukti_transfer' => 'payments/proof_trx_001.jpg',
                'status_verifikasi' => 'valid',
                'diverifikasi_oleh' => $admin->id,
            ],
        );

        $trx2 = Penyewaan::updateOrCreate(
            ['nomor_transaksi' => 'TRX-20261121-002'],
            [
                'penyewa_id' => $budiPenyewa->id,
                'garasi_id' => 'C001',
                'kendaraan_id' => $avanza->id,
                'tanggal_mulai' => '2026-11-21',
                'tanggal_selesai' => '2026-11-25',
                'tipe_sewa' => TipeSewaEnum::Harian,
                'durasi' => 5,
                'tarif_pokok' => 250000.00,
                'biaya_listrik' => 0.00,
                'total_bayar' => 250000.00,
                'status_sewa' => StatusSewaEnum::Aktif,
            ],
        );

        Pembayaran::updateOrCreate(
            ['nomor_pembayaran' => 'PAY-20261121-002'],
            [
                'penyewaan_id' => $trx2->id,
                'perpanjangan_id' => null,
                'jenis_pembayaran' => 'sewa_baru',
                'nominal' => 250000.00,
                'bukti_transfer' => 'payments/proof_trx_002.jpg',
                'status_verifikasi' => 'valid',
                'diverifikasi_oleh' => $admin->id,
            ],
        );

        $trx3 = Penyewaan::updateOrCreate(
            ['nomor_transaksi' => 'TRX-20261121-003'],
            [
                'penyewa_id' => $sitiPenyewa->id,
                'garasi_id' => 'E001',
                'kendaraan_id' => $wuling->id,
                'tanggal_mulai' => '2026-11-21',
                'tanggal_selesai' => '2026-12-20',
                'tipe_sewa' => TipeSewaEnum::Bulanan,
                'durasi' => 1,
                'tarif_pokok' => 2500000.00,
                'biaya_listrik' => 53400.00,
                'total_bayar' => 2553400.00,
                'status_sewa' => StatusSewaEnum::Aktif,
            ],
        );

        Pembayaran::updateOrCreate(
            ['nomor_pembayaran' => 'PAY-20261121-003'],
            [
                'penyewaan_id' => $trx3->id,
                'perpanjangan_id' => null,
                'jenis_pembayaran' => 'sewa_baru',
                'nominal' => 2553400.00,
                'bukti_transfer' => 'payments/proof_trx_003.jpg',
                'status_verifikasi' => 'valid',
                'diverifikasi_oleh' => $admin->id,
            ],
        );

        // 8. Sample Rental Extension (Perpanjangan Sewa)
        $ext1 = PerpanjanganSewa::updateOrCreate(
            ['nomor_perpanjangan' => 'EXT-20261125-001'],
            [
                'penyewaan_id' => $trx2->id,
                'admin_id' => $admin->id,
                'tanggal_selesai_lama' => '2026-11-25',
                'tanggal_selesai_baru' => '2026-11-30',
                'selisih_hari' => 5,
                'tarif_harian_snapshot' => 50000.00,
                'biaya_perpanjangan' => 250000.00,
            ],
        );

        $trx2->update([
            'tanggal_selesai' => '2026-11-30',
        ]);

        Pembayaran::updateOrCreate(
            ['nomor_pembayaran' => 'PAY-20261125-001'],
            [
                'penyewaan_id' => $trx2->id,
                'perpanjangan_id' => $ext1->id,
                'jenis_pembayaran' => 'perpanjangan',
                'nominal' => 250000.00,
                'bukti_transfer' => 'payments/proof_ext_001.jpg',
                'status_verifikasi' => 'valid',
                'diverifikasi_oleh' => $admin->id,
            ],
        );
    }
}
