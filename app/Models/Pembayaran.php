<?php

namespace App\Models;

use Database\Factories\PembayaranFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $nomor_pembayaran
 * @property int $penyewaan_id
 * @property int|null $perpanjangan_id
 * @property string $jenis_pembayaran
 * @property float $nominal
 * @property string $bukti_transfer
 * @property Carbon $waktu_bayar
 * @property string $status_verifikasi
 * @property int|null $diverifikasi_oleh
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Penyewaan $penyewaan
 * @property-read PerpanjanganSewa|null $perpanjanganSewa
 * @property-read User|null $verifier
 */
#[Fillable([
    'nomor_pembayaran',
    'penyewaan_id',
    'perpanjangan_id',
    'jenis_pembayaran',
    'nominal',
    'bukti_transfer',
    'waktu_bayar',
    'status_verifikasi',
    'diverifikasi_oleh',
])]
class Pembayaran extends Model
{
    /** @use HasFactory<PembayaranFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pembayaran';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nominal' => 'float',
            'waktu_bayar' => 'datetime',
        ];
    }

    /**
     * Get the rental transaction associated with this payment.
     *
     * @return BelongsTo<Penyewaan, $this>
     */
    public function penyewaan(): BelongsTo
    {
        return $this->belongsTo(Penyewaan::class, 'penyewaan_id');
    }

    /**
     * Get the extension record associated with this payment (if jenis_pembayaran = perpanjangan).
     *
     * @return BelongsTo<PerpanjanganSewa, $this>
     */
    public function perpanjanganSewa(): BelongsTo
    {
        return $this->belongsTo(PerpanjanganSewa::class, 'perpanjangan_id');
    }

    /**
     * Get the admin user who verified this payment.
     *
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    /**
     * Mark the payment as verified by an Admin user (UML Class Diagram method).
     */
    public function verifikasi(User $admin): void
    {
        $this->update([
            'status_verifikasi' => 'valid',
            'diverifikasi_oleh' => $admin->id,
        ]);
    }

    /**
     * Mark the payment as invalid/rejected by an Admin user (UML Class Diagram method).
     */
    public function tolak(User $admin): void
    {
        $this->update([
            'status_verifikasi' => 'invalid',
            'diverifikasi_oleh' => $admin->id,
        ]);
    }

    /**
     * Get the public storage URL for the payment receipt image.
     */
    public function getBuktiUrl(): string
    {
        return Storage::disk('public')->url($this->bukti_transfer);
    }
}
