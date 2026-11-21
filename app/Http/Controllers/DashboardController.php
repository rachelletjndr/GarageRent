<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controller rendering the unified SIM-Garasi Dashboard for Admin and Approved Tenants.
 */
class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService,
    ) {}

    /**
     * Display the integrated dashboard view or redirect unverified tenants to the verification notice page.
     */
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $user?->loadMissing('penyewa');

        if ($user && $user->isPenyewa() && $user->penyewa !== null && ! $user->penyewa->isApproved()) {
            return redirect()->route('tenant.verification.notice');
        }

        return view('dashboard', $this->dashboardService->getDashboardData($user));
    }
}
