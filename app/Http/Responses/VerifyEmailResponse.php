<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class VerifyEmailResponse implements VerifyEmailResponseContract
{
    /**
     * @param  Request  $request
     * @return Response
     */
    public function toResponse($request)
    {
        $home = $request->user()?->homePath() ?? Fortify::redirects('email-verification');

        return $request->wantsJson()
            ? response()->json('', 204)
            : redirect()->intended($home.'?verified=1');
    }
}
