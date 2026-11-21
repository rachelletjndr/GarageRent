<?php

namespace App\Models;

use App\Enums\TipeKendaraanEnum;
use Database\Factories\KendaraanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $penyewa_id
 * @property string $plat_nomor
 * @property TipeKendaraanEnum $tipe_kendaraan
 * @property string $merek
 * @property float|null $kapasitas_kwh
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Penyewa $penyewa
 * @property-read Collection<int, Penyewaan> $penyewaan
 */
#[Fillable([
    'penyewa_id',
    'plat_nomor',
    'tipe_kendaraan',
    'merek',
    'kapasitas_kwh',
])]
class Kendaraan extends Model
{
    /** @use HasFactory<KendaraanFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'kendaraan';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipe_kendaraan' => TipeKendaraanEnum::class,
            'kapasitas_kwh' => 'float',
        ];
    }

    /**
     * Get the tenant who owns this vehicle.
     *
     * @return BelongsTo<Penyewa, $this>
     */
    public function penyewa(): BelongsTo
    {
        return $this->belongsTo(Penyewa::class, 'penyewa_id');
    }

    /**
     * Get the rental transactions associated with this vehicle.
     *
     * @return HasMany<Penyewaan, $this>
     */
    public function penyewaan(): HasMany
    {
        return $this->hasMany(Penyewaan::class, 'kendaraan_id');
    }

    /**
     * Determine whether this vehicle is an Electric Vehicle (EV).
     */
    public function isElectricVehicle(): bool
    {
        return $this->tipe_kendaraan === TipeKendaraanEnum::MobilListrik;
    }

    /**
     * Get the battery capacity in kWh (0.0 if non-EV).
     */
    public function getKapasitasKwh(): float
    {
        return $this->isElectricVehicle() ? (float) ($this->kapasitas_kwh ?? 0.0) : 0.0;
    }
}
