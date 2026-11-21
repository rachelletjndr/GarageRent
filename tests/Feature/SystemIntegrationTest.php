<?php

use App\Enums\StatusGarasiEnum;
use App\Enums\TipeGarasiEnum;
use App\Enums\TipeKendaraanEnum;
use App\Enums\TipeSewaEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Garasi;
use App\Models\Pembayaran;
use App\Models\Penyewa;
use App\Models\Penyewaan;
use App\Models\PerpanjanganSewa;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
});

test('full end-to-end integration across registration, ktp verification, bulk garages, multi-rental, admin extension, dashboard, payment proof, and reports', function (): void {
    Carbon::setTestNow(Carbon::create(2026, 11, 21, 9, 0, 0));

    $admin = User::factory()->admin()->create([
        'name' => 'Susi Susanti',
        'email' => 'admin.e2e@garasi.test',
    ]);

    // 1. Guest accessing root '/' is redirected directly to '/login'
    $this->get(route('home'))->assertRedirect(route('login'));

    // 2. Candidate Tenant registers with 16-digit NIK KTP
    $registerResponse = $this->post(route('register.store'), [
        'nik' => '3171011506950077',
        'name' => 'Andi Pratama',
        'no_telp' => '081277778888',
        'email' => 'andi@garasi.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $penyewa = Penyewa::query()->where('nik', '3171011506950077')->first();
    expect($penyewa)->not->toBeNull()
        ->and($penyewa->status_verifikasi)->toBe(VerificationStatusEnum::Pending);

    // If an unapproved tenant session visits /dashboard, they are redirected to /verification-notice
    $this->actingAs($penyewa->user)
        ->get(route('dashboard'))
        ->assertRedirect(route('tenant.verification.notice'));

    // 3. Admin visits unified Dashboard & sees pending verification queue
    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('1 Pending')
        ->assertSee('Andi Pratama')
        ->assertSee('3171011506950077');

    // 4. Admin approves Tenant KTP
    $this->actingAs($admin)
        ->patch(route('admin.verifications.approve', $penyewa))
        ->assertRedirect(route('admin.verifications.index'));

    $penyewa->refresh();
    expect($penyewa->isApproved())->toBeTrue();

    // 5. Admin bulk-creates garages for Sepeda (S) and Mobil Listrik (E)
    $this->actingAs($admin)
        ->post(route('admin.garages.store'), [
            'tipe_garasi' => TipeGarasiEnum::Sepeda->value,
            'jumlah' => 2,
            'harga_sewa_harian' => 20000,
        ])
        ->assertRedirect(route('admin.garages.index', ['tipe' => TipeGarasiEnum::Sepeda->value]));

    $this->actingAs($admin)
        ->post(route('admin.garages.store'), [
            'tipe_garasi' => TipeGarasiEnum::MobilListrik->value,
            'jumlah' => 2,
            'harga_sewa_harian' => 100000,
            'harga_kwh' => 2000,
        ])
        ->assertRedirect(route('admin.garages.index', ['tipe' => TipeGarasiEnum::MobilListrik->value]));

    expect(Garasi::find('S001')?->getHargaSewaBulanan())->toBe(500000.0)
        ->and(Garasi::find('E001')?->getHargaSewaBulanan())->toBe(2500000.0);

    // 6. Approved Tenant logs in, views unified Dashboard, and creates 2 simultaneous rentals (BR-06)
    $tenantUser = $penyewa->user;

    $this->actingAs($tenantUser)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Andi Pratama')
        ->assertSee('3171011506950077')
        ->assertSee('0 Unit');

    // Rental 1: Daily rental of S001 for 5 days (5 * 20.000 = Rp 100.000)
    $this->actingAs($tenantUser)
        ->post(route('tenant.rentals.store'), [
            'tanggal_mulai' => '2026-11-21',
            'tipe_sewa' => TipeSewaEnum::Harian->value,
            'durasi_hari' => 5,
            'tipe_kendaraan' => TipeKendaraanEnum::SepedaMotor->value,
            'plat_nomor' => 'b 7001 mtr',
            'merek' => 'Yamaha NMAX',
            'garasi_id' => 'S001',
            'bukti_bayar' => UploadedFile::fake()->image('bukti_s001.jpg'),
        ])
        ->assertRedirect(route('tenant.rentals.index'));

    // Rental 2: Monthly rental of E001 for 1 calendar month with 50 kWh EV (2.500.000 + 100.000 = Rp 2.600.000)
    $this->actingAs($tenantUser)
        ->post(route('tenant.rentals.store'), [
            'tanggal_mulai' => '2026-11-21',
            'tipe_sewa' => TipeSewaEnum::Bulanan->value,
            'durasi_bulan' => 1,
            'tipe_kendaraan' => TipeKendaraanEnum::MobilListrik->value,
            'plat_nomor' => 'b 7002 ev',
            'merek' => 'Hyundai Ioniq 5',
            'kapasitas_kwh' => 50,
            'garasi_id' => 'E001',
            'bukti_bayar' => UploadedFile::fake()->image('bukti_e001.png'),
        ])
        ->assertRedirect(route('tenant.rentals.index'));

    // Verify both garages transitioned to 'used' (BR-07)
    expect(Garasi::find('S001')?->status)->toBe(StatusGarasiEnum::Used)
        ->and(Garasi::find('E001')?->status)->toBe(StatusGarasiEnum::Used);

    // 7. Verify Tenant Dashboard now shows 2 active garages and Rp 2.700.000 total
    $this->actingAs($tenantUser)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('2 Unit')
        ->assertSee('S001')
        ->assertSee('E001')
        ->assertSee('Rp 2.700.000');

    // 8. Admin extends Rental 1 (S001) from 2026-11-25 to 2026-11-30 (+5 days * Rp 20.000 = Rp 100.000)
    $rentalS001 = Penyewaan::query()->where('garasi_id', 'S001')->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.extensions.store'), [
            'penyewaan_id' => $rentalS001->id,
            'tanggal_selesai_baru' => '2026-11-30',
            'bukti_bayar_perpanjangan' => UploadedFile::fake()->image('bukti_ext_s001.jpg'),
        ])
        ->assertRedirect(route('admin.extensions.index'));

    $ext = PerpanjanganSewa::query()->where('penyewaan_id', $rentalS001->id)->firstOrFail();
    expect($ext->biaya_perpanjangan)->toBe(100000.0);

    // 9. Verify Payment Proof endpoint authorization (Admin and owning Tenant can view; other Tenant gets 403)
    $payment = Pembayaran::query()->where('penyewaan_id', $rentalS001->id)->firstOrFail();

    $this->actingAs($admin)
        ->get(route('payments.proof', $payment))
        ->assertOk();

    $this->actingAs($tenantUser)
        ->get(route('payments.proof', $payment))
        ->assertOk();

    $otherTenant = User::factory()->withApprovedPenyewa(['nik' => '3171019909990099'])->create();
    $this->actingAs($otherTenant)
        ->get(route('payments.proof', $payment))
        ->assertForbidden();

    // 10. Verify Admin Dashboard and Multi-Dimensional Reports reflect all integrated data (Rp 2.700.000 + Rp 100.000 = Rp 2.800.000 & 1x Extend paid)
    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Rp 2.800.000')
        ->assertSee('1x Extend');

    $this->actingAs($admin)
        ->get(route('admin.reports.index', ['mode' => 'bulanan', 'tahun' => 2026]))
        ->assertOk()
        ->assertSee('Rp 2.800.000')
        ->assertSee('Rp 100.000');

    Carbon::setTestNow();
});

test('UI/UX revision checklist: passkey removed, hamburger menu present, clean wording on admin & user dashboards, garages, extensions, and rental notification', function (): void {
    $admin = User::factory()->admin()->create();
    $tenantUser = User::factory()->withApprovedPenyewa(['nik' => '3171018808880088'])->create();

    // 1. Login page does not show "Sign in with a passkey"
    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('Sign in with a passkey')
        ->assertDontSee('passkey');

    // 2. Admin Dashboard has hamburger button, does not contain "Modul 1/2/3/4"
    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-test="hamburger-menu-button"', escape: false)
        ->assertSee('data-test="hamburger-sidebar-drawer"', escape: false)
        ->assertDontSee('Modul 1')
        ->assertDontSee('Modul 2')
        ->assertDontSee('Modul 3')
        ->assertDontSee('Modul 4');

    // 3. Admin Garage Management page uses "Add Garasi" and natural Indonesian description
    $this->actingAs($admin)
        ->get(route('admin.garages.index'))
        ->assertOk()
        ->assertSee('Add Garasi')
        ->assertSee('Garasi baru akan mendapatkan nomor secara otomatis sesuai jenis garasi, dengan status awal tersedia.')
        ->assertDontSee('Formulir Pembuatan Garasi Secara Massal (Bulk Creation)')
        ->assertDontSee('Buat Unit Garasi Massal');

    // 4. Admin Extension page shows Total Biaya Perpanjangan and does not show Total Kurang Bayar or BR-12
    $this->actingAs($admin)
        ->get(route('admin.extensions.index'))
        ->assertOk()
        ->assertSee('Total Biaya Perpanjangan')
        ->assertDontSee('Total Kurang Bayar')
        ->assertDontSee('BR-12')
        ->assertDontSee('Kalkulator Kurang Bayar')
        ->assertDontSee('Wajib Negatif')
        ->assertDontSee('Hak Akses Eksklusif')
        ->assertDontSee('&Delta;', escape: false);

    // 5. User Dashboard does not show "Penyewa Garasi" or the 3 shortcut buttons, while main navigation links remain
    $this->actingAs($tenantUser)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Penyewa Garasi')
        ->assertDontSee('data-test="dash-link-catalog"', escape: false)
        ->assertDontSee('data-test="dash-link-tenant-rentals"', escape: false)
        ->assertDontSee('data-test="dash-link-new-rental"', escape: false)
        ->assertSee(route('tenant.garages.index'), escape: false)
        ->assertSee(route('tenant.rentals.create'), escape: false)
        ->assertSee(route('tenant.rentals.index'), escape: false);

    // 6. New rental notification is simplified to "Transaksi sewa garasi baru berhasil disimpan."
    Garasi::factory()->create([
        'id' => 'S001',
        'tipe_garasi' => TipeGarasiEnum::Sepeda,
        'harga_sewa_harian' => 20000,
        'status' => StatusGarasiEnum::Available,
    ]);

    $response = $this->actingAs($tenantUser)
        ->post(route('tenant.rentals.store'), [
            'tanggal_mulai' => now()->toDateString(),
            'tipe_sewa' => TipeSewaEnum::Harian->value,
            'durasi_hari' => 2,
            'tipe_kendaraan' => TipeKendaraanEnum::SepedaMotor->value,
            'plat_nomor' => 'B 9876 REV',
            'merek' => 'Honda Beat',
            'garasi_id' => 'S001',
            'bukti_bayar' => UploadedFile::fake()->image('bukti_rev.jpg'),
        ]);

    $response->assertRedirect(route('tenant.rentals.index'));
    $response->assertSessionHas('status', 'Transaksi sewa garasi baru berhasil disimpan.');
});
