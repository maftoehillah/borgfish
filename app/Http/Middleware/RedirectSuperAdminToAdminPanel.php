<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RedirectSuperAdminToAdminPanel
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check() && auth()->user()->isSuperAdmin()) {
            return redirect('/admin');
        }

        return $next($request);
    }
}
