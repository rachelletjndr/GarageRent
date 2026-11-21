<?php

use App\Enums\StatusGarasiEnum;
use App\Enums\StatusSewaEnum;
use App\Enums\TipeKendaraanEnum;
use App\Enums\TipeSewaEnum;
use App\Models\Garasi;
use App\Models\Pembayaran;
use App\Models\Penyewaan;
use App\Models\User;
use App\Services\GarageAvailabilityService;
use App\Services\PricingCalculatorService;
use App\Services\RentalCalendarValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

test('start date defaults to today allows advance booking and rejects past dates', function () {
    Carbon::setTestNow('2026-11-21 10:00:00');
    Storage::fake('public');

    $tenant = User::factory()->withApprovedPenyewa()->create();
    Garasi::factory()->sepeda('S001', 20000)->available()->create();

    // AC-01: Default start date is today (2026-11-21) on form load
    $formResponse = $this->actingAs($tenant)->get(route('tenant.rentals.create'));
    $formResponse->assertOk()
        ->assertSee('2026-11-21');

    // AC-03: Past date (yesterday 2026-11-20) is rejected
    $pastDateResponse = $this->actingAs($tenant)->post(route('tenant.rentals.store'), [
        'tanggal_mulai' => '2026-11-20',
        'tipe_sewa' => 'harian',
        'durasi_hari' => 3,
        'tipe_kendaraan' => 'sepeda_motor',
        'plat_nomor' => 'B 1111 MTR',
        'merek' => 'Honda Beat',
        'garasi_id' => 'S001',
        'bukti_bayar' => UploadedFile::fake()->image('bukti.jpg'),
    ]);

    $pastDateResponse->assertSessionHasErrors(['tanggal_mulai']);

    // AC-02: Future date (advance booking 2026-12-01) is accepted
    $futureDateResponse = $this->actingAs($tenant)->post(route('tenant.rentals.store'), [
        'tanggal_mulai' => '2026-12-01',
        'tipe_sewa' => 'harian',
        'durasi_hari' => 5,
        'tipe_kendaraan' => 'sepeda_motor',
        'plat_nomor' => 'B 1111 MTR',
        'merek' => 'Honda Beat',
        'garasi_id' => 'S001',
        'bukti_bayar' => UploadedFile::fake()->image('bukti.jpg'),
    ]);

    $futureDateResponse->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.rentals.index'));
});

test('calendar month and daily duration limit enforces bva and leap year rules', function () {
    $validator = app(RentalCalendarValidator::class);

    // AC-04: Februari 2026 Non-Kabisat (28 hari) -> max harian = 27 hari
    expect($validator->getDaysInCalendarMonth('2026-02-01'))->toBe(28)
        ->and($validator->getMaxDurasiHarian('2026-02-01'))->toBe(27)
        ->and($validator->validateDurasi('2026-02-01', TipeSewaEnum::Harian, 1))->toBeTrue()
        ->and($validator->validateDurasi('2026-02-01', TipeSewaEnum::Harian, 27))->toBeTrue()
        ->and($validator->validateDurasi('2026-02-01', TipeSewaEnum::Harian, 28))->toBeFalse();

    // AC-05: Maret 2026 (31 hari) -> max harian = 30 hari
    expect($validator->getDaysInCalendarMonth('2026-03-01'))->toBe(31)
        ->and($validator->getMaxDurasiHarian('2026-03-01'))->toBe(30)
        ->and($validator->validateDurasi('2026-03-01', TipeSewaEnum::Harian, 30))->toBeTrue()
        ->and($validator->validateDurasi('2026-03-01', TipeSewaEnum::Harian, 31))->toBeFalse();

    // AC-06: Februari 2028 Tahun Kabisat (29 hari) -> max harian = 28 hari
    expect($validator->getDaysInCalendarMonth('2028-02-01'))->toBe(29)
        ->and($validator->getMaxDurasiHarian('2028-02-01'))->toBe(28)
        ->and($validator->validateDurasi('2028-02-01', TipeSewaEnum::Harian, 28))->toBeTrue()
        ->and($validator->validateDurasi('2028-02-01', TipeSewaEnum::Harian, 29))->toBeFalse();

    // AC-07: Bulanan 1 bulan kalender mulai 2026-02-15 -> berakhir 2026-03-14 (28 hari kalender aktual)
    $endDateFeb = $validator->hitungTanggalSelesai('2026-02-15', TipeSewaEnum::Bulanan, 1);
    expect($endDateFeb->toDateString())->toBe('2026-03-14')
        ->and($validator->hitungJumlahHariAktual('2026-02-15', $endDateFeb))->toBe(28)
        ->and($validator->validateDurasi('2026-02-15', TipeSewaEnum::Bulanan, 2))->toBeFalse();

    // HTTP Validation check for daily duration >= 1 calendar month
    Carbon::setTestNow('2026-01-15 09:00:00');
    Storage::fake('public');
    $tenant = User::factory()->withApprovedPenyewa()->create();
    Garasi::factory()->sepeda('S001', 20000)->available()->create();

    $invalidFebDaily = $this->actingAs($tenant)->post(route('tenant.rentals.store'), [
        'tanggal_mulai' => '2026-02-01',
        'tipe_sewa' => 'harian',
        'durasi_hari' => 28,
        'tipe_kendaraan' => 'sepeda',
        'plat_nomor' => 'SPD-01',
        'merek' => 'Polygon',
        'garasi_id' => 'S001',
        'bukti_bayar' => UploadedFile::fake()->image('proof.jpg'),
    ]);

    $invalidFebDaily->assertSessionHasErrors(['durasi_hari']);
});

test('rental price calculation computes pure tariff without surcharge for daily monthly and ev charging', function () {
    $calculator = app(PricingCalculatorService::class);

    // AC-13 (Positive - Sewa Harian):
    // Garasi S001 tarif harian Rp 20.000, durasi 5 hari -> Total Bayar = Rp 100.000
    $s001 = Garasi::factory()->sepeda('S001', 20000)->make();
    $rincianHarian = $calculator->hitungRincianBiaya(
        $s001,
        TipeKendaraanEnum::Sepeda,
        TipeSewaEnum::Harian,
        5,
    );

    expect($rincianHarian['tarif_pokok'])->toBe(100000.0)
        ->and($rincianHarian['biaya_listrik'])->toBe(0.0)
        ->and($rincianHarian['total_bayar'])->toBe(100000.0);

    // AC-14 (Positive - Sewa 1 Bulan Kalender + EV Charging):
    // Garasi E001 tarif harian Rp 100.000 (bulanan otomatis = 25 x 100.000 = Rp 2.500.000),
    // tarif listrik Rp 2.000/kWh, kapasitas baterai 50 kWh -> Listrik = Rp 100.000 -> Total Bayar = Rp 2.600.000
    $e001 = Garasi::factory()->mobilListrik('E001', 100000, 2000)->make();
    $rincianBulananEv = $calculator->hitungRincianBiaya(
        $e001,
        TipeKendaraanEnum::MobilListrik,
        TipeSewaEnum::Bulanan,
        1,
        50.0,
    );

    expect($rincianBulananEv['tarif_pokok'])->toBe(2500000.0)
        ->and($rincianBulananEv['biaya_listrik'])->toBe(100000.0)
        ->and($rincianBulananEv['total_bayar'])->toBe(2600000.0);

    // AC-11 (Mobil Listrik di Garasi Mobil Biasa 'C' -> Biaya Listrik = Rp 0):
    $c001 = Garasi::factory()->mobil('C001', 50000)->make();
    $rincianEvInCarGarage = $calculator->hitungRincianBiaya(
        $c001,
        TipeKendaraanEnum::MobilListrik,
        TipeSewaEnum::Harian,
        4,
        60.0,
    );

    expect($rincianEvInCarGarage['tarif_pokok'])->toBe(200000.0)
        ->and($rincianEvInCarGarage['biaya_listrik'])->toBe(0.0)
        ->and($rincianEvInCarGarage['total_bayar'])->toBe(200000.0);
});

test('get available garages for date range filters by vehicle compatibility and schedule overlap', function () {
    Carbon::setTestNow('2026-11-21 09:00:00');
    Storage::fake('public');

    $tenant = User::factory()->withApprovedPenyewa()->create();
    $s001 = Garasi::factory()->sepeda('S001', 20000)->available()->create();
    $c001 = Garasi::factory()->mobil('C001', 50000)->available()->create();
    $e001 = Garasi::factory()->mobilListrik('E001', 100000, 2000)->available()->create();

    $availabilityService = app(GarageAvailabilityService::class);

    // Mobil Listrik is compatible with both C001 and E001, not S001
    $evGarages = $availabilityService->getAvailableGarages(
        TipeKendaraanEnum::MobilListrik,
        '2026-11-21',
        '2026-11-25',
    );
    expect($evGarages->pluck('id')->all())->toEqual(['C001', 'E001']);

    // Book E001 from 2026-11-21 to 2026-11-25
    $this->actingAs($tenant)->post(route('tenant.rentals.store'), [
        'tanggal_mulai' => '2026-11-21',
        'tipe_sewa' => 'harian',
        'durasi_hari' => 5,
        'tipe_kendaraan' => 'mobil_listrik',
        'plat_nomor' => 'B 7777 EV',
        'merek' => 'Hyundai Ioniq 5',
        'kapasitas_kwh' => 50,
        'garasi_id' => 'E001',
        'bukti_bayar' => UploadedFile::fake()->image('receipt.png'),
    ])->assertSessionHasNoErrors();

    // During overlapping dates (2026-11-23 to 2026-11-27), E001 is NOT available
    $overlappingEvGarages = $availabilityService->getAvailableGarages(
        TipeKendaraanEnum::MobilListrik,
        '2026-11-23',
        '2026-11-27',
    );
    expect($overlappingEvGarages->pluck('id')->all())->toEqual(['C001']);

    // After the booking ends (2026-12-01 to 2026-12-05), E001 IS available for advance booking
    $futureEvGarages = $availabilityService->getAvailableGarages(
        TipeKendaraanEnum::MobilListrik,
        '2026-12-01',
        '2026-12-05',
    );
    expect($futureEvGarages->pluck('id')->all())->toEqual(['C001', 'E001']);
});

test('create new rental transaction stores vehicle payment proof transitions garage to used and supports multi-rental', function () {
    Carbon::setTestNow('2026-11-21 10:00:00');
    Storage::fake('public');

    $tenantUser = User::factory()->withApprovedPenyewa()->create();
    $s001 = Garasi::factory()->sepeda('S001', 20000)->available()->create();
    $e001 = Garasi::factory()->mobilListrik('E001', 100000, 2000)->available()->create();

    // AC-12 (Negative): Mobil Bensin attempting to rent Sepeda Garage S001 is rejected
    $incompatibleResponse = $this->actingAs($tenantUser)->post(route('tenant.rentals.store'), [
        'tanggal_mulai' => '2026-11-21',
        'tipe_sewa' => 'harian',
        'durasi_hari' => 3,
        'tipe_kendaraan' => 'mobil_bensin',
        'plat_nomor' => 'B 1234 CAR',
        'merek' => 'Honda Civic',
        'garasi_id' => 'S001',
        'bukti_bayar' => UploadedFile::fake()->image('bukti.jpg'),
    ]);
    $incompatibleResponse->assertSessionHasErrors(['garasi_id']);
    expect($s001->fresh()->status)->toBe(StatusGarasiEnum::Available);

    // Rental 1: Tenant rents S001 for 5 days (Rp 100.000)
    $receipt1 = UploadedFile::fake()->image('bukti_sepeda.jpg', 600, 600);
    $rental1Response = $this->actingAs($tenantUser)->post(route('tenant.rentals.store'), [
        'tanggal_mulai' => '2026-11-21',
        'tipe_sewa' => 'harian',
        'durasi_hari' => 5,
        'tipe_kendaraan' => 'sepeda_motor',
        'plat_nomor' => 'B 2222 MTR',
        'merek' => 'Yamaha NMAX',
        'garasi_id' => 'S001',
        'bukti_bayar' => $receipt1,
    ]);

    $rental1Response->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.rentals.index'));

    // AC-17: Garage S001 status transitions from 'available' to 'used'
    expect($s001->fresh()->status)->toBe(StatusGarasiEnum::Used);

    // BR-06 (Multi-Rental): Same tenant simultaneously rents E001 for 1 calendar month + 50 kWh EV charging (Rp 2.600.000)
    $receipt2 = UploadedFile::fake()->image('bukti_ev.png', 600, 600);
    $rental2Response = $this->actingAs($tenantUser)->post(route('tenant.rentals.store'), [
        'tanggal_mulai' => '2026-11-21',
        'tipe_sewa' => 'bulanan',
        'durasi_bulan' => 1,
        'tipe_kendaraan' => 'mobil_listrik',
        'plat_nomor' => 'B 8888 EV',
        'merek' => 'BYD Seal',
        'kapasitas_kwh' => 50,
        'garasi_id' => 'E001',
        'bukti_bayar' => $receipt2,
    ]);

    $rental2Response->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.rentals.index'));

    expect($e001->fresh()->status)->toBe(StatusGarasiEnum::Used);

    $activeRentals = Penyewaan::query()
        ->where('penyewa_id', $tenantUser->penyewa->id)
        ->where('status_sewa', StatusSewaEnum::Aktif->value)
        ->orderBy('id')
        ->get();

    expect($activeRentals)->toHaveCount(2)
        ->and($activeRentals[0]->garasi_id)->toBe('S001')
        ->and($activeRentals[0]->tanggal_selesai->toDateString())->toBe('2026-11-25')
        ->and($activeRentals[0]->total_bayar)->toBe(100000.0)
        ->and($activeRentals[1]->garasi_id)->toBe('E001')
        ->and($activeRentals[1]->tanggal_selesai->toDateString())->toBe('2026-12-20')
        ->and($activeRentals[1]->tarif_pokok)->toBe(2500000.0)
        ->and($activeRentals[1]->biaya_listrik)->toBe(100000.0)
        ->and($activeRentals[1]->total_bayar)->toBe(2600000.0);

    // Verify payment proof files exist in public storage disk
    $payments = Pembayaran::query()->whereIn('penyewaan_id', $activeRentals->pluck('id'))->get();
    expect($payments)->toHaveCount(2);
    foreach ($payments as $payment) {
        Storage::disk('public')->assertExists($payment->bukti_transfer);
    }
});

test('payment proof upload rejects non-image files and files exceeding 2mb', function () {
    Carbon::setTestNow('2026-11-21 10:00:00');
    Storage::fake('public');

    $tenantUser = User::factory()->withApprovedPenyewa()->create();
    Garasi::factory()->sepeda('S001', 20000)->available()->create();

    // 1. Non-image PDF file rejected
    $pdfResponse = $this->actingAs($tenantUser)->post(route('tenant.rentals.store'), [
        'tanggal_mulai' => '2026-11-21',
        'tipe_sewa' => 'harian',
        'durasi_hari' => 2,
        'tipe_kendaraan' => 'sepeda',
        'plat_nomor' => 'SPD-02',
        'merek' => 'United',
        'garasi_id' => 'S001',
        'bukti_bayar' => UploadedFile::fake()->create('document.pdf', 500, 'application/pdf'),
    ]);
    $pdfResponse->assertSessionHasErrors(['bukti_bayar']);

    // 2. Oversized image (> 2048 KB) rejected
    $oversizedResponse = $this->actingAs($tenantUser)->post(route('tenant.rentals.store'), [
        'tanggal_mulai' => '2026-11-21',
        'tipe_sewa' => 'harian',
        'durasi_hari' => 2,
        'tipe_kendaraan' => 'sepeda',
        'plat_nomor' => 'SPD-02',
        'merek' => 'United',
        'garasi_id' => 'S001',
        'bukti_bayar' => UploadedFile::fake()->image('huge.jpg')->size(3000),
    ]);
    $oversizedResponse->assertSessionHasErrors(['bukti_bayar']);
});

test('bicycle (sepeda) rental does not require a license plate (plat_nomor kecuali sepeda)', function () {
    Carbon::setTestNow('2026-11-21 10:00:00');
    Storage::fake('public');

    $tenantUser = User::factory()->withApprovedPenyewa()->create();
    Garasi::factory()->sepeda('S001', 20000)->available()->create();

    $response = $this->actingAs($tenantUser)->post(route('tenant.rentals.store'), [
        'tanggal_mulai' => '2026-11-21',
        'tipe_sewa' => 'harian',
        'durasi_hari' => 3,
        'tipe_kendaraan' => 'sepeda',
        'plat_nomor' => '',
        'merek' => 'Polygon Strattos S5',
        'garasi_id' => 'S001',
        'bukti_bayar' => UploadedFile::fake()->image('bukti_sepeda.jpg'),
    ]);

    $response->assertRedirect(route('tenant.rentals.index'));
    $response->assertSessionHasNoErrors();

    $rental = Penyewaan::query()->where('garasi_id', 'S001')->first();
    expect($rental)->not->toBeNull()
        ->and($rental->kendaraan->tipe_kendaraan)->toBe(TipeKendaraanEnum::Sepeda)
        ->and($rental->kendaraan->plat_nomor)->toBeNull();
});
