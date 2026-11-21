<?php

namespace App\Http\Controllers;

use App\Models\Pembayaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller serving uploaded payment receipt images (Sewa Baru & Perpanjangan Sewa)
 * with role and ownership authorization (FR-08, FR-09, NFR-SEC-03).
 */
class PaymentProofController extends Controller
{
    /**
     * Stream the payment proof image file to authorized Admin or owning Tenant.
     */
    public function __invoke(Request $request, Pembayaran $pembayaran): StreamedResponse|Response
    {
        $user = $request->user();
        $pembayaran->loadMissing('penyewaan.penyewa');

        $isOwnerTenant = $user?->penyewa !== null
            && $pembayaran->penyewaan?->penyewa_id === $user->penyewa->id;

        if (! $user?->isAdmin() && ! $isOwnerTenant) {
            abort(403, 'Anda tidak memiliki hak akses untuk melihat bukti pembayaran ini.');
        }

        $disk = Storage::disk('public');

        if (! $pembayaran->bukti_transfer || ! $disk->exists($pembayaran->bukti_transfer)) {
            abort(404, 'Berkas bukti pembayaran tidak ditemukan pada penyimpanan server.');
        }

        return $disk->response($pembayaran->bukti_transfer);
    }
}
