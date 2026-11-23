<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function showLogin(): Response|RedirectResponse
    {
        if (User::withTrashed()->count() === 0) {
            return redirect()->route('setup.show');
        }

        return Inertia::render('auth/Login');
    }

    public function showSetup(): Response|RedirectResponse
    {
        if (User::withTrashed()->count() > 0) {
            return redirect()->route('login');
        }

        return Inertia::render('auth/SetupOwner');
    }

    public function storeSetup(Request $request): RedirectResponse
    {
        if (User::withTrashed()->count() > 0) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'branch_code' => ['required', 'string', 'max:20'],
            'branch_name' => ['required', 'string', 'max:120'],
            'branch_address' => ['nullable', 'string', 'max:255'],
            'branch_phone' => ['nullable', 'string', 'max:40'],
            'tax_rate' => ['required', 'integer', 'min:0', 'max:100'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'pin' => ['required', 'digits_between:4,8'],
        ]);

        $result = DB::transaction(function () use ($validated) {
            if (User::withTrashed()->lockForUpdate()->count() > 0) {
                return null;
            }

            $branch = Branch::query()->firstOrCreate(
                ['code' => strtoupper($validated['branch_code'])],
                [
                    'name' => $validated['branch_name'],
                    'address' => $validated['branch_address'] ?? null,
                    'phone' => $validated['branch_phone'] ?? null,
                    'is_active' => true,
                ]
            );

            Setting::query()->firstOrCreate(
                ['branch_id' => $branch->id],
                [
                    'tax_rate' => (int) $validated['tax_rate'],
                    'tax_inclusive' => false,
                    'receipt_prefix' => 'INV',
                    'printer_name' => 'EPSON TM-T82 (58mm)',
                    'paper_width' => 58,
                    'chars_per_line' => 32,
                    'auto_open_drawer' => true,
                    'receipt_header' => strtoupper($branch->name)." — {$branch->code}\n".($branch->address ?? ''),
                    'receipt_footer' => 'Terima kasih telah berbelanja!',
                ]
            );

            // Pastikan metode pembayaran, kategori, dan satuan dasar tersedia di DB kosong
            PaymentMethod::query()->firstOrCreate(['code' => 'cash'], ['name' => 'Tunai', 'is_active' => true, 'opens_drawer' => true, 'sort_order' => 1]);
            PaymentMethod::query()->firstOrCreate(['code' => 'qris'], ['name' => 'QRIS', 'is_active' => true, 'opens_drawer' => false, 'sort_order' => 2]);
            PaymentMethod::query()->firstOrCreate(['code' => 'ewallet'], ['name' => 'E-Wallet', 'is_active' => true, 'opens_drawer' => false, 'sort_order' => 3]);
            Category::query()->firstOrCreate(['slug' => 'umum'], ['name' => 'Umum']);
            Unit::query()->firstOrCreate(['short_name' => 'pcs'], ['name' => 'Pieces']);

            $owner = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'owner',
                'pin' => Hash::make($validated['pin']),
                'is_active' => true,
            ]);

            ActivityLog::record(
                action: 'system.owner_registered',
                description: "Pendaftaran awal Owner {$owner->name} & cabang {$branch->code}",
                branchId: $branch->id,
                userId: $owner->id,
                subject: $owner
            );

            return ['owner' => $owner, 'branch' => $branch];
        });

        if (! $result) {
            return redirect()->route('login');
        }

        Auth::login($result['owner']);
        $request->session()->regenerate();
        $request->session()->put('active_branch_id', $result['branch']->id);

        return redirect()->route('pos.index')->with('success', 'Pendaftaran Owner selesai. Halaman pendaftaran kini ditutup permanen.');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Email atau kata sandi salah, atau akun tidak aktif.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user = $request->user();
        $firstBranch = $user->accessibleBranches()->first();
        if ($firstBranch) {
            $request->session()->put('active_branch_id', $firstBranch->id);
        }

        ActivityLog::record(
            action: 'auth.login',
            description: "{$user->name} ({$user->role}) masuk ke sistem",
            branchId: $firstBranch?->id,
            userId: $user->id
        );

        $defaultRoute = $user->isGudang() ? route('inventory.index') : route('pos.index');

        return redirect()->intended($defaultRoute);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function switchBranch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ]);

        $user = $request->user();
        if (! $user->canAccessBranch((int) $validated['branch_id'])) {
            abort(403, 'Anda tidak memiliki akses ke cabang tersebut.');
        }

        $branch = Branch::query()->findOrFail($validated['branch_id']);
        $request->session()->put('active_branch_id', $branch->id);

        return back()->with('success', "Berpindah ke cabang {$branch->code} — {$branch->name}.");
    }
}
