<?php

namespace App\Http\Requests;

use App\Models\Garasi;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation rules for updating an existing garage's daily tariff, electricity tariff, and operational status.
 */
class UpdateGarageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Prepare the data for validation.
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        /** @var Garasi|null $garasi */
        $garasi = $this->route('garasi');
        $isElectric = $garasi?->isElectricGarage() ?? false;

        return [
            'harga_sewa_harian' => ['required', 'numeric', 'min:1000'],
            'harga_kwh' => $isElectric
                ? ['required', 'numeric', 'min:100']
                : ['nullable', 'numeric', 'min:100'],
            'status' => ['required', 'string', 'in:available,used'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'harga_sewa_harian.required' => 'Harga sewa harian minimal Rp 1.000.',
            'harga_sewa_harian.numeric' => 'Harga sewa harian minimal Rp 1.000.',
            'harga_sewa_harian.min' => 'Harga sewa harian minimal Rp 1.000.',
            'harga_kwh.required' => 'Harga listrik per kWh wajib diisi untuk garasi mobil listrik (minimal Rp 100).',
            'harga_kwh.numeric' => 'Harga listrik per kWh wajib diisi untuk garasi mobil listrik (minimal Rp 100).',
            'harga_kwh.min' => 'Harga listrik per kWh wajib diisi untuk garasi mobil listrik (minimal Rp 100).',
            'status.required' => 'Status garasi harus berupa available atau used.',
            'status.in' => 'Status garasi harus berupa available atau used.',
        ];
    }
}
