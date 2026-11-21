<?php

use App\Http\Controllers\Admin\VerificationController;
use App\Http\Controllers\Tenant\VerificationNoticeController;
use App\Services\AdminVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth'])->group(function (): void {
    Route::get('/verification-notice', VerificationNoticeController::class)
        ->name('tenant.verification.notice');

    Route::prefix('admin')
        ->name('admin.')
        ->middleware(['role:admin'])
        ->group(function (): void {
            Route::get('/verifications', [VerificationController::class, 'index'])
                ->name('verifications.index');
            Route::patch('/verifications/{penyewa}/approve', [VerificationController::class, 'approve'])
                ->name('verifications.approve');
            Route::patch('/verifications/{penyewa}/reject', [VerificationController::class, 'reject'])
                ->name('verifications.reject');
        });
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/dashboard', function (Request $request, AdminVerificationService $verificationService) {
        $user = $request->user();
        $user?->loadMissing('penyewa');

        return view('dashboard', [
            'user' => $user,
            'penyewa' => $user?->penyewa,
            'verificationStats' => $user?->isAdmin()
                ? $verificationService->getVerificationStatistics()
                : null,
        ]);
    })->name('dashboard');
});

require __DIR__.'/settings.php';
