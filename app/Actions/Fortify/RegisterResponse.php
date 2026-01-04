<?php

namespace App\Actions\Fortify;

use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request)
    {
        // Logout user jika Fortify sudah auto-login
        Auth::guard('web')->logout();

        return $request->wantsJson()
            ? response()->json(['message' => 'Registration successful'])
            : redirect()->route('check-your-email');
    }
}
