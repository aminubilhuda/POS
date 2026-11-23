<?php

namespace App\Http\Middleware;

use App\Models\CashShift;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOpenShift
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $branch = $request->attributes->get('branch');

        if (! $user || ! $branch) {
            return $next($request);
        }

        $openShift = CashShift::query()
            ->where('branch_id', $branch->id)
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        if (! $openShift) {
            return back()->withErrors([
                'shift' => 'Buka shift kasir terlebih dahulu sebelum memproses transaksi.',
            ]);
        }

        $request->attributes->set('cash_shift', $openShift);

        return $next($request);
    }
}
