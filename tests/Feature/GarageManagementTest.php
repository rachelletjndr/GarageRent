<?php

use App\Enums\StatusGarasiEnum;
use App\Enums\TipeGarasiEnum;
use App\Enums\TipeKendaraanEnum;
use App\Models\Garasi;
use App\Models\User;

test('bulk garage creation generates sequential ids automatic 25x monthly rate and enforces max 999 quota', function () {
    $admin = User::factory()->admin()->create();

    // AC-08: Create 3 Sepeda garages with daily tariff Rp 20.000
    $response = $this->actingAs($admin)->post(route('admin.garages.store'), [
        'tipe_garasi' => 'sepeda',
        'jumlah' => 3,
        'harga_sewa_harian' => 20000,
    ]);

    $response->assertRedirect(route('admin.garages.index', ['tipe' => 'sepeda']))
        ->assertSessionHas('status');

    $s001 = Garasi::find('S001');
    $s002 = Garasi::find('S002');
    $s003 = Garasi::find('S003');

    expect($s001)->not->toBeNull()
        ->and($s002)->not->toBeNull()
        ->and($s003)->not->toBeNull()
        ->and($s001->tipe_garasi)->toBe(TipeGarasiEnum::Sepeda)
        ->and($s001->harga_sewa_harian)->toBe(20000.0)
        ->and($s001->getHargaSewaBulanan())->toBe(500000.0)
        ->and($s001->harga_sewa_bulanan)->toBe(500000.0)
        ->and($s001->status)->toBe(StatusGarasiEnum::Available);

    // Next batch continues sequence from S004
    $this->actingAs($admin)->post(route('admin.garages.store'), [
        'tipe_garasi' => 'sepeda',
        'jumlah' => 2,
        'harga_sewa_harian' => 25000,
    ])->assertSessionHasNoErrors();

    expect(Garasi::find('S004'))->not->toBeNull()
        ->and(Garasi::find('S005'))->not->toBeNull()
        ->and(Garasi::find('S005')->getHargaSewaBulanan())->toBe(625000.0);

    // AC-09 (Negative - Exceeding 999 quota per type):
    // Seed a garage at sequence C990, then try to create 15 more units (990 + 15 = 1005 > 999)
    Garasi::factory()->mobil('C990', 50000)->create();

    $quotaExceededResponse = $this->actingAs($admin)->post(route('admin.garages.store'), [
        'tipe_garasi' => 'mobil',
        'jumlah' => 15,
        'harga_sewa_harian' => 50000,
    ]);

    $quotaExceededResponse->assertSessionHasErrors(['jumlah']);
    expect(Garasi::find('C991'))->toBeNull();
});

test('electric vehicle garage requires kwh tariff and non-electric garages set kwh tariff to null', function () {
    $admin = User::factory()->admin()->create();

    // 1. Missing harga_kwh for mobil_listrik fails validation
    $missingKwhResponse = $this->actingAs($admin)->post(route('admin.garages.store'), [
        'tipe_garasi' => 'mobil_listrik',
        'jumlah' => 2,
        'harga_sewa_harian' => 100000,
    ]);

    $missingKwhResponse->assertSessionHasErrors(['harga_kwh']);
    expect(Garasi::find('E001'))->toBeNull();

    // 2. Valid mobil_listrik creation with harga_kwh
    $validEvResponse = $this->actingAs($admin)->post(route('admin.garages.store'), [
        'tipe_garasi' => 'mobil_listrik',
        'jumlah' => 2,
        'harga_sewa_harian' => 100000,
        'harga_kwh' => 2000,
    ]);

    $validEvResponse->assertSessionHasNoErrors();

    $e001 = Garasi::find('E001');
    $e002 = Garasi::find('E002');

    expect($e001)->not->toBeNull()
        ->and($e002)->not->toBeNull()
        ->and($e001->tipe_garasi)->toBe(TipeGarasiEnum::MobilListrik)
        ->and($e001->harga_sewa_harian)->toBe(100000.0)
        ->and($e001->getHargaSewaBulanan())->toBe(2500000.0)
        ->and($e001->tarif_kwh)->toBe(2000.0)
        ->and($e001->status)->toBe(StatusGarasiEnum::Available);
});

test('admin can update garage daily price kwh tariff and status', function () {
    $admin = User::factory()->admin()->create();
    $garasi = Garasi::factory()->mobilListrik('E001', 100000, 2000)->available()->create();

    $response = $this->actingAs($admin)->patch(route('admin.garages.update', $garasi), [
        'harga_sewa_harian' => 120000,
        'harga_kwh' => 2500,
        'status' => 'used',
    ]);

    $response->assertRedirect(route('admin.garages.index'))
        ->assertSessionHas('status');

    $garasi->refresh();

    expect($garasi->harga_sewa_harian)->toBe(120000.0)
        ->and($garasi->getHargaSewaBulanan())->toBe(3000000.0)
        ->and($garasi->tarif_kwh)->toBe(2500.0)
        ->and($garasi->status)->toBe(StatusGarasiEnum::Used)
        ->and($garasi->isUsed())->toBeTrue();
});

test('garage vehicle compatibility matrix enforces business rule br01', function () {
    $sepedaGarage = Garasi::factory()->sepeda('S001')->make();
    $mobilGarage = Garasi::factory()->mobil('C001')->make();
    $evGarage = Garasi::factory()->mobilListrik('E001')->make();

    // Sepeda garage ('S') accepts only Sepeda & SepedaMotor
    expect($sepedaGarage->isCompatibleWith(TipeKendaraanEnum::Sepeda))->toBeTrue()
        ->and($sepedaGarage->isCompatibleWith(TipeKendaraanEnum::SepedaMotor))->toBeTrue()
        ->and($sepedaGarage->isCompatibleWith(TipeKendaraanEnum::MobilBensin))->toBeFalse()
        ->and($sepedaGarage->isCompatibleWith(TipeKendaraanEnum::MobilListrik))->toBeFalse();

    // Mobil garage ('C') accepts MobilBensin and MobilListrik (without charging)
    expect($mobilGarage->isCompatibleWith(TipeKendaraanEnum::MobilBensin))->toBeTrue()
        ->and($mobilGarage->isCompatibleWith(TipeKendaraanEnum::MobilListrik))->toBeTrue()
        ->and($mobilGarage->isCompatibleWith(TipeKendaraanEnum::Sepeda))->toBeFalse()
        ->and($mobilGarage->isCompatibleWith(TipeKendaraanEnum::SepedaMotor))->toBeFalse();

    // Mobil Listrik garage ('E') accepts only MobilListrik
    expect($evGarage->isCompatibleWith(TipeKendaraanEnum::MobilListrik))->toBeTrue()
        ->and($evGarage->isCompatibleWith(TipeKendaraanEnum::MobilBensin))->toBeFalse()
        ->and($evGarage->isCompatibleWith(TipeKendaraanEnum::Sepeda))->toBeFalse()
        ->and($evGarage->isCompatibleWith(TipeKendaraanEnum::SepedaMotor))->toBeFalse();
});

test('penyewa has read-only access to garage catalog and is forbidden from managing garages', function () {
    $approvedTenant = User::factory()->withApprovedPenyewa()->create();
    $pendingTenant = User::factory()->withPendingPenyewa()->create();
    $garasi = Garasi::factory()->sepeda('S001', 20000)->create();

    // 1. Approved tenant can view read-only garage catalog
    $catalogResponse = $this->actingAs($approvedTenant)->get(route('tenant.garages.index'));
    $catalogResponse->assertOk()
        ->assertSee('Katalog Unit & Daftar Tarif Resmi Garasi', escape: false)
        ->assertSee('S001')
        ->assertSee('500.000');

    // 2. Approved tenant is forbidden from accessing Admin garage management routes
    $this->actingAs($approvedTenant)
        ->get(route('admin.garages.index'))
        ->assertForbidden();

    $this->actingAs($approvedTenant)
        ->post(route('admin.garages.store'), [
            'tipe_garasi' => 'sepeda',
            'jumlah' => 1,
            'harga_sewa_harian' => 20000,
        ])
        ->assertForbidden();

    $this->actingAs($approvedTenant)
        ->patch(route('admin.garages.update', $garasi), [
            'harga_sewa_harian' => 10000,
            'status' => 'used',
        ])
        ->assertForbidden();

    // 3. Pending tenant is redirected to verification notice
    $this->actingAs($pendingTenant)
        ->get(route('tenant.garages.index'))
        ->assertRedirect(route('tenant.verification.notice'));
});
