<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyTenantRequest;
use App\Models\Penyewa;
use App\Services\AdminVerificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controller for Admin Tenant Registration & KTP Verification Management (FR-03).
 */
class VerificationController extends Controller
{
    public function __construct(private AdminVerificationService $verificationService) {}

    /**
     * Display the list of tenant registrations and KTP verification status.
     */
    public function index(Request $request): View
    {
        $statusFilter = $request->query('status');
        $search = $request->query('search');

        $registrations = $this->verificationService->getTenantRegistrations(
            is_string($statusFilter) ? $statusFilter : null,
            is_string($search) ? $search : null,
        );

        $statistics = $this->verificationService->getVerificationStatistics();

        return view('admin.verifications.index', [
            'registrations' => $registrations,
            'statistics' => $statistics,
            'statusFilter' => is_string($statusFilter) ? $statusFilter : 'all',
            'search' => is_string($search) ? $search : '',
            'verificationService' => $this->verificationService,
        ]);
    }

    /**
     * Approve a tenant registration after verifying their 16-digit NIK KTP.
     */
    public function approve(Request $request, Penyewa $penyewa): RedirectResponse
    {
        $admin = $request->user();

        abort_if(! $admin || ! $admin->isAdmin(), 403);

        if (! $penyewa->isPending()) {
            return redirect()
                ->route('admin.verifications.index', $request->only(['status', 'search']))
                ->withErrors(['status_verifikasi' => 'Status verifikasi penyewa yang sudah diputuskan tidak dapat diubah kembali.']);
        }

        $this->verificationService->approveTenant($penyewa, $admin);

        return redirect()
            ->route('admin.verifications.index', $request->only(['status', 'search']))
            ->with('status', "Pendaftaran penyewa {$penyewa->nama_lengkap} (NIK: {$penyewa->nik}) berhasil disetujui.");
    }

    /**
     * Reject a tenant registration with an explanation note.
     */
    public function reject(VerifyTenantRequest $request, Penyewa $penyewa): RedirectResponse
    {
        $admin = $request->user();

        abort_if(! $admin || ! $admin->isAdmin(), 403);

        if (! $penyewa->isPending()) {
            return redirect()
                ->route('admin.verifications.index', $request->only(['status', 'search']))
                ->withErrors(['status_verifikasi' => 'Status verifikasi penyewa yang sudah diputuskan tidak dapat diubah kembali.']);
        }

        $this->verificationService->rejectTenant(
            $penyewa,
            $admin,
            $request->validated('catatan_penolakan'),
        );

        return redirect()
            ->route('admin.verifications.index', $request->only(['status', 'search']))
            ->with('status', "Pendaftaran penyewa {$penyewa->nama_lengkap} (NIK: {$penyewa->nik}) telah ditolak.");
    }
}
