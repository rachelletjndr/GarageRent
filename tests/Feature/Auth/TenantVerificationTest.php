<?php

use App\Enums\VerificationStatusEnum;
use App\Models\Penyewa;
use App\Models\User;
use App\Services\AdminVerificationService;

test('tenant registration enforces 16 digit numeric unique nik and phone validation', function () {
    // 1. Invalid NIK (less than 16 digits) & invalid phone (less than 10 digits)
    $response = $this->post(route('register.store'), [
        'nik' => '12345',
        'name' => 'Calon Penyewa',
        'no_telp' => '08123',
        'email' => 'invalid@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertSessionHasErrors(['nik', 'no_telp']);

    // 2. Duplicate NIK validation
    User::factory()->withApprovedPenyewa(['nik' => '3171010101900001'])->create();

    $duplicateResponse = $this->post(route('register.store'), [
        'nik' => '3171010101900001',
        'name' => 'Duplikat NIK',
        'no_telp' => '081234567890',
        'email' => 'duplikat@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $duplicateResponse->assertSessionHasErrors(['nik']);
});

test('pending tenant cannot login before admin verification', function () {
    $pendingUser = User::factory()->withPendingPenyewa([
        'nik' => '3171010202950002',
    ])->create([
        'email' => 'pending@garasi.test',
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $pendingUser->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors(['email']);
    $this->assertGuest();
});

test('rejected tenant cannot login and sees admin rejection reason', function () {
    $rejectedUser = User::factory()->withRejectedPenyewa(
        reason: 'NIK KTP tidak terdaftar di Dukcapil.',
        attributes: ['nik' => '3171010303960003'],
    )->create([
        'email' => 'rejected@garasi.test',
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $rejectedUser->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors([
        'email' => 'Pendaftaran akun Anda telah ditolak oleh Admin. Alasan penolakan: NIK KTP tidak terdaftar di Dukcapil.',
    ]);
    $this->assertGuest();
});

test('approved tenant and admin can login successfully', function () {
    $approvedTenant = User::factory()->withApprovedPenyewa([
        'nik' => '3171010404940004',
    ])->create();

    $response = $this->post(route('login.store'), [
        'email' => $approvedTenant->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($approvedTenant);
});

test('admin can view and filter tenant ktp verifications', function () {
    $admin = User::factory()->admin()->create();
    $pendingUser = User::factory()->withPendingPenyewa([
        'nik' => '3171010808990008',
        'nama_lengkap' => 'Rina Permata',
    ])->create();

    $response = $this->actingAs($admin)->get(route('admin.verifications.index', [
        'status' => 'pending',
        'search' => '3171010808990008',
    ]));

    $response->assertOk()
        ->assertSee('Verifikasi Pendaftaran & Validasi KTP Penyewa', escape: false)
        ->assertSee('3171010808990008')
        ->assertSee('Rina Permata')
        ->assertDontSee("'variant' => 'outline'", escape: false)
        ->assertDontSee('Team');
});

test('admin can approve or reject tenant registration and approved tenant can then login', function () {
    $admin = User::factory()->admin()->create();
    $tenantUser = User::factory()->withPendingPenyewa([
        'nik' => '3171010909970009',
        'nama_lengkap' => 'Dedi Kurniawan',
    ])->create([
        'email' => 'dedi@garasi.test',
    ]);

    /** @var Penyewa $penyewa */
    $penyewa = $tenantUser->penyewa;

    // 1. Admin approves tenant KTP
    $approveResponse = $this->actingAs($admin)
        ->patch(route('admin.verifications.approve', $penyewa));

    $approveResponse->assertRedirect(route('admin.verifications.index'))
        ->assertSessionHas('status');

    $penyewa->refresh();
    expect($penyewa->status_verifikasi)->toBe(VerificationStatusEnum::Approved)
        ->and($penyewa->diverifikasi_oleh)->toBe($admin->id)
        ->and($penyewa->tanggal_verifikasi)->not->toBeNull();

    // 2. Approved tenant can now log in
    auth()->logout();
    $loginResponse = $this->post(route('login.store'), [
        'email' => 'dedi@garasi.test',
        'password' => 'password',
    ]);
    $loginResponse->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($tenantUser);

    // 3. Admin rejects another tenant with rejection reason
    $rejectedCandidate = User::factory()->withPendingPenyewa([
        'nik' => '3171011010980010',
    ])->create();

    $rejectResponse = $this->actingAs($admin)
        ->patch(route('admin.verifications.reject', $rejectedCandidate->penyewa), [
            'catatan_penolakan' => 'Format tanggal lahir pada NIK KTP tidak valid.',
        ]);

    $rejectResponse->assertRedirect(route('admin.verifications.index'))
        ->assertSessionHas('status');

    $rejectedCandidate->penyewa->refresh();
    expect($rejectedCandidate->penyewa->status_verifikasi)->toBe(VerificationStatusEnum::Rejected)
        ->and($rejectedCandidate->penyewa->catatan_penolakan)->toBe('Format tanggal lahir pada NIK KTP tidak valid.')
        ->and($rejectedCandidate->penyewa->diverifikasi_oleh)->toBe($admin->id);
});

test('non-admin penyewa is forbidden from accessing admin verification endpoints', function () {
    $tenant = User::factory()->withApprovedPenyewa()->create();
    $pendingCandidate = User::factory()->withPendingPenyewa()->create();

    $this->actingAs($tenant)
        ->get(route('admin.verifications.index'))
        ->assertForbidden();

    $this->actingAs($tenant)
        ->patch(route('admin.verifications.approve', $pendingCandidate->penyewa))
        ->assertForbidden();

    $this->actingAs($tenant)
        ->patch(route('admin.verifications.reject', $pendingCandidate->penyewa), [
            'catatan_penolakan' => 'Mencoba bypass',
        ])
        ->assertForbidden();
});

test('ktp nik structural inspector accurately parses 16 digit nik format', function () {
    $service = app(AdminVerificationService::class);

    // Male NIK: 31.71.01, DOB: 01-05-90, Seq: 0001
    $maleResult = $service->inspectKtpNik('3171010105900001');
    expect($maleResult['is_valid_format'])->toBeTrue()
        ->and($maleResult['kode_wilayah'])->toBe('31.71.01')
        ->and($maleResult['tanggal_lahir_kode'])->toBe('01-05-90')
        ->and($maleResult['jenis_kelamin_estimasi'])->toBe('Laki-laki')
        ->and($maleResult['nomor_urut'])->toBe('0001');

    // Female NIK: day 02 + 40 = 42 -> 3171014208920002
    $femaleResult = $service->inspectKtpNik('3171014208920002');
    expect($femaleResult['is_valid_format'])->toBeTrue()
        ->and($femaleResult['tanggal_lahir_kode'])->toBe('02-08-92')
        ->and($femaleResult['jenis_kelamin_estimasi'])->toBe('Perempuan');
});
