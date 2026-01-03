<?php

namespace App\Actions\Fortify;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = $request->user();

        // Cek apakah email sudah diverifikasi
        if (is_null($user->email_verified_at)) {
            // Logout user yang belum verified
            Auth::guard('web')->logout();

            // Invalidate session
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Redirect ke verify email dengan pesan
            $redirect = route('verification.notice');

            return $request->wantsJson()
                ? new JsonResponse([
                    'redirect' => $redirect,
                    'status' => 'You must verify your email address before logging in.'
                ], 200)
                : redirect($redirect)->with('status', 'You must verify your email address before logging in.');
        }

        // Logic redirect berdasarkan role (yang sudah ada)
        if ($user->role === 'admin') {
            $redirect = '/dashboard';
        } else {
            $redirect = '/';
        }

        return $request->wantsJson()
            ? new JsonResponse(['redirect' => $redirect], 200)
            : redirect($redirect);
    }
}
