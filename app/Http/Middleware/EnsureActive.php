<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActive
{
    public function handle(Request $request, \Closure $next): Response
    {
        abort_unless($request->user()?->is_active && $request->user()->canReceiveAccountMail(), 403, 'Akun belum aktif atau kredensial belum ditetapkan.');
        if ($request->user()->must_change_password && ! $request->routeIs('password.first-change', 'password.first-change.save', 'logout')) {
            return redirect()->route('password.first-change');
        }

        return $next($request);
    }
}
