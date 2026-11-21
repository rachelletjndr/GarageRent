<?php

use App\Http\Controllers\Admin\ExtensionController;
use App\Http\Controllers\Admin\GarageManagementController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\VerificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentProofController;
use App\Http\Controllers\Tenant\GarageCatalogController;
use App\Http\Controllers\Tenant\RentalBookingController;
use App\Http\Controllers\Tenant\VerificationNoticeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth'])->group(function (): void {
    Route::get('/verification-notice', VerificationNoticeController::class)
        ->name('tenant.verification.notice');

    Route::get('/payments/{pembayaran}/proof', PaymentProofController::class)
        ->name('payments.proof');

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

            Route::get('/garages', [GarageManagementController::class, 'index'])
                ->name('garages.index');
            Route::post('/garages', [GarageManagementController::class, 'store'])
                ->name('garages.store');
            Route::patch('/garages/{garasi}', [GarageManagementController::class, 'update'])
                ->name('garages.update');

            Route::get('/extensions', [ExtensionController::class, 'index'])
                ->name('extensions.index');
            Route::post('/extensions', [ExtensionController::class, 'store'])
                ->name('extensions.store');

            Route::get('/reports', [ReportController::class, 'index'])
                ->name('reports.index');
        });

    Route::prefix('tenant')
        ->name('tenant.')
        ->middleware(['role:penyewa', 'tenant.approved'])
        ->group(function (): void {
            Route::get('/garages', [GarageCatalogController::class, 'index'])
                ->name('garages.index');

            Route::get('/rentals', [RentalBookingController::class, 'index'])
                ->name('rentals.index');
            Route::get('/rentals/create', [RentalBookingController::class, 'create'])
                ->name('rentals.create');
            Route::post('/rentals', [RentalBookingController::class, 'store'])
                ->name('rentals.store');
        });
});

Route::middleware(['auth'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});

require __DIR__.'/settings.php';
