<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFirstAdminIsNeeded
{
    public function handle(Request $request, Closure $next): Response
    {
        if (User::query()->where('role', User::ROLE_ADMIN)->exists()) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
