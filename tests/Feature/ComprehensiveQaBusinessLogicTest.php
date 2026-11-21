<?php

use App\Enums\RoleEnum;
use App\Enums\StatusGarasiEnum;
use App\Enums\StatusSewaEnum;
use App\Enums\TipeGarasiEnum;
use App\Enums\TipeKendaraanEnum;
use App\Enums\TipeSewaEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Garasi;
use App\Models\Kendaraan;
use App\Models\Penyewa;
use App\Models\Penyewaan;
use App\Models\PerpanjanganSewa;
use App\Models\User;
use App\Services\AdminVerificationService;
use App\Services\GarageAvailabilityService;
use App\Services\GarageFactoryService;
use App\Services\PaymentProofService;
use App\Services\PricingCalculatorService;
use App\Services\RentalBookingService;
use App\Services\RentalCalendarValidator;
use App\Services\RentalExtensionService;
use App\Services\RentalReportService;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
});

/*
|--------------------------------------------------------------------------
| 1. Registrasi dan Validasi Penyewa (TC-01, TC-02, TC-03)
|--------------------------------------------------------------------------
*/
test('TC-01: valid tenant registration creates user and pending penyewa profile', function (): void {
    $response = $this->post(route('register.store'), [
        'nik' => '3171012005950001',
        'name' => 'Rizky Ramadhan',
        'no_telp' => '081234567899',
        'email' => 'rizky.qa@garasi.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $user = User::query()->where('email', 'rizky.qa@garasi.test')->first();
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe(RoleEnum::Penyewa)
        ->and($user->penyewa)->not->toBeNull()
        ->and($user->penyewa->nik)->toBe('3171012005950001')
        ->and($user->penyewa->status_verifikasi)->toBe(VerificationStatusEnum::Pending);
});

test('TC-02: tenant registration rejects empty required fields and invalid NIK/phone formats', function (): void {
    // Empty input check
    $emptyResponse = $this->post(route('register.store'), []);
    $emptyResponse->assertSessionHasErrors(['nik', 'name', 'no_telp', 'email', 'password']);

    // Invalid format check (NIK not 16 digits, non-numeric phone, short password)
    $invalidResponse = $this->post(route('register.store'), [
        'nik' => '317101ABC',
        'name' => 'Invalid User',
        'no_telp' => '0812',
        'email' => 'not-an-email',
        'password' => '123',
        'password_confirmation' => '456',
    ]);
    $invalidResponse->assertSessionHasErrors(['nik', 'no_telp', 'email', 'password']);
});

test('TC-03: tenant registration rejects duplicate NIK and duplicate email', function (): void {
    User::factory()->withApprovedPenyewa(['nik' => '3171012005950002'])->create([
        'email' => 'existing.qa@garasi.test',
    ]);

    $dupResponse = $this->post(route('register.store'), [
        'nik' => '3171012005950002',
        'name' => 'Duplikat User',
        'no_telp' => '081299998888',
        'email' => 'existing.qa@garasi.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $dupResponse->assertSessionHasErrors(['nik', 'email']);
});

/*
|--------------------------------------------------------------------------
| 2. Login dan Autentikasi Role / RBAC (TC-04, TC-05)
|--------------------------------------------------------------------------
*/
test('TC-04: pending and rejected tenants cannot login while approved tenant and admin can login', function (): void {
    $pendingUser = User::factory()->withPendingPenyewa(['nik' => '3171012005950003'])->create([
        'email' => 'pending.qa@garasi.test',
    ]);

    $this->post(route('login.store'), [
        'email' => $pendingUser->email,
        'password' => 'password',
    ])->assertSessionHasErrors(['email']);
    $this->assertGuest();

    $approvedUser = User::factory()->withApprovedPenyewa(['nik' => '3171012005950004'])->create([
        'email' => 'approved.qa@garasi.test',
    ]);

    $this->post(route('login.store'), [
        'email' => $approvedUser->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($approvedUser);
});

test('TC-05: role authorization blocks unauthorized users from admin and tenant routes with 403', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = User::factory()->withApprovedPenyewa(['nik' => '3171012005950005'])->create();

    // Tenant cannot access Admin routes
    $this->actingAs($tenant)->get(route('admin.verifications.index'))->assertForbidden();
    $this->actingAs($tenant)->get(route('admin.garages.index'))->assertForbidden();
    $this->actingAs($tenant)->get(route('admin.extensions.index'))->assertForbidden();
    $this->actingAs($tenant)->get(route('admin.reports.index'))->assertForbidden();

    // Admin cannot access Tenant-only booking routes
    $this->actingAs($admin)->get(route('tenant.rentals.create'))->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| 3. Verifikasi Pendaftaran & Validasi KTP (TC-06)
|--------------------------------------------------------------------------
*/
test('TC-06: admin verification service inspects 16-digit NIK KTP and approves or rejects registration', function (): void {
    $service = app(AdminVerificationService::class);
    $admin = User::factory()->admin()->create();
    $candidateUser = User::factory()->withPendingPenyewa(['nik' => '3171015508960006'])->create();
    $penyewa = $candidateUser->penyewa;

    // Inspect female NIK (day 55 - 40 = 15 August 1996)
    $inspection = $service->inspectKtpNik($penyewa->nik);
    expect($inspection['is_valid_format'])->toBeTrue()
        ->and($inspection['jenis_kelamin_estimasi'])->toBe('Perempuan')
        ->and($inspection['tanggal_lahir_kode'])->toBe('15-08-96');

    // Reject without reason fails validation
    $this->actingAs($admin)
        ->patch(route('admin.verifications.reject', $penyewa), ['catatan_penolakan' => ''])
        ->assertSessionHasErrors(['catatan_penolakan']);

    // Approve succeeds
    $service->approveTenant($penyewa, $admin);
    expect($penyewa->fresh()->isApproved())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| 4 & 5. Pembuatan Garasi Massal & Kode Otomatis (TC-07, TC-08)
|--------------------------------------------------------------------------
*/
test('TC-07: bulk garage creation generates sequential IDs S001/C001/E001 and computes 25x monthly rate', function (): void {
    $service = app(GarageFactoryService::class);

    $sepedaUnits = $service->bulkCreate(TipeGarasiEnum::Sepeda, 2, 20000.0);
    $mobilUnits = $service->bulkCreate(TipeGarasiEnum::Mobil, 2, 50000.0);
    $evUnits = $service->bulkCreate(TipeGarasiEnum::MobilListrik, 2, 100000.0, 2000.0);

    expect($sepedaUnits->pluck('id')->all())->toBe(['S001', 'S002'])
        ->and($mobilUnits->pluck('id')->all())->toBe(['C001', 'C002'])
        ->and($evUnits->pluck('id')->all())->toBe(['E001', 'E002'])
        ->and($sepedaUnits->first()->getHargaSewaBulanan())->toBe(500000.0)
        ->and($mobilUnits->first()->getHargaSewaBulanan())->toBe(1250000.0)
        ->and($evUnits->first()->getHargaSewaBulanan())->toBe(2500000.0);

    // Next batch continues from S003
    $nextSepeda = $service->bulkCreate(TipeGarasiEnum::Sepeda, 1, 20000.0);
    expect($nextSepeda->first()->id)->toBe('S003');
});

test('TC-08: bulk garage creation enforces max 999 quota per type and requires kWh tariff for EV garage', function (): void {
    $service = app(GarageFactoryService::class);

    Garasi::factory()->create([
        'id' => 'C998',
        'tipe_garasi' => TipeGarasiEnum::Mobil,
        'harga_sewa_harian' => 50000.0,
    ]);

    expect(fn () => $service->bulkCreate(TipeGarasiEnum::Mobil, 2, 50000.0))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $service->bulkCreate(TipeGarasiEnum::MobilListrik, 1, 100000.0, null))
        ->toThrow(InvalidArgumentException::class);
});

/*
|--------------------------------------------------------------------------
| 6. Validasi Kesesuaian Tipe Kendaraan dan Garasi (TC-09)
|--------------------------------------------------------------------------
*/
test('TC-09: vehicle to garage compatibility matrix strictly enforces BR-01 rules', function (): void {
    $garasiS = new Garasi(['id' => 'S001', 'tipe_garasi' => TipeGarasiEnum::Sepeda]);
    $garasiC = new Garasi(['id' => 'C001', 'tipe_garasi' => TipeGarasiEnum::Mobil]);
    $garasiE = new Garasi(['id' => 'E001', 'tipe_garasi' => TipeGarasiEnum::MobilListrik]);

    // Sepeda & Sepeda Motor -> only Garasi Sepeda (S)
    expect($garasiS->isCompatibleWith(TipeKendaraanEnum::Sepeda))->toBeTrue()
        ->and($garasiS->isCompatibleWith(TipeKendaraanEnum::SepedaMotor))->toBeTrue()
        ->and($garasiS->isCompatibleWith(TipeKendaraanEnum::MobilBensin))->toBeFalse()
        ->and($garasiS->isCompatibleWith(TipeKendaraanEnum::MobilListrik))->toBeFalse();

    // Mobil Bensin -> only Garasi Mobil (C); Mobil Listrik -> Garasi Mobil (C) OR Garasi Mobil Listrik (E)
    expect($garasiC->isCompatibleWith(TipeKendaraanEnum::MobilBensin))->toBeTrue()
        ->and($garasiC->isCompatibleWith(TipeKendaraanEnum::MobilListrik))->toBeTrue()
        ->and($garasiC->isCompatibleWith(TipeKendaraanEnum::SepedaMotor))->toBeFalse()
        ->and($garasiE->isCompatibleWith(TipeKendaraanEnum::MobilListrik))->toBeTrue()
        ->and($garasiE->isCompatibleWith(TipeKendaraanEnum::MobilBensin))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| 7 & 8. Perhitungan Sewa Harian & Sewa Bulanan (TC-10, TC-11)
|--------------------------------------------------------------------------
*/
test('TC-10: daily rental price calculation computes base tariff and EV electricity accurately', function (): void {
    $calculator = app(PricingCalculatorService::class);

    $garasiS = new Garasi([
        'id' => 'S001',
        'tipe_garasi' => TipeGarasiEnum::Sepeda,
        'harga_sewa_harian' => 20000.0,
        'tarif_kwh' => null,
    ]);

    // 5 days * Rp 20.000 = Rp 100.000
    $rincianSepeda = $calculator->hitungRincianBiaya($garasiS, TipeKendaraanEnum::SepedaMotor, TipeSewaEnum::Harian, 5);
    expect($rincianSepeda['tarif_pokok'])->toBe(100000.0)
        ->and($rincianSepeda['biaya_listrik'])->toBe(0.0)
        ->and($rincianSepeda['total_bayar'])->toBe(100000.0);
});

test('TC-11: monthly rental price calculation uses 25x daily rate plus full EV charging in garage E and zero electricity in garage C', function (): void {
    $calculator = app(PricingCalculatorService::class);

    $garasiE = new Garasi([
        'id' => 'E001',
        'tipe_garasi' => TipeGarasiEnum::MobilListrik,
        'harga_sewa_harian' => 100000.0,
        'tarif_kwh' => 2000.0,
    ]);

    $garasiC = new Garasi([
        'id' => 'C001',
        'tipe_garasi' => TipeGarasiEnum::Mobil,
        'harga_sewa_harian' => 50000.0,
        'tarif_kwh' => null,
    ]);

    // Monthly in Garage E: 25 * 100.000 = 2.500.000 + (50 kWh * 2.000 = 100.000) = Rp 2.600.000
    $rincianEvInE = $calculator->hitungRincianBiaya($garasiE, TipeKendaraanEnum::MobilListrik, TipeSewaEnum::Bulanan, 1, 50.0);
    expect($rincianEvInE['tarif_pokok'])->toBe(2500000.0)
        ->and($rincianEvInE['biaya_listrik'])->toBe(100000.0)
        ->and($rincianEvInE['total_bayar'])->toBe(2600000.0);

    // Monthly EV parked in conventional Garage C: 25 * 50.000 = 1.250.000 + Rp 0 electricity = Rp 1.250.000
    $rincianEvInC = $calculator->hitungRincianBiaya($garasiC, TipeKendaraanEnum::MobilListrik, TipeSewaEnum::Bulanan, 1, 50.0);
    expect($rincianEvInC['tarif_pokok'])->toBe(1250000.0)
        ->and($rincianEvInC['biaya_listrik'])->toBe(0.0)
        ->and($rincianEvInC['total_bayar'])->toBe(1250000.0);
});

/*
|--------------------------------------------------------------------------
| 9 & 10. Validasi Maksimal Hari (BVA) & Tanggal Mulai/Selesai (TC-12, TC-13)
|--------------------------------------------------------------------------
*/
test('TC-12: calendar validator enforces dynamic daily max duration (daysInMonth - 1) including March 30-day limit and leap years', function (): void {
    $validator = app(RentalCalendarValidator::class);

    // March 2026 (31 days) -> max daily duration is 30 days (31 days is rejected)
    expect($validator->getMaxDurasiHarian('2026-03-01'))->toBe(30)
        ->and($validator->validateDurasi('2026-03-01', TipeSewaEnum::Harian, 30))->toBeTrue()
        ->and($validator->validateDurasi('2026-03-01', TipeSewaEnum::Harian, 31))->toBeFalse();

    // April 2026 (30 days) -> max daily duration is 29 days (30 days is rejected)
    expect($validator->getMaxDurasiHarian('2026-04-01'))->toBe(29)
        ->and($validator->validateDurasi('2026-04-01', TipeSewaEnum::Harian, 29))->toBeTrue()
        ->and($validator->validateDurasi('2026-04-01', TipeSewaEnum::Harian, 30))->toBeFalse();

    // February 2026 non-leap (28 days) -> max daily is 27 days; February 2028 leap (29 days) -> max daily is 28 days
    expect($validator->getMaxDurasiHarian('2026-02-01'))->toBe(27)
        ->and($validator->getMaxDurasiHarian('2028-02-01'))->toBe(28);
});

test('TC-13: start date validation rejects past dates and end date is computed accurately for daily and 1 calendar month', function (): void {
    Carbon::setTestNow(Carbon::create(2026, 11, 21, 10, 0, 0));
    $validator = app(RentalCalendarValidator::class);

    expect($validator->validateTanggalMulai('2026-11-20'))->toBeFalse()
        ->and($validator->validateTanggalMulai('2026-11-21'))->toBeTrue()
        ->and($validator->validateTanggalMulai('2026-12-01'))->toBeTrue();

    // Daily 5 days starting 2026-11-21 -> ends 2026-11-25
    expect($validator->hitungTanggalSelesai('2026-11-21', TipeSewaEnum::Harian, 5)->toDateString())
        ->toBe('2026-11-25');

    // Monthly 1 calendar month starting 2026-02-15 -> ends 2026-03-14 (28 actual days)
    expect($validator->hitungTanggalSelesai('2026-02-15', TipeSewaEnum::Bulanan, 1)->toDateString())
        ->toBe('2026-03-14');

    Carbon::setTestNow();
});

/*
|--------------------------------------------------------------------------
| 11, 12, 13, 14. Ketersediaan, Multi-Sewa, Status Used, & Bukti Bayar (TC-14, TC-15)
|--------------------------------------------------------------------------
*/
test('TC-14: garage availability service blocks overlapping dates and allows non-overlapping advance booking', function (): void {
    $availability = app(GarageAvailabilityService::class);
    $penyewa = Penyewa::factory()->approved()->create();
    $garasi = Garasi::factory()->create([
        'id' => 'C001',
        'tipe_garasi' => TipeGarasiEnum::Mobil,
        'status' => StatusGarasiEnum::Used,
    ]);
    $kendaraan = Kendaraan::factory()->mobilBensin()->create(['penyewa_id' => $penyewa->id]);

    Penyewaan::factory()->create([
        'penyewa_id' => $penyewa->id,
        'garasi_id' => $garasi->id,
        'kendaraan_id' => $kendaraan->id,
        'tanggal_mulai' => '2026-11-21',
        'tanggal_selesai' => '2026-11-25',
        'status_sewa' => StatusSewaEnum::Aktif,
    ]);

    // Overlapping range [2026-11-23, 2026-11-27] -> unavailable
    expect($availability->isGarageAvailableForRange($garasi, '2026-11-23', '2026-11-27'))->toBeFalse();

    // Future non-overlapping range [2026-11-26, 2026-11-30] when status is available -> available
    $garasi->markAsAvailable();
    expect($availability->isGarageAvailableForRange($garasi, '2026-11-26', '2026-11-30'))->toBeTrue();
});

test('TC-15: approved tenant can rent multiple garages simultaneously, garage transitions to used, and payment proof is stored', function (): void {
    Carbon::setTestNow(Carbon::create(2026, 11, 21, 10, 0, 0));
    $bookingService = app(RentalBookingService::class);
    $penyewa = Penyewa::factory()->approved()->create();

    $s001 = Garasi::factory()->create([
        'id' => 'S001',
        'tipe_garasi' => TipeGarasiEnum::Sepeda,
        'harga_sewa_harian' => 20000.0,
        'status' => StatusGarasiEnum::Available,
    ]);
    $c001 = Garasi::factory()->create([
        'id' => 'C001',
        'tipe_garasi' => TipeGarasiEnum::Mobil,
        'harga_sewa_harian' => 50000.0,
        'status' => StatusGarasiEnum::Available,
    ]);

    $trx1 = $bookingService->createNewRental($penyewa, [
        'tanggal_mulai' => '2026-11-21',
        'tipe_sewa' => 'harian',
        'durasi_hari' => 3,
        'tipe_kendaraan' => 'sepeda_motor',
        'plat_nomor' => 'B 1010 QA',
        'merek' => 'Honda PCX',
        'garasi_id' => 'S001',
    ], UploadedFile::fake()->image('proof1.jpg'));

    $trx2 = $bookingService->createNewRental($penyewa, [
        'tanggal_mulai' => '2026-11-21',
        'tipe_sewa' => 'harian',
        'durasi_hari' => 4,
        'tipe_kendaraan' => 'mobil_bensin',
        'plat_nomor' => 'B 2020 QA',
        'merek' => 'Honda Civic',
        'garasi_id' => 'C001',
    ], UploadedFile::fake()->image('proof2.png'));

    expect($penyewa->getPenyewaanAktif()->count())->toBe(2)
        ->and($s001->fresh()->status)->toBe(StatusGarasiEnum::Used)
        ->and($c001->fresh()->status)->toBe(StatusGarasiEnum::Used);

    Storage::disk('public')->assertExists($trx1->getBuktiBayarSewaBaru()->bukti_transfer);
    Storage::disk('public')->assertExists($trx2->getBuktiBayarSewaBaru()->bukti_transfer);

    Carbon::setTestNow();
});

/*
|--------------------------------------------------------------------------
| 15 & 16. Perpanjangan Sewa & Perhitungan Biaya Perpanjangan (TC-16)
|--------------------------------------------------------------------------
*/
test('TC-16: admin rental extension updates end date and stores biaya perpanjangan', function (): void {
    $extensionService = app(RentalExtensionService::class);
    $admin = User::factory()->admin()->create();
    $penyewa = Penyewa::factory()->approved($admin)->create();
    $garasi = Garasi::factory()->create([
        'id' => 'C001',
        'tipe_garasi' => TipeGarasiEnum::Mobil,
        'harga_sewa_harian' => 50000.0,
        'status' => StatusGarasiEnum::Used,
    ]);
    $kendaraan = Kendaraan::factory()->mobilBensin()->create(['penyewa_id' => $penyewa->id]);

    $penyewaan = Penyewaan::factory()->create([
        'penyewa_id' => $penyewa->id,
        'garasi_id' => $garasi->id,
        'kendaraan_id' => $kendaraan->id,
        'tanggal_mulai' => '2026-11-21',
        'tanggal_selesai' => '2026-11-25',
        'status_sewa' => StatusSewaEnum::Aktif,
    ]);

    $ext = $extensionService->extendRental(
        $penyewaan,
        $admin,
        '2026-11-30',
        UploadedFile::fake()->image('ext_proof.jpg'),
    );

    expect($ext->selisih_hari)->toBe(5)
        ->and($ext->biaya_perpanjangan)->toBe(250000.0)
        ->and($penyewaan->fresh()->tanggal_selesai->toDateString())->toBe('2026-11-30');
});

/*
|--------------------------------------------------------------------------
| 17. Laporan Bulanan, Harian, Tipe Garasi, dan Penyewa (TC-17)
|--------------------------------------------------------------------------
*/
test('TC-17: report service accurately aggregates monthly, daily, garage-type, and tenant dimensions', function (): void {
    $reportService = app(RentalReportService::class);
    $admin = User::factory()->admin()->create();
    $penyewa = Penyewa::factory()->approved($admin)->create(['nama_lengkap' => 'Siti QA']);
    $garasi = Garasi::factory()->create([
        'id' => 'E001',
        'tipe_garasi' => TipeGarasiEnum::MobilListrik,
        'harga_sewa_harian' => 100000.0,
        'tarif_kwh' => 2000.0,
        'status' => StatusGarasiEnum::Used,
    ]);
    $kendaraan = Kendaraan::factory()->mobilListrik(50.0)->create(['penyewa_id' => $penyewa->id]);

    $trx = Penyewaan::factory()->create([
        'penyewa_id' => $penyewa->id,
        'garasi_id' => $garasi->id,
        'kendaraan_id' => $kendaraan->id,
        'tanggal_mulai' => '2026-11-21',
        'tanggal_selesai' => '2026-11-25',
        'tarif_pokok' => 400000.0,
        'biaya_listrik' => 100000.0,
        'total_bayar' => 500000.0,
        'status_sewa' => StatusSewaEnum::Aktif,
    ]);

    PerpanjanganSewa::factory()->create([
        'penyewaan_id' => $trx->id,
        'admin_id' => $admin->id,
        'tanggal_selesai_lama' => '2026-11-25',
        'tanggal_selesai_baru' => '2026-11-27',
        'selisih_hari' => 2,
        'tarif_harian_snapshot' => 100000.0,
        'biaya_perpanjangan' => 200000.0,
    ]);

    $monthly = $reportService->getMonthlyReport(2026);
    $daily = $reportService->getDailyReport(2026, 11);
    $byType = $reportService->getGarageTypeReport(2026, 11, TipeGarasiEnum::MobilListrik->value);
    $byTenant = $reportService->getTenantReport($penyewa->id, 2026, 11);

    expect($monthly['months'][11]['grand_total'])->toBe(700000.0)
        ->and($monthly['months'][11]['total_perpanjangan'])->toBe(200000.0)
        ->and($daily['days'][21]['grand_total'])->toBe(700000.0)
        ->and($byType['mobil_listrik']['grand_total'])->toBe(700000.0)
        ->and($byTenant[0]['grand_total'])->toBe(700000.0);
});

/*
|--------------------------------------------------------------------------
| 18. Kondisi Khusus: Kesalahan Database, Rollback & Edge Cases (TC-18, TC-19)
|--------------------------------------------------------------------------
*/
test('TC-18: database constraint violation and transaction rollback prevent orphan data or premature garage release', function (): void {
    $penyewa1 = Penyewa::factory()->approved()->create(['nik' => '3171012005950088']);

    // 1. Database UNIQUE constraint on penyewa.nik throws QueryException
    expect(fn () => Penyewa::factory()->create(['nik' => '3171012005950088']))
        ->toThrow(QueryException::class);

    // 2. Completing one rental on a garage that still has another active rental keeps garage status as 'used'
    $garasi = Garasi::factory()->create([
        'id' => 'S009',
        'tipe_garasi' => TipeGarasiEnum::Sepeda,
        'status' => StatusGarasiEnum::Used,
    ]);
    $kendaraan = Kendaraan::factory()->create(['penyewa_id' => $penyewa1->id]);

    $rental1 = Penyewaan::factory()->create([
        'penyewa_id' => $penyewa1->id,
        'garasi_id' => $garasi->id,
        'kendaraan_id' => $kendaraan->id,
        'tanggal_mulai' => '2026-11-21',
        'tanggal_selesai' => '2026-11-23',
        'status_sewa' => StatusSewaEnum::Aktif,
    ]);

    $rental2 = Penyewaan::factory()->create([
        'penyewa_id' => $penyewa1->id,
        'garasi_id' => $garasi->id,
        'kendaraan_id' => $kendaraan->id,
        'tanggal_mulai' => '2026-11-25',
        'tanggal_selesai' => '2026-11-28',
        'status_sewa' => StatusSewaEnum::Aktif,
    ]);

    $rental1->selesaikan();
    expect($garasi->fresh()->status)->toBe(StatusGarasiEnum::Used);

    $rental2->selesaikan();
    expect($garasi->fresh()->status)->toBe(StatusGarasiEnum::Available);
});

test('TC-19: payment proof service rejects oversized files and non-image MIME types', function (): void {
    $proofService = app(PaymentProofService::class);

    // Non-image PDF rejected
    $pdfFile = UploadedFile::fake()->create('script.pdf', 100, 'application/pdf');
    expect(fn () => $proofService->storePaymentProof($pdfFile))
        ->toThrow(InvalidArgumentException::class);

    // Oversized image (> 2048 KB) rejected
    $hugeImage = UploadedFile::fake()->image('huge.jpg')->size(3000);
    expect(fn () => $proofService->storePaymentProof($hugeImage))
        ->toThrow(InvalidArgumentException::class);
});
