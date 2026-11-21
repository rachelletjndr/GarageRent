<?php

use App\Enums\RoleEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Penyewa;
use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk()
        ->assertSee('Registrasi Akun Penyewa')
        ->assertSee('NIK KTP (16 Digit Angka)');
});

test('new users can register as pending tenant and require admin verification', function () {
    $response = $this->post(route('register.store'), [
        'nik' => '3171010506980005',
        'name' => 'John Doe',
        'no_telp' => '081234567899',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $user = User::where('email', 'test@example.com')->first();
    $penyewa = Penyewa::where('nik', '3171010506980005')->first();

    expect($user)->not->toBeNull()
        ->and($user->role)->toBe(RoleEnum::Penyewa)
        ->and($penyewa)->not->toBeNull()
        ->and($penyewa->user_id)->toBe($user->id)
        ->and($penyewa->nama_lengkap)->toBe('John Doe')
        ->and($penyewa->no_telp)->toBe('081234567899')
        ->and($penyewa->status_verifikasi)->toBe(VerificationStatusEnum::Pending);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('login', absolute: false));

    // Pending tenant should not have an active login session until approved by Admin
    $this->assertGuest();
});
