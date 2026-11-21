<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\AdminVerificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controller displaying KTP verification status notice for pending or rejected tenants.
 */
class VerificationNoticeController extends Controller
{
    public function __construct(private AdminVerificationService $verificationService) {}

    /**
     * Display the tenant verification status notice page.
     */
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        abort_if(! $user, 403);

        if ($user->isAdmin() || $user->penyewa?->isApproved()) {
            return redirect()->route('dashboard');
        }

        $penyewa = $user->penyewa;
        $ktpAnalysis = $penyewa ? $this->verificationService->inspectKtpNik($penyewa->nik) : null;

        return view('tenant.verification-notice', [
            'user' => $user,
            'penyewa' => $penyewa,
            'ktpAnalysis' => $ktpAnalysis,
        ]);
    }
}
