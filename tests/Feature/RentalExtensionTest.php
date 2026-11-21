<?php

use App\Enums\StatusGarasiEnum;
use App\Enums\StatusSewaEnum;
use App\Enums\TipeGarasiEnum;
use App\Enums\TipeKendaraanEnum;
use App\Enums\TipeSewaEnum;
use App\Models\Garasi;
use App\Models\Kendaraan;
use App\Models\Pembayaran;
use App\Models\Penyewa;
use App\Models\Penyewaan;
use App\Models\PerpanjanganSewa;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
});

test('AC-15: admin can view currently rented active garages and extend rental duration', function (): void {
    $admin = User::factory()->admin()->create();
    $tenantUser = User::factory()->create();
    $penyewa = Penyewa::factory()->approved($admin)->for($tenantUser)->create([
        'nama_lengkap' => 'Budi Santoso',
    ]);

    $garasi = Garasi::factory()->create([
        'id' => 'C001',
        'tipe_garasi' => TipeGarasiEnum::Mobil,
        'harga_sewa_harian' => 50000.00,
        'tarif_kwh' => null,
        'status' => StatusGarasiEnum::Used,
    ]);

    $kendaraan = Kendaraan::factory()->create([
        'penyewa_id' => $penyewa->id,
        'tipe_kendaraan' => TipeKendaraanEnum::MobilBensin,
        'plat_nomor' => 'B 4321 CAR',
        'merek' => 'Toyota Avanza 1.5',
    ]);

    $penyewaan = Penyewaan::factory()->create([
        'nomor_transaksi' => 'TRX-20261121-002',
        'penyewa_id' => $penyewa->id,
        'garasi_id' => $garasi->id,
        'kendaraan_id' => $kendaraan->id,
        'tanggal_mulai' => '2026-11-21',
        'tanggal_selesai' => '2026-11-25',
        'tipe_sewa' => TipeSewaEnum::Harian,
        'durasi' => 5,
        'tarif_pokok' => 250000.00,
        'biaya_listrik' => 0.00,
        'total_bayar' => 250000.00,
        'status_sewa' => StatusSewaEnum::Aktif,
    ]);

    // 1. Admin views the extension management page
    $this->actingAs($admin)
        ->get(route('admin.extensions.index'))
        ->assertOk()
        ->assertSee('C001')
        ->assertSee('TRX-20261121-002')
        ->assertSee('Budi Santoso');

    // 2. Admin extends rental from 2026-11-25 to 2026-11-30 (+5 days * Rp 50.000 = Rp 250.000)
    $proofFile = UploadedFile::fake()->image('bukti_extend_c001.jpg', 800, 600);

    $response = $this->actingAs($admin)
        ->post(route('admin.extensions.store'), [
            'penyewaan_id' => $penyewaan->id,
            'tanggal_selesai_baru' => '2026-11-30',
            'bukti_bayar_perpanjangan' => $proofFile,
        ]);

    $response->assertRedirect(route('admin.extensions.index'));
    $response->assertSessionHas('status');

    // 3. Verify PerpanjanganSewa record has biaya_perpanjangan = 250000.00
    $perpanjangan = PerpanjanganSewa::query()->where('penyewaan_id', $penyewaan->id)->first();
    expect($perpanjangan)->not->toBeNull()
        ->and($perpanjangan->tanggal_selesai_lama->toDateString())->toBe('2026-11-25')
        ->and($perpanjangan->tanggal_selesai_baru->toDateString())->toBe('2026-11-30')
        ->and($perpanjangan->selisih_hari)->toBe(5)
        ->and($perpanjangan->tarif_harian_snapshot)->toBe(50000.0)
        ->and($perpanjangan->biaya_perpanjangan)->toBe(250000.0);

    // 4. Verify parent Penyewaan end date was updated to 2026-11-30
    $penyewaan->refresh();
    expect($penyewaan->tanggal_selesai->toDateString())->toBe('2026-11-30');

    // 5. Verify Pembayaran record for perpanjangan and uploaded file on disk
    $pembayaran = Pembayaran::query()
        ->where('penyewaan_id', $penyewaan->id)
        ->where('perpanjangan_id', $perpanjangan->id)
        ->where('jenis_pembayaran', 'perpanjangan')
        ->first();

    expect($pembayaran)->not->toBeNull()
        ->and($pembayaran->nominal)->toBe(250000.0)
        ->and($pembayaran->status_verifikasi)->toBe('valid');

    Storage::disk('public')->assertExists($pembayaran->bukti_transfer);

    // 6. Verify updated rental duration and paid extension badge are immediately visible on Tenant's rental page
    $this->actingAs($tenantUser)
        ->get(route('tenant.rentals.index'))
        ->assertOk()
        ->assertSee('30 Nov 2026')
        ->assertSee('Diperpanjang +5 Hari oleh Admin')
        ->assertSee('Rp 250.000 (Lunas)');

    // 7. Verify extension form defaults to the most recently extended active rental
    $otherGarage = Garasi::factory()->create([
        'id' => 'S001',
        'tipe_garasi' => TipeGarasiEnum::Sepeda,
        'harga_sewa_harian' => 20000.00,
        'status' => StatusGarasiEnum::Used,
    ]);
    $otherRental = Penyewaan::factory()->create([
        'nomor_transaksi' => 'TRX-20261121-001',
        'penyewa_id' => $penyewa->id,
        'garasi_id' => $otherGarage->id,
        'kendaraan_id' => $kendaraan->id,
        'tanggal_mulai' => '2026-11-21',
        'tanggal_selesai' => '2026-11-22', // Ends earlier than C001 so it appears first in activeRentals list
        'status_sewa' => StatusSewaEnum::Aktif,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.extensions.index'))
        ->assertOk()
        ->assertViewHas('initialRentalId', $penyewaan->id);
});

test('validation rejects new end date that is not greater than current end date', function (): void {
    $admin = User::factory()->admin()->create();
    $penyewa = Penyewa::factory()->approved($admin)->create();
    $garasi = Garasi::factory()->create([
        'id' => 'S001',
        'tipe_garasi' => TipeGarasiEnum::Sepeda,
        'harga_sewa_harian' => 20000.00,
        'status' => StatusGarasiEnum::Used,
    ]);
    $kendaraan = Kendaraan::factory()->create(['penyewa_id' => $penyewa->id]);

    $penyewaan = Penyewaan::factory()->create([
        'penyewa_id' => $penyewa->id,
        'garasi_id' => $garasi->id,
        'kendaraan_id' => $kendaraan->id,
        'tanggal_mulai' => '2026-11-21',
        'tanggal_selesai' => '2026-11-25',
        'status_sewa' => StatusSewaEnum::Aktif,
    ]);

    $response = $this->actingAs($admin)
        ->from(route('admin.extensions.index'))
        ->post(route('admin.extensions.store'), [
            'penyewaan_id' => $penyewaan->id,
            'tanggal_selesai_baru' => '2026-11-25', // Equal to current end date (invalid)
            'bukti_bayar_perpanjangan' => UploadedFile::fake()->image('proof.png'),
        ]);

    $response->assertRedirect(route('admin.extensions.index'));
    $response->assertSessionHasErrors([
        'tanggal_selesai_baru' => 'Tanggal selesai baru harus lebih besar dari tanggal selesai saat ini.',
    ]);

    expect(PerpanjanganSewa::query()->count())->toBe(0);
});

test('validation rejects extension when payment proof is missing or not an image', function (): void {
    $admin = User::factory()->admin()->create();
    $penyewa = Penyewa::factory()->approved($admin)->create();
    $garasi = Garasi::factory()->create([
        'id' => 'E001',
        'tipe_garasi' => TipeGarasiEnum::MobilListrik,
        'harga_sewa_harian' => 100000.00,
        'tarif_kwh' => 2000.00,
        'status' => StatusGarasiEnum::Used,
    ]);
    $kendaraan = Kendaraan::factory()->mobilListrik(58.0)->create(['penyewa_id' => $penyewa->id]);

    $penyewaan = Penyewaan::factory()->create([
        'penyewa_id' => $penyewa->id,
        'garasi_id' => $garasi->id,
        'kendaraan_id' => $kendaraan->id,
        'tanggal_mulai' => '2026-11-21',
        'tanggal_selesai' => '2026-11-25',
        'status_sewa' => StatusSewaEnum::Aktif,
    ]);

    $response = $this->actingAs($admin)
        ->from(route('admin.extensions.index'))
        ->post(route('admin.extensions.store'), [
            'penyewaan_id' => $penyewaan->id,
            'tanggal_selesai_baru' => '2026-11-28',
            'bukti_bayar_perpanjangan' => UploadedFile::fake()->create('dokumen.pdf', 500, 'application/pdf'),
        ]);

    $response->assertRedirect(route('admin.extensions.index'));
    $response->assertSessionHasErrors(['bukti_bayar_perpanjangan']);
});

test('validation rejects extension for non-active rental transaction', function (): void {
    $admin = User::factory()->admin()->create();
    $penyewa = Penyewa::factory()->approved($admin)->create();
    $garasi = Garasi::factory()->create(['id' => 'C002']);
    $kendaraan = Kendaraan::factory()->create(['penyewa_id' => $penyewa->id]);

    $completedRental = Penyewaan::factory()->create([
        'penyewa_id' => $penyewa->id,
        'garasi_id' => $garasi->id,
        'kendaraan_id' => $kendaraan->id,
        'tanggal_mulai' => '2026-11-01',
        'tanggal_selesai' => '2026-11-05',
        'status_sewa' => StatusSewaEnum::Selesai,
    ]);

    $response = $this->actingAs($admin)
        ->from(route('admin.extensions.index'))
        ->post(route('admin.extensions.store'), [
            'penyewaan_id' => $completedRental->id,
            'tanggal_selesai_baru' => '2026-11-10',
            'bukti_bayar_perpanjangan' => UploadedFile::fake()->image('proof.jpg'),
        ]);

    $response->assertRedirect(route('admin.extensions.index'));
    $response->assertSessionHasErrors([
        'penyewaan_id' => 'Transaksi sewa tidak ditemukan atau sudah tidak berstatus aktif.',
    ]);
});

test('AC-16: tenant is strictly forbidden from accessing or submitting rental extensions', function (): void {
    $admin = User::factory()->admin()->create();
    $tenantUser = User::factory()->create();
    $penyewa = Penyewa::factory()->approved($admin)->for($tenantUser)->create();

    $garasi = Garasi::factory()->create(['id' => 'C001', 'status' => StatusGarasiEnum::Used]);
    $kendaraan = Kendaraan::factory()->create(['penyewa_id' => $penyewa->id]);
    $penyewaan = Penyewaan::factory()->create([
        'penyewa_id' => $penyewa->id,
        'garasi_id' => $garasi->id,
        'kendaraan_id' => $kendaraan->id,
        'tanggal_mulai' => '2026-11-21',
        'tanggal_selesai' => '2026-11-25',
        'status_sewa' => StatusSewaEnum::Aktif,
    ]);

    // Tenant attempts GET /admin/extensions -> 403 Forbidden
    $this->actingAs($tenantUser)
        ->get(route('admin.extensions.index'))
        ->assertForbidden();

    // Tenant attempts POST /admin/extensions -> 403 Forbidden
    $this->actingAs($tenantUser)
        ->post(route('admin.extensions.store'), [
            'penyewaan_id' => $penyewaan->id,
            'tanggal_selesai_baru' => '2026-11-30',
            'bukti_bayar_perpanjangan' => UploadedFile::fake()->image('tenant_hack.jpg'),
        ])
        ->assertForbidden();
});
