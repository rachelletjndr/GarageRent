<?php

use App\Enums\StatusGarasiEnum;
use App\Enums\StatusSewaEnum;
use App\Enums\TipeGarasiEnum;
use App\Enums\TipeKendaraanEnum;
use App\Enums\TipeSewaEnum;
use App\Models\Garasi;
use App\Models\Kendaraan;
use App\Models\Penyewa;
use App\Models\Penyewaan;
use App\Models\PerpanjanganSewa;
use App\Models\User;

/**
 * Helper to seed a multi-dimensional dataset for report testing.
 *
 * @return array{admin: User, budiUser: User, budiPenyewa: Penyewa, sitiPenyewa: Penyewa}
 */
function seedReportDataset(): array
{
    $admin = User::factory()->admin()->create(['name' => 'Susi Susanti']);

    $budiUser = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi.report@gmail.test']);
    $budiPenyewa = Penyewa::factory()->approved($admin)->for($budiUser)->create([
        'nik' => '3171010105900011',
        'nama_lengkap' => 'Budi Santoso',
    ]);

    $sitiUser = User::factory()->create(['name' => 'Siti Rahmawati', 'email' => 'siti.report@gmail.test']);
    $sitiPenyewa = Penyewa::factory()->approved($admin)->for($sitiUser)->create([
        'nik' => '3171014208920022',
        'nama_lengkap' => 'Siti Rahmawati',
    ]);

    $s001 = Garasi::factory()->create([
        'id' => 'S001',
        'tipe_garasi' => TipeGarasiEnum::Sepeda,
        'harga_sewa_harian' => 20000.00,
        'tarif_kwh' => null,
        'status' => StatusGarasiEnum::Used,
    ]);

    $c001 = Garasi::factory()->create([
        'id' => 'C001',
        'tipe_garasi' => TipeGarasiEnum::Mobil,
        'harga_sewa_harian' => 50000.00,
        'tarif_kwh' => null,
        'status' => StatusGarasiEnum::Used,
    ]);

    $e001 = Garasi::factory()->create([
        'id' => 'E001',
        'tipe_garasi' => TipeGarasiEnum::MobilListrik,
        'harga_sewa_harian' => 100000.00,
        'tarif_kwh' => 2000.00,
        'status' => StatusGarasiEnum::Used,
    ]);

    $vario = Kendaraan::factory()->create([
        'penyewa_id' => $budiPenyewa->id,
        'tipe_kendaraan' => TipeKendaraanEnum::SepedaMotor,
        'plat_nomor' => 'B 1111 MTR',
        'merek' => 'Honda Vario 160',
    ]);

    $avanza = Kendaraan::factory()->mobilBensin('Toyota Avanza')->create([
        'penyewa_id' => $budiPenyewa->id,
        'plat_nomor' => 'B 2222 CAR',
    ]);

    $wuling = Kendaraan::factory()->mobilListrik(26.7, 'Wuling Air EV')->create([
        'penyewa_id' => $sitiPenyewa->id,
        'plat_nomor' => 'B 3333 EV',
    ]);

    // Transaction 1: Budi rents S001 on 2026-11-21 (5 days = Rp 100.000)
    Penyewaan::factory()->create([
        'nomor_transaksi' => 'TRX-REP-001',
        'penyewa_id' => $budiPenyewa->id,
        'garasi_id' => $s001->id,
        'kendaraan_id' => $vario->id,
        'tanggal_mulai' => '2026-11-21',
        'tanggal_selesai' => '2026-11-25',
        'tipe_sewa' => TipeSewaEnum::Harian,
        'durasi' => 5,
        'tarif_pokok' => 100000.00,
        'biaya_listrik' => 0.00,
        'total_bayar' => 100000.00,
        'status_sewa' => StatusSewaEnum::Aktif,
    ]);

    // Transaction 2: Budi rents C001 on 2026-11-21 (5 days = Rp 250.000) + Extended 5 days (+Rp 250.000)
    $trx2 = Penyewaan::factory()->create([
        'nomor_transaksi' => 'TRX-REP-002',
        'penyewa_id' => $budiPenyewa->id,
        'garasi_id' => $c001->id,
        'kendaraan_id' => $avanza->id,
        'tanggal_mulai' => '2026-11-21',
        'tanggal_selesai' => '2026-11-30',
        'tipe_sewa' => TipeSewaEnum::Harian,
        'durasi' => 5,
        'tarif_pokok' => 250000.00,
        'biaya_listrik' => 0.00,
        'total_bayar' => 250000.00,
        'status_sewa' => StatusSewaEnum::Aktif,
    ]);

    PerpanjanganSewa::factory()->create([
        'nomor_perpanjangan' => 'EXT-REP-001',
        'penyewaan_id' => $trx2->id,
        'admin_id' => $admin->id,
        'tanggal_selesai_lama' => '2026-11-25',
        'tanggal_selesai_baru' => '2026-11-30',
        'selisih_hari' => 5,
        'tarif_harian_snapshot' => 50000.00,
        'biaya_perpanjangan' => 250000.00,
    ]);

    // Transaction 3: Siti rents E001 on 2026-10-15 (Monthly = Rp 2.500.000 + EV Electricity Rp 53.400 = Rp 2.553.400)
    Penyewaan::factory()->create([
        'nomor_transaksi' => 'TRX-REP-003',
        'penyewa_id' => $sitiPenyewa->id,
        'garasi_id' => $e001->id,
        'kendaraan_id' => $wuling->id,
        'tanggal_mulai' => '2026-10-15',
        'tanggal_selesai' => '2026-11-14',
        'tipe_sewa' => TipeSewaEnum::Bulanan,
        'durasi' => 1,
        'tarif_pokok' => 2500000.00,
        'biaya_listrik' => 53400.00,
        'total_bayar' => 2553400.00,
        'status_sewa' => StatusSewaEnum::Aktif,
    ]);

    return [
        'admin' => $admin,
        'budiUser' => $budiUser,
        'budiPenyewa' => $budiPenyewa,
        'sitiPenyewa' => $sitiPenyewa,
    ];
}

test('admin can view monthly report (laporan bulanan per tahun)', function (): void {
    $data = seedReportDataset();

    $response = $this->actingAs($data['admin'])
        ->get(route('admin.reports.index', [
            'mode' => 'bulanan',
            'tahun' => 2026,
        ]));

    $response->assertOk()
        ->assertSee('Rekapitulasi Laporan Bulanan')
        ->assertSee('Oktober 2026')
        ->assertSee('November 2026')
        ->assertSee('TRX-REP-001')
        ->assertSee('TRX-REP-002')
        ->assertSee('TRX-REP-003')
        ->assertSee('Rp 3.153.400') // Grand total: 100.000 + 250.000 + 250.000 (ext) + 2.553.400 = 3.153.400
        ->assertSee('Rp 250.000'); // Paid extension fee
});

test('admin can view daily report (laporan harian per bulan)', function (): void {
    $data = seedReportDataset();

    // Filter November 2026 -> only TRX-REP-001 and TRX-REP-002 started in November 2026
    $response = $this->actingAs($data['admin'])
        ->get(route('admin.reports.index', [
            'mode' => 'harian',
            'tahun' => 2026,
            'bulan' => 11,
        ]));

    $response->assertOk()
        ->assertSee('Rekapitulasi Laporan Harian')
        ->assertSee('21 November 2026')
        ->assertSee('TRX-REP-001')
        ->assertSee('TRX-REP-002')
        ->assertDontSee('TRX-REP-003');
});

test('admin can filter report by garage type (laporan berdasarkan tipe garasi)', function (): void {
    $data = seedReportDataset();

    $response = $this->actingAs($data['admin'])
        ->get(route('admin.reports.index', [
            'mode' => 'tipe_garasi',
            'tahun' => 2026,
            'tipe_garasi' => TipeGarasiEnum::MobilListrik->value,
        ]));

    $response->assertOk()
        ->assertSee('Rekapitulasi Laporan Berdasarkan Tipe Garasi')
        ->assertSee('Garasi Mobil Listrik')
        ->assertSee('TRX-REP-003')
        ->assertDontSee('TRX-REP-001')
        ->assertDontSee('TRX-REP-002');
});

test('admin can filter report by tenant (laporan berdasarkan penyewa)', function (): void {
    $data = seedReportDataset();

    $response = $this->actingAs($data['admin'])
        ->get(route('admin.reports.index', [
            'mode' => 'penyewa',
            'tahun' => 2026,
            'penyewa_id' => $data['budiPenyewa']->id,
        ]));

    $response->assertOk()
        ->assertSee('Rekapitulasi Laporan Berdasarkan Penyewa')
        ->assertSee('Budi Santoso')
        ->assertSee('3171010105900011')
        ->assertSee('TRX-REP-001')
        ->assertSee('TRX-REP-002')
        ->assertDontSee('TRX-REP-003');
});

test('report page is view-only without export or print buttons', function (): void {
    $data = seedReportDataset();

    $this->actingAs($data['admin'])
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertDontSee('Ekspor CSV / Excel')
        ->assertDontSee('Cetak Laporan / PDF');
});

test('validation rejects invalid report filter parameters', function (): void {
    $data = seedReportDataset();

    $response = $this->actingAs($data['admin'])
        ->from(route('admin.reports.index'))
        ->get(route('admin.reports.index', [
            'mode' => 'invalid_mode',
            'bulan' => 15,
            'tipe_garasi' => 'pesawat',
        ]));

    $response->assertRedirect(route('admin.reports.index'));
    $response->assertSessionHasErrors(['mode', 'bulan', 'tipe_garasi']);
});

test('tenant is strictly forbidden from accessing admin rental reports', function (): void {
    $data = seedReportDataset();

    $this->actingAs($data['budiUser'])
        ->get(route('admin.reports.index'))
        ->assertForbidden();
});
