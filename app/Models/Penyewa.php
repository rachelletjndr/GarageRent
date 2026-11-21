<?php

namespace App\Models;

use App\Enums\VerificationStatusEnum;
use Database\Factories\PenyewaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $nik
 * @property string $nama_lengkap
 * @property string $no_telp
 * @property VerificationStatusEnum $status_verifikasi
 * @property int|null $diverifikasi_oleh
 * @property Carbon|null $tanggal_verifikasi
 * @property string|null $catatan_penolakan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read User|null $verifier
 */
#[Fillable([
    'user_id',
    'nik',
    'nama_lengkap',
    'no_telp',
    'status_verifikasi',
    'diverifikasi_oleh',
    'tanggal_verifikasi',
    'catatan_penolakan',
])]
class Penyewa extends Model
{
    /** @use HasFactory<PenyewaFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'penyewa';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_verifikasi' => VerificationStatusEnum::class,
            'tanggal_verifikasi' => 'datetime',
        ];
    }

    /**
     * Get the user account that owns the tenant profile.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the admin user who verified this tenant registration.
     *
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    /**
     * Determine whether the tenant registration is approved.
     */
    public function isApproved(): bool
    {
        return $this->status_verifikasi === VerificationStatusEnum::Approved;
    }

    /**
     * Determine whether the tenant registration is still pending.
     */
    public function isPending(): bool
    {
        return $this->status_verifikasi === VerificationStatusEnum::Pending;
    }

    /**
     * Determine whether the tenant registration was rejected.
     */
    public function isRejected(): bool
    {
        return $this->status_verifikasi === VerificationStatusEnum::Rejected;
    }

    /**
     * Approve the tenant registration after KTP verification.
     */
    public function approve(User $admin): void
    {
        $this->update([
            'status_verifikasi' => VerificationStatusEnum::Approved,
            'diverifikasi_oleh' => $admin->id,
            'tanggal_verifikasi' => now(),
            'catatan_penolakan' => null,
        ]);
    }

    /**
     * Reject the tenant registration with an optional rejection note.
     */
    public function reject(User $admin, ?string $note = null): void
    {
        $this->update([
            'status_verifikasi' => VerificationStatusEnum::Rejected,
            'diverifikasi_oleh' => $admin->id,
            'tanggal_verifikasi' => now(),
            'catatan_penolakan' => $note,
        ]);
    }
}
