<?php

namespace App\Models;

use Database\Factories\PerpanjanganSewaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nomor_perpanjangan
 * @property int $penyewaan_id
 * @property int $admin_id
 * @property Carbon $tanggal_selesai_lama
 * @property Carbon $tanggal_selesai_baru
 * @property int $selisih_hari
 * @property float $tarif_harian_snapshot
 * @property float $biaya_perpanjangan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Penyewaan $penyewaan
 * @property-read User $admin
 * @property-read Pembayaran|null $pembayaran
 */
#[Fillable([
    'nomor_perpanjangan',
    'penyewaan_id',
    'admin_id',
    'tanggal_selesai_lama',
    'tanggal_selesai_baru',
    'selisih_hari',
    'tarif_harian_snapshot',
    'biaya_perpanjangan',
])]
class PerpanjanganSewa extends Model
{
    /** @use HasFactory<PerpanjanganSewaFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'perpanjangan_sewa';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_selesai_lama' => 'date',
            'tanggal_selesai_baru' => 'date',
            'selisih_hari' => 'integer',
            'tarif_harian_snapshot' => 'float',
            'biaya_perpanjangan' => 'float',
        ];
    }

    /**
     * Get the parent rental transaction that was extended.
     *
     * @return BelongsTo<Penyewaan, $this>
     */
    public function penyewaan(): BelongsTo
    {
        return $this->belongsTo(Penyewaan::class, 'penyewaan_id');
    }

    /**
     * Get the Admin user who executed this extension.
     *
     * @return BelongsTo<User, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Get the payment receipt record linked to this extension.
     *
     * @return HasOne<Pembayaran, $this>
     */
    public function pembayaran(): HasOne
    {
        return $this->hasOne(Pembayaran::class, 'perpanjangan_id');
    }

    /**
     * Calculate the additional days difference (tanggal_selesai_baru - tanggal_selesai_lama).
     */
    public function hitungSelisihHari(): int
    {
        return (int) $this->tanggal_selesai_lama
            ->copy()
            ->startOfDay()
            ->diffInDays($this->tanggal_selesai_baru->copy()->startOfDay());
    }

    /**
     * Calculate the extension fee in IDR (selisih_hari * tarif_harian_snapshot).
     */
    public function hitungBiayaPerpanjangan(): float
    {
        return round($this->hitungSelisihHari() * (float) $this->tarif_harian_snapshot, 2);
    }
}
