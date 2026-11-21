<?php

namespace App\Http\Requests;

use App\Models\Penyewaan;
use App\Services\RentalExtensionService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Form Request validating Rental Extension submissions by Admin (SRS Section 7.4, FR-09, BR-12).
 */
class StoreExtensionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Exclusive to Admin (BR-12 / AC-16).
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'penyewaan_id' => [
                'required',
                'integer',
                'exists:penyewaan,id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    /** @var Penyewaan|null $penyewaan */
                    $penyewaan = Penyewaan::query()->find((int) $value);

                    if (! $penyewaan || ! $penyewaan->isActive()) {
                        $fail('Transaksi sewa tidak ditemukan atau sudah tidak berstatus aktif.');
                    }
                },
            ],
            'tanggal_selesai_baru' => [
                'required',
                'date',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $penyewaanId = $this->input('penyewaan_id');

                    if (! $penyewaanId) {
                        return;
                    }

                    /** @var Penyewaan|null $penyewaan */
                    $penyewaan = Penyewaan::query()->with('garasi')->find((int) $penyewaanId);

                    if (! $penyewaan) {
                        return;
                    }

                    try {
                        $newEnd = Carbon::parse((string) $value)->startOfDay();
                    } catch (Throwable) {
                        $fail('Format tanggal selesai baru tidak valid.');

                        return;
                    }

                    $oldEnd = $penyewaan->tanggal_selesai->copy()->startOfDay();

                    if ($newEnd->lessThanOrEqualTo($oldEnd)) {
                        $fail('Tanggal selesai baru harus lebih besar dari tanggal selesai saat ini.');

                        return;
                    }

                    /** @var RentalExtensionService $extensionService */
                    $extensionService = app(RentalExtensionService::class);

                    if (! $extensionService->isExtensionRangeAvailable($penyewaan, $newEnd)) {
                        $fail('Garasi sudah dipesan pada rentang tanggal perpanjangan tersebut.');
                    }
                },
            ],
            'bukti_bayar_perpanjangan' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,png,jpg',
                'max:2048',
            ],
        ];
    }

    /**
     * Get custom Indonesian validation messages matching SRS Section 7.4.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'penyewaan_id.required' => 'Transaksi sewa aktif wajib dipilih.',
            'penyewaan_id.integer' => 'Transaksi sewa tidak ditemukan atau sudah tidak berstatus aktif.',
            'penyewaan_id.exists' => 'Transaksi sewa tidak ditemukan atau sudah tidak berstatus aktif.',
            'tanggal_selesai_baru.required' => 'Tanggal selesai baru wajib diisi.',
            'tanggal_selesai_baru.date' => 'Format tanggal selesai baru tidak valid.',
            'bukti_bayar_perpanjangan.required' => 'Bukti pembayaran perpanjangan wajib diunggah (format JPG, JPEG, atau PNG, maksimal 2 MB).',
            'bukti_bayar_perpanjangan.file' => 'Bukti pembayaran perpanjangan wajib berupa berkas gambar yang valid.',
            'bukti_bayar_perpanjangan.image' => 'Format file bukti pembayaran tidak sesuai. Harap unggah gambar berformat JPG, JPEG, atau PNG.',
            'bukti_bayar_perpanjangan.mimes' => 'Format file bukti pembayaran tidak sesuai. Harap unggah gambar berformat JPG, JPEG, atau PNG.',
            'bukti_bayar_perpanjangan.max' => 'Ukuran file bukti pembayaran terlalu besar. Ukuran maksimal gambar adalah 2 MB (2.048 KB).',
            'bukti_bayar_perpanjangan.uploaded' => 'Gagal mengunggah bukti pembayaran karena ukuran gambar melebihi batas maksimal 2 MB.',
        ];
    }
}
