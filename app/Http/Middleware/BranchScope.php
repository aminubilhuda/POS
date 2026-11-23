<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BranchScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $accessible = $user->accessibleBranches();
        if ($accessible->isEmpty()) {
            abort(403, 'Akun Anda belum ditugaskan ke cabang manapun.');
        }

        $sessionBranchId = (int) $request->session()->get('active_branch_id');
        $activeBranch = $accessible->firstWhere('id', $sessionBranchId) ?? $accessible->first();

        $request->session()->put('active_branch_id', $activeBranch->id);
        $request->attributes->set('branch', $activeBranch);
        $request->attributes->set('accessible_branches', $accessible);

        return $next($request);
    }
}
