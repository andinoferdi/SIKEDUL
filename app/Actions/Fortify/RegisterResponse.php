<?php

namespace App\Actions\Fortify;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Fortify;

class RegisterResponse implements RegisterResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        // Logout user yang baru register (belum verified)
        Auth::guard('web')->logout();

        // Redirect ke halaman verify email dengan pesan
        $redirect = route('verification.notice');

        return $request->wantsJson()
            ? new JsonResponse([
                'redirect' => $redirect,
                'status' => 'Registration successful! Please check your email to verify your account.'
            ], 201)
            : redirect($redirect)->with('status', 'Registration successful! Please check your email to verify your account.');
    }
}
