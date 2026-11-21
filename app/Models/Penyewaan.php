<?php

namespace App\Models;

use App\Enums\StatusSewaEnum;
use App\Enums\TipeSewaEnum;
use Database\Factories\PenyewaanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nomor_transaksi
 * @property int $penyewa_id
 * @property string $garasi_id
 * @property int $kendaraan_id
 * @property Carbon $tanggal_mulai
 * @property Carbon $tanggal_selesai
 * @property TipeSewaEnum $tipe_sewa
 * @property int $durasi
 * @property float $tarif_pokok
 * @property float $biaya_listrik
 * @property float $total_bayar
 * @property StatusSewaEnum $status_sewa
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Penyewa $penyewa
 * @property-read Garasi $garasi
 * @property-read Kendaraan $kendaraan
 * @property-read Collection<int, Pembayaran> $pembayaran
 * @property-read Collection<int, PerpanjanganSewa> $perpanjanganSewa
 */
#[Fillable([
    'nomor_transaksi',
    'penyewa_id',
    'garasi_id',
    'kendaraan_id',
    'tanggal_mulai',
    'tanggal_selesai',
    'tipe_sewa',
    'durasi',
    'tarif_pokok',
    'biaya_listrik',
    'total_bayar',
    'status_sewa',
])]
class Penyewaan extends Model
{
    /** @use HasFactory<PenyewaanFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'penyewaan';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'tipe_sewa' => TipeSewaEnum::class,
            'durasi' => 'integer',
            'tarif_pokok' => 'float',
            'biaya_listrik' => 'float',
            'total_bayar' => 'float',
            'status_sewa' => StatusSewaEnum::class,
        ];
    }

    /**
     * Get the tenant who booked this rental.
     *
     * @return BelongsTo<Penyewa, $this>
     */
    public function penyewa(): BelongsTo
    {
        return $this->belongsTo(Penyewa::class, 'penyewa_id');
    }

    /**
     * Get the garage unit allocated to this rental.
     *
     * @return BelongsTo<Garasi, $this>
     */
    public function garasi(): BelongsTo
    {
        return $this->belongsTo(Garasi::class, 'garasi_id', 'id');
    }

    /**
     * Get the vehicle parked in this rental.
     *
     * @return BelongsTo<Kendaraan, $this>
     */
    public function kendaraan(): BelongsTo
    {
        return $this->belongsTo(Kendaraan::class, 'kendaraan_id');
    }

    /**
     * Get all payment records associated with this rental transaction.
     *
     * @return HasMany<Pembayaran, $this>
     */
    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class, 'penyewaan_id');
    }

    /**
     * Get all extension history records associated with this rental transaction.
     *
     * @return HasMany<PerpanjanganSewa, $this>
     */
    public function perpanjanganSewa(): HasMany
    {
        return $this->hasMany(PerpanjanganSewa::class, 'penyewaan_id');
    }

    /**
     * Determine whether the rental contract is currently active.
     */
    public function isActive(): bool
    {
        return $this->status_sewa === StatusSewaEnum::Aktif;
    }

    /**
     * Complete the rental and release garage availability only if no other active rental exists on the same garage.
     */
    public function selesaikan(): void
    {
        $this->update(['status_sewa' => StatusSewaEnum::Selesai]);

        $hasOtherActiveRental = self::query()
            ->where('garasi_id', $this->garasi_id)
            ->where('id', '!=', $this->id)
            ->where('status_sewa', StatusSewaEnum::Aktif->value)
            ->exists();

        if (! $hasOtherActiveRental) {
            $this->garasi?->markAsAvailable();
        }
    }

    /**
     * Cancel the rental and release garage availability only if no other active rental exists on the same garage.
     */
    public function batalkan(): void
    {
        $this->update(['status_sewa' => StatusSewaEnum::Dibatalkan]);

        $hasOtherActiveRental = self::query()
            ->where('garasi_id', $this->garasi_id)
            ->where('id', '!=', $this->id)
            ->where('status_sewa', StatusSewaEnum::Aktif->value)
            ->exists();

        if (! $hasOtherActiveRental) {
            $this->garasi?->markAsAvailable();
        }
    }

    /**
     * Get the list of extension history records for this rental (UML Class Diagram method).
     *
     * @return Collection<int, PerpanjanganSewa>
     */
    public function getRiwayatPerpanjangan(): Collection
    {
        return $this->perpanjanganSewa()->orderBy('id')->get();
    }

    /**
     * Get the list of payment records for this rental (UML Class Diagram method).
     *
     * @return Collection<int, Pembayaran>
     */
    public function getRiwayatPembayaran(): Collection
    {
        return $this->pembayaran()->orderBy('id')->get();
    }

    /**
     * Get the initial new-rental payment record.
     */
    public function getBuktiBayarSewaBaru(): ?Pembayaran
    {
        return $this->pembayaran
            ->where('jenis_pembayaran', 'sewa_baru')
            ->first();
    }
}
