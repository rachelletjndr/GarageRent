<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request validating multi-dimensional Rental Report filter inputs (FR-10).
 */
class FilterRentalReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Exclusive to Admin users.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the report filter request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mode' => ['nullable', 'string', 'in:bulanan,harian,tipe_garasi,penyewa'],
            'tahun' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'bulan' => ['nullable', 'integer', 'min:1', 'max:12'],
            'tanggal' => ['nullable', 'date'],
            'tipe_garasi' => ['nullable', 'string', 'in:sepeda,mobil,mobil_listrik'],
            'penyewa_id' => ['nullable', 'integer', 'exists:penyewa,id'],
            'status_sewa' => ['nullable', 'string', 'in:aktif,selesai,dibatalkan'],
        ];
    }

    /**
     * Get custom Indonesian error messages for report filters.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mode.in' => 'Mode laporan harus berupa bulanan, harian, tipe_garasi, atau penyewa.',
            'tahun.integer' => 'Tahun laporan harus berupa angka tahun yang valid (2020-2100).',
            'tahun.min' => 'Tahun laporan minimal tahun 2020.',
            'tahun.max' => 'Tahun laporan maksimal tahun 2100.',
            'bulan.integer' => 'Bulan laporan harus berupa angka bulan antara 1 hingga 12.',
            'bulan.min' => 'Bulan laporan minimal 1 (Januari).',
            'bulan.max' => 'Bulan laporan maksimal 12 (Desember).',
            'tanggal.date' => 'Format tanggal filter harian tidak valid.',
            'tipe_garasi.in' => 'Filter tipe garasi harus berupa sepeda, mobil, atau mobil_listrik.',
            'penyewa_id.integer' => 'Filter penyewa tidak valid.',
            'penyewa_id.exists' => 'Data penyewa yang dipilih tidak ditemukan.',
            'status_sewa.in' => 'Status sewa harus berupa aktif, selesai, atau dibatalkan.',
        ];
    }

    /**
     * Normalize filter parameters with sensible defaults.
     *
     * @return array{
     *     mode: string,
     *     tahun: int,
     *     bulan: int,
     *     tanggal: string|null,
     *     tipe_garasi: string|null,
     *     penyewa_id: int|null,
     *     status_sewa: string|null
     * }
     */
    public function normalizedFilters(): array
    {
        $validated = $this->validated();

        return [
            'mode' => (string) ($validated['mode'] ?? 'bulanan'),
            'tahun' => isset($validated['tahun']) ? (int) $validated['tahun'] : (int) now()->year,
            'bulan' => isset($validated['bulan']) ? (int) $validated['bulan'] : (int) now()->month,
            'tanggal' => isset($validated['tanggal']) && $validated['tanggal'] !== '' ? (string) $validated['tanggal'] : null,
            'tipe_garasi' => isset($validated['tipe_garasi']) && $validated['tipe_garasi'] !== '' ? (string) $validated['tipe_garasi'] : null,
            'penyewa_id' => isset($validated['penyewa_id']) && $validated['penyewa_id'] !== '' ? (int) $validated['penyewa_id'] : null,
            'status_sewa' => isset($validated['status_sewa']) && $validated['status_sewa'] !== '' ? (string) $validated['status_sewa'] : null,
        ];
    }
}
