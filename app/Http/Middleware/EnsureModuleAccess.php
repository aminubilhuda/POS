<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();
        if (! $user || ! $user->canAccessModule($module)) {
            abort(403, 'Anda tidak memiliki hak akses ke modul ini.');
        }

        return $next($request);
    }
}
