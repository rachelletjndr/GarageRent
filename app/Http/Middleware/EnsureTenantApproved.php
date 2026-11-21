<?php

namespace App\Http\Middleware;

use App\Enums\RoleEnum;
use App\Enums\VerificationStatusEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantApproved
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Only enforce for penyewa role
        if ($user->role === RoleEnum::Penyewa || $user->role === RoleEnum::Penyewa->value) {
            $penyewa = $user->penyewa;

            if (! $penyewa || $penyewa->status_verifikasi !== VerificationStatusEnum::Approved) {
                return redirect()->route('tenant.verification.notice')
                    ->with('warning', 'Akun pendaftaran Anda masih menunggu verifikasi atau ditolak oleh Admin.');
            }
        }

        return $next($request);
    }
}
