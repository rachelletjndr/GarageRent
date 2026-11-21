<?php

namespace App\Models;

use App\Enums\StatusGarasiEnum;
use App\Enums\TipeGarasiEnum;
use App\Enums\TipeKendaraanEnum;
use Database\Factories\GarasiFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property TipeGarasiEnum $tipe_garasi
 * @property float $harga_sewa_harian
 * @property-read float $harga_sewa_bulanan
 * @property float|null $tarif_kwh
 * @property StatusGarasiEnum $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Penyewaan> $penyewaan
 */
#[Fillable([
    'id',
    'tipe_garasi',
    'harga_sewa_harian',
    'tarif_kwh',
    'status',
])]
class Garasi extends Model
{
    /** @use HasFactory<GarasiFactory> */
    use HasFactory;

    /**
     * Formula multiplier for fixed monthly rental price (BR-03: Bulanan = 25 x Harian).
     */
    public const int MONTHLY_PRICE_MULTIPLIER = 25;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'garasi';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipe_garasi' => TipeGarasiEnum::class,
            'harga_sewa_harian' => 'float',
            'tarif_kwh' => 'float',
            'status' => StatusGarasiEnum::class,
        ];
    }

    /**
     * Get all rental transactions allocated to this garage unit.
     *
     * @return HasMany<Penyewaan, $this>
     */
    public function penyewaan(): HasMany
    {
        return $this->hasMany(Penyewaan::class, 'garasi_id', 'id');
    }

    /**
     * Calculate the fixed monthly rental price dynamically (BR-03: 25 x daily price).
     */
    public function getHargaSewaBulanan(): float
    {
        return (float) $this->harga_sewa_harian * self::MONTHLY_PRICE_MULTIPLIER;
    }

    /**
     * Accessor for harga_sewa_bulanan so it is always consistent in PHP and SQL.
     *
     * @return Attribute<float, never>
     */
    protected function hargaSewaBulanan(): Attribute
    {
        return Attribute::make(
            get: fn (): float => $this->getHargaSewaBulanan(),
        );
    }

    /**
     * Determine whether the garage is currently available for rental.
     */
    public function isAvailable(): bool
    {
        return $this->status === StatusGarasiEnum::Available;
    }

    /**
     * Determine whether the garage is currently used.
     */
    public function isUsed(): bool
    {
        return $this->status === StatusGarasiEnum::Used;
    }

    /**
     * Determine whether this garage is an Electric Vehicle (EV) charging garage.
     */
    public function isElectricGarage(): bool
    {
        return $this->tipe_garasi === TipeGarasiEnum::MobilListrik;
    }

    /**
     * Check whether the given vehicle type is compatible with this garage type (BR-01).
     */
    public function isCompatibleWith(TipeKendaraanEnum $tipeKendaraan): bool
    {
        return match ($tipeKendaraan) {
            TipeKendaraanEnum::Sepeda, TipeKendaraanEnum::SepedaMotor => $this->tipe_garasi === TipeGarasiEnum::Sepeda,
            TipeKendaraanEnum::MobilBensin => $this->tipe_garasi === TipeGarasiEnum::Mobil,
            TipeKendaraanEnum::MobilListrik => in_array($this->tipe_garasi, [
                TipeGarasiEnum::Mobil,
                TipeGarasiEnum::MobilListrik,
            ], true),
        };
    }

    /**
     * Transition garage status to 'used' (BR-07).
     */
    public function markAsUsed(): void
    {
        $this->update(['status' => StatusGarasiEnum::Used]);
    }

    /**
     * Transition garage status to 'available' (BR-07).
     */
    public function markAsAvailable(): void
    {
        $this->update(['status' => StatusGarasiEnum::Available]);
    }
}
