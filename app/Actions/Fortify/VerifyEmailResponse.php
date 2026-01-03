<?php

namespace App\Actions\Fortify;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Symfony\Component\HttpFoundation\Response;

class VerifyEmailResponse implements VerifyEmailResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request): Response
    {
        // Semua user redirect ke dashboard setelah verify email
        $redirect = route('dashboard', absolute: false).'?verified=1';

        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect($redirect);
    }
}
