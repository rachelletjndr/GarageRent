<?php

namespace App\Http\Requests;

use App\Enums\TipeGarasiEnum;
use App\Services\GarageFactoryService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation rules for Admin Bulk Garage Creation Form (FR-04 & Section 7.2 SRS v1.2.0).
 *
 * Note: Per SRS v1.2.0 Perubahan 1, `harga_sewa_bulanan` is NOT an Admin input;
 * it is automatically calculated as 25 x `harga_sewa_harian`.
 */
class BulkCreateGarageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Prepare the data for validation (normalize tarif_kwh and harga_kwh alias).
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('harga_kwh') && $this->has('tarif_kwh')) {
            $this->merge([
                'harga_kwh' => $this->input('tarif_kwh'),
            ]);
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
            'tipe_garasi' => [
                'required',
                'string',
                'in:sepeda,mobil,mobil_listrik',
            ],
            'jumlah' => [
                'required',
                'integer',
                'min:1',
                'max:'.GarageFactoryService::MAX_GARAGES_PER_TYPE,
                function (string $attribute, mixed $value, Closure $fail): void {
                    $tipe = TipeGarasiEnum::tryFrom((string) $this->input('tipe_garasi'));

                    if (! $tipe || ! is_numeric($value)) {
                        return;
                    }

                    $remainingQuota = app(GarageFactoryService::class)->getRemainingQuota($tipe);

                    if ((int) $value > $remainingQuota) {
                        $fail("Jumlah garasi minimal 1 dan total garasi per tipe maksimal 999. Sisa kuota {$tipe->label()} saat ini: {$remainingQuota} unit.");
                    }
                },
            ],
            'harga_sewa_harian' => [
                'required',
                'numeric',
                'min:1000',
            ],
            'harga_kwh' => [
                'required_if:tipe_garasi,mobil_listrik',
                'nullable',
                'numeric',
                'min:100',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors according to SRS Section 7.2.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipe_garasi.required' => 'Tipe garasi harus berupa sepeda, mobil, atau mobil listrik.',
            'tipe_garasi.in' => 'Tipe garasi harus berupa sepeda, mobil, atau mobil listrik.',
            'jumlah.required' => 'Jumlah garasi minimal 1 dan total garasi per tipe maksimal 999.',
            'jumlah.integer' => 'Jumlah garasi minimal 1 dan total garasi per tipe maksimal 999.',
            'jumlah.min' => 'Jumlah garasi minimal 1 dan total garasi per tipe maksimal 999.',
            'jumlah.max' => 'Jumlah garasi minimal 1 dan total garasi per tipe maksimal 999.',
            'harga_sewa_harian.required' => 'Harga sewa harian minimal Rp 1.000.',
            'harga_sewa_harian.numeric' => 'Harga sewa harian minimal Rp 1.000.',
            'harga_sewa_harian.min' => 'Harga sewa harian minimal Rp 1.000.',
            'harga_kwh.required_if' => 'Harga listrik per kWh wajib diisi untuk garasi mobil listrik (minimal Rp 100).',
            'harga_kwh.numeric' => 'Harga listrik per kWh wajib diisi untuk garasi mobil listrik (minimal Rp 100).',
            'harga_kwh.min' => 'Harga listrik per kWh wajib diisi untuk garasi mobil listrik (minimal Rp 100).',
        ];
    }
}
