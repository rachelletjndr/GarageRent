<?php

namespace App\Http\Requests;

use App\Enums\VerificationStatusEnum;
use App\Models\Penyewa;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation rules and messages for Tenant Self-Registration (FR-01 & Section 7.1 SRS).
 * Allows previously rejected accounts (status_verifikasi = rejected) to re-register
 * using their previous email and/or NIK.
 */
class RegisterTenantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return self::rulesForRegistration();
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::messagesForRegistration();
    }

    /**
     * Reusable registration validation rules according to SRS Section 7.1.
     * Rejected accounts are excluded from unique blocking so they can re-register.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rulesForRegistration(): array
    {
        return [
            'nik' => [
                'required',
                'numeric',
                'digits:16',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $activeOrPendingExists = Penyewa::query()
                        ->where('nik', (string) $value)
                        ->where('status_verifikasi', '!=', VerificationStatusEnum::Rejected->value)
                        ->exists();

                    if ($activeOrPendingExists) {
                        $fail('NIK harus 16 digit angka dan belum terdaftar.');
                    }
                },
            ],
            'name' => [
                'required',
                'string',
                'max:100',
            ],
            'no_telp' => [
                'required',
                'numeric',
                'digits_between:10,15',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:100',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $existingUser = User::query()
                        ->with('penyewa')
                        ->where('email', (string) $value)
                        ->first();

                    if ($existingUser && (! $existingUser->penyewa || ! $existingUser->penyewa->isRejected())) {
                        $fail('Email harus berformat valid dan belum digunakan.');
                    }
                },
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ];
    }

    /**
     * Reusable Indonesian error messages according to SRS Section 7.1.
     *
     * @return array<string, string>
     */
    public static function messagesForRegistration(): array
    {
        return [
            'nik.required' => 'NIK harus 16 digit angka dan belum terdaftar.',
            'nik.numeric' => 'NIK harus 16 digit angka dan belum terdaftar.',
            'nik.digits' => 'NIK harus 16 digit angka dan belum terdaftar.',
            'nik.unique' => 'NIK harus 16 digit angka dan belum terdaftar.',
            'name.required' => 'Nama lengkap wajib diisi maksimal 100 karakter.',
            'name.string' => 'Nama lengkap wajib diisi maksimal 100 karakter.',
            'name.max' => 'Nama lengkap wajib diisi maksimal 100 karakter.',
            'no_telp.required' => 'Nomor telepon harus bernilai numerik antara 10-15 digit.',
            'no_telp.numeric' => 'Nomor telepon harus bernilai numerik antara 10-15 digit.',
            'no_telp.digits_between' => 'Nomor telepon harus bernilai numerik antara 10-15 digit.',
            'email.required' => 'Email harus berformat valid dan belum digunakan.',
            'email.email' => 'Email harus berformat valid dan belum digunakan.',
            'email.unique' => 'Email harus berformat valid dan belum digunakan.',
            'password.required' => 'Password minimal terdiri dari 8 karakter.',
            'password.min' => 'Password minimal terdiri dari 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok dengan password yang dimasukkan.',
        ];
    }
}
