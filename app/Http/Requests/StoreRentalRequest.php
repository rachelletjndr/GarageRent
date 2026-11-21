<?php

namespace App\Http\Requests;

use App\Enums\TipeKendaraanEnum;
use App\Enums\TipeSewaEnum;
use App\Models\Garasi;
use App\Models\Kendaraan;
use App\Services\GarageAvailabilityService;
use App\Services\RentalCalendarValidator;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Validation rules for New Garage Rental Booking Form (FR-06, FR-07, FR-08 & Section 7.3 SRS v1.2.0).
 */
class StoreRentalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->isPenyewa()
            && ($user->penyewa?->isApproved() ?? false);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('tipe_sewa') === TipeSewaEnum::Bulanan->value && ! $this->filled('durasi_bulan')) {
            $this->merge(['durasi_bulan' => 1]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tanggal_mulai' => [
                'required',
                'date',
                'after_or_equal:today',
            ],
            'tipe_sewa' => [
                'required',
                'string',
                'in:harian,bulanan',
            ],
            'durasi_hari' => [
                'required_if:tipe_sewa,harian',
                'nullable',
                'integer',
                'min:1',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->input('tipe_sewa') !== TipeSewaEnum::Harian->value || ! is_numeric($value)) {
                        return;
                    }

                    $tanggalMulai = $this->input('tanggal_mulai');
                    if (! is_string($tanggalMulai) || strtotime($tanggalMulai) === false) {
                        return;
                    }

                    $validator = app(RentalCalendarValidator::class);
                    $maxHarian = $validator->getMaxDurasiHarian($tanggalMulai);

                    if ((int) $value > $maxHarian) {
                        $fail("Durasi sewa harian maksimal adalah jumlah hari bulan kalender dikurangi 1 hari (Maksimal {$maxHarian} hari).");
                    }
                },
            ],
            'durasi_bulan' => [
                'required_if:tipe_sewa,bulanan',
                'nullable',
                'integer',
                'min:1',
                'max:1',
            ],
            'tipe_kendaraan' => [
                'required',
                'string',
                'in:sepeda,sepeda_motor,mobil_bensin,mobil_listrik',
            ],
            'plat_nomor' => [
                'required_unless:tipe_kendaraan,sepeda',
                'nullable',
                'string',
                'max:15',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || trim($value) === '') {
                        return;
                    }

                    $penyewaId = $this->user()?->penyewa?->id;
                    $normalizedPlat = strtoupper(trim($value));

                    $ownedByOther = Kendaraan::query()
                        ->where('plat_nomor', $normalizedPlat)
                        ->when($penyewaId !== null, fn ($q) => $q->where('penyewa_id', '!=', $penyewaId))
                        ->exists();

                    if ($ownedByOther) {
                        $fail('Plat nomor kendaraan tersebut sudah terdaftar milik penyewa lain.');
                    }
                },
            ],
            'merek' => [
                'required',
                'string',
                'max:50',
            ],
            'kapasitas_kwh' => [
                'required_if:tipe_kendaraan,mobil_listrik',
                'nullable',
                'numeric',
                'min:1',
            ],
            'garasi_id' => [
                'required',
                'string',
                'exists:garasi,id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value)) {
                        return;
                    }

                    $garasi = Garasi::find($value);
                    $tipeKendaraan = TipeKendaraanEnum::tryFrom((string) $this->input('tipe_kendaraan'));

                    if (! $garasi || ! $tipeKendaraan) {
                        return;
                    }

                    if (! $garasi->isCompatibleWith($tipeKendaraan)) {
                        $fail('Garasi yang dipilih tidak cocok atau tidak tersedia pada rentang tanggal tersebut.');

                        return;
                    }

                    $tanggalMulai = $this->input('tanggal_mulai');
                    $tipeSewa = TipeSewaEnum::tryFrom((string) $this->input('tipe_sewa'));

                    if (! is_string($tanggalMulai) || strtotime($tanggalMulai) === false || ! $tipeSewa) {
                        return;
                    }

                    $durasi = $tipeSewa === TipeSewaEnum::Harian
                        ? (int) $this->input('durasi_hari', 0)
                        : (int) $this->input('durasi_bulan', 1);

                    $calendarValidator = app(RentalCalendarValidator::class);

                    if (! $calendarValidator->validateDurasi($tanggalMulai, $tipeSewa, $durasi)) {
                        return;
                    }

                    $tanggalSelesai = $calendarValidator->hitungTanggalSelesai(
                        Carbon::parse($tanggalMulai),
                        $tipeSewa,
                        $durasi,
                    );

                    $availabilityService = app(GarageAvailabilityService::class);
                    if (! $availabilityService->isGarageAvailableForRange($garasi, $tanggalMulai, $tanggalSelesai)) {
                        $fail('Garasi yang dipilih tidak cocok atau tidak tersedia pada rentang tanggal tersebut.');
                    }
                },
            ],
            'bukti_bayar' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,png,jpg',
                'max:2048',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors according to SRS Section 7.3.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tanggal_mulai.required' => 'Tanggal mulai sewa tidak boleh merupakan tanggal yang telah berlalu.',
            'tanggal_mulai.date' => 'Tanggal mulai sewa tidak boleh merupakan tanggal yang telah berlalu.',
            'tanggal_mulai.after_or_equal' => 'Tanggal mulai sewa tidak boleh merupakan tanggal yang telah berlalu.',
            'tipe_sewa.required' => "Tipe sewa harus berupa 'harian' atau 'bulanan'.",
            'tipe_sewa.in' => "Tipe sewa harus berupa 'harian' atau 'bulanan'.",
            'durasi_hari.required_if' => 'Durasi sewa harian maksimal adalah jumlah hari bulan kalender dikurangi 1 hari.',
            'durasi_hari.integer' => 'Durasi sewa harian maksimal adalah jumlah hari bulan kalender dikurangi 1 hari.',
            'durasi_hari.min' => 'Durasi sewa harian maksimal adalah jumlah hari bulan kalender dikurangi 1 hari.',
            'durasi_bulan.required_if' => 'Durasi sewa bulanan dalam satu kali pemesanan maksimal 1 bulan kalender.',
            'durasi_bulan.integer' => 'Durasi sewa bulanan dalam satu kali pemesanan maksimal 1 bulan kalender.',
            'durasi_bulan.min' => 'Durasi sewa bulanan dalam satu kali pemesanan maksimal 1 bulan kalender.',
            'durasi_bulan.max' => 'Durasi sewa bulanan dalam satu kali pemesanan maksimal 1 bulan kalender.',
            'tipe_kendaraan.required' => 'Pilihan tipe kendaraan tidak valid.',
            'tipe_kendaraan.in' => 'Pilihan tipe kendaraan tidak valid.',
            'plat_nomor.required' => 'Plat nomor kendaraan wajib diisi.',
            'plat_nomor.required_unless' => 'Plat nomor kendaraan wajib diisi.',
            'plat_nomor.max' => 'Plat nomor kendaraan wajib diisi maksimal 15 karakter.',
            'merek.required' => 'Merek kendaraan wajib diisi.',
            'merek.max' => 'Merek kendaraan wajib diisi maksimal 50 karakter.',
            'kapasitas_kwh.required_if' => 'Kapasitas baterai kWh wajib diisi untuk kendaraan mobil listrik.',
            'kapasitas_kwh.numeric' => 'Kapasitas baterai kWh wajib diisi untuk kendaraan mobil listrik.',
            'kapasitas_kwh.min' => 'Kapasitas baterai kWh wajib diisi untuk kendaraan mobil listrik.',
            'garasi_id.required' => 'Garasi yang dipilih tidak cocok atau tidak tersedia pada rentang tanggal tersebut.',
            'garasi_id.exists' => 'Garasi yang dipilih tidak cocok atau tidak tersedia pada rentang tanggal tersebut.',
            'bukti_bayar.required' => 'Bukti pembayaran wajib diunggah (format JPG, JPEG, atau PNG, maksimal 2 MB).',
            'bukti_bayar.file' => 'Bukti pembayaran wajib berupa berkas gambar yang valid.',
            'bukti_bayar.image' => 'Format file bukti pembayaran tidak sesuai. Harap unggah gambar berformat JPG, JPEG, atau PNG.',
            'bukti_bayar.mimes' => 'Format file bukti pembayaran tidak sesuai. Harap unggah gambar berformat JPG, JPEG, atau PNG.',
            'bukti_bayar.max' => 'Ukuran file bukti pembayaran terlalu besar. Ukuran maksimal gambar adalah 2 MB (2.048 KB).',
            'bukti_bayar.uploaded' => 'Gagal mengunggah bukti pembayaran karena ukuran gambar melebihi batas maksimal 2 MB.',
        ];
    }
}
