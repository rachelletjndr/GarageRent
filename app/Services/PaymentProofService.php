<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Service handling secure validation, storage, sanitization, and cleanup of payment proof receipt images (FR-08 & NFR-SEC-04).
 */
class PaymentProofService
{
    /**
     * Allowed image extensions for payment proofs (FR-08, NFR-SEC-04).
     *
     * @var array<int, string>
     */
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png'];

    /**
     * Allowed MIME types for payment proofs (NFR-SEC-04).
     *
     * @var array<int, string>
     */
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/jpg'];

    /**
     * Maximum file size in bytes (2 MB = 2048 KB).
     */
    private const MAX_FILE_SIZE_BYTES = 2048 * 1024;

    /**
     * Validate and store an uploaded payment proof receipt image securely on the public storage disk.
     */
    public function storePaymentProof(UploadedFile $file, string $prefix = 'proof_trx'): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        $mimeType = strtolower((string) $file->getMimeType());
        $sizeInBytes = (int) $file->getSize();

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true) || ! in_array($mimeType, self::ALLOWED_MIMES, true)) {
            throw new InvalidArgumentException('Bukti pembayaran wajib berupa berkas gambar JPG/PNG maksimal 2MB.');
        }

        if ($sizeInBytes > self::MAX_FILE_SIZE_BYTES) {
            throw new InvalidArgumentException('Ukuran berkas bukti pembayaran melebihi batas maksimal 2MB.');
        }

        $filename = sprintf(
            '%s_%s_%s.%s',
            $prefix,
            now()->format('YmdHis'),
            Str::lower(Str::random(10)),
            $extension,
        );

        $storedPath = $file->storeAs('payments', $filename, 'public');

        return is_string($storedPath) ? $storedPath : "payments/{$filename}";
    }

    /**
     * Delete a stored payment proof file during transaction rollback or cleanup.
     */
    public function deletePaymentProof(?string $path): void
    {
        if ($path !== null && $path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
