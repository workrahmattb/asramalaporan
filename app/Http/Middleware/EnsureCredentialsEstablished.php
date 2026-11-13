<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCredentialsEstablished
{
    public function handle(Request $request, \Closure $next): Response
    {
        if ($request->user()) {
            return app(EnsureActive::class)->handle($request, $next);
        }

        return $next($request);
    }
}
