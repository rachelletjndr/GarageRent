<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * According to SRS FR-01 & FR-02, newly registered tenants start with 'pending' status
     * and must wait for Admin KTP verification before starting an active login session.
     *
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $user = $request->user();

        if ($user && $user->isPenyewa() && $user->penyewa?->isPending()) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->wantsJson()) {
                return new JsonResponse([
                    'message' => 'Registrasi berhasil! Akun Anda sedang menunggu verifikasi KTP oleh Admin.',
                    'status_verifikasi' => 'pending',
                ], 201);
            }

            return redirect()->route('login')->with(
                'status',
                'Registrasi berhasil! Akun Anda sedang menunggu verifikasi KTP oleh Admin sebelum dapat login.',
            );
        }

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false], 201)
            : redirect()->intended(Fortify::redirects('register', route('dashboard', absolute: false)));
    }
}
