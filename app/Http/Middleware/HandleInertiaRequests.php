<?php

namespace App\Http\Middleware;

use App\Models\Stock;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $branch = $request->attributes->get('branch');
        $accessibleBranches = $request->attributes->get('accessible_branches');

        $lowStockCount = 0;
        if ($branch) {
            $lowStockCount = Stock::query()
                ->join('product_variants', 'product_variants.id', '=', 'stocks.product_variant_id')
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->where('stocks.branch_id', $branch->id)
                ->where('products.is_bundle', false)
                ->whereColumn('stocks.quantity', '<=', 'product_variants.min_stock')
                ->count();
        }

        return [
            ...parent::share($request),
            'appName' => config('app.name', 'NusaPOS Retail'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'initials' => $user->initials(),
                    'modules' => [
                        'pos' => $user->canAccessModule('pos'),
                        'products' => $user->canAccessModule('products'),
                        'inventory' => $user->canAccessModule('inventory'),
                        'purchasing' => $user->canAccessModule('purchasing'),
                        'reports' => $user->canAccessModule('reports'),
                        'settings' => $user->canAccessModule('settings'),
                    ],
                ] : null,
            ],
            'activeBranch' => $branch ? [
                'id' => $branch->id,
                'code' => $branch->code,
                'name' => $branch->name,
                'address' => $branch->address,
                'phone' => $branch->phone,
            ] : null,
            'accessibleBranches' => $accessibleBranches
                ? $accessibleBranches->map(fn ($b) => [
                    'id' => $b->id,
                    'code' => $b->code,
                    'name' => $b->name,
                ])->values()
                : [],
            'lowStockCount' => $lowStockCount,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'receipt' => fn () => $request->session()->get('receipt'),
            ],
        ];
    }
}
