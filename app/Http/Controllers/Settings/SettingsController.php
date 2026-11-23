<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $branch = $request->attributes->get('branch');

        $setting = Setting::query()->firstOrCreate(
            ['branch_id' => $branch->id],
            [
                'tax_rate' => 11,
                'receipt_prefix' => 'INV',
                'printer_name' => 'EPSON TM-T82 (58mm)',
                'paper_width' => 58,
                'chars_per_line' => 32,
                'auto_open_drawer' => true,
            ]
        );

        $branches = Branch::query()->orderBy('code')->get();
        $users = $user->isOwner()
            ? User::query()->with('branches:id,code,name')->orderBy('id')->get()->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'is_active' => $u->is_active,
                'has_pin' => ! empty($u->pin),
                'branch_ids' => $u->branches->pluck('id')->values(),
                'branches' => $u->branches->pluck('code')->values(),
            ])
            : [];

        $logs = ActivityLog::query()
            ->with(['user:id,name', 'branch:id,code'])
            ->when(! $user->isOwner(), fn ($q) => $q->where('branch_id', $branch->id))
            ->latest('id')
            ->paginate(15, ['*'], 'log_page')
            ->withQueryString()
            ->through(fn (ActivityLog $l) => [
                'id' => $l->id,
                'time' => $l->created_at->format('d/m/Y H:i:s'),
                'branch' => $l->branch?->code ?? '-',
                'user' => $l->user?->name ?? 'Sistem',
                'action' => $l->action,
                'description' => $l->description,
            ]);

        return Inertia::render('settings/Index', [
            'isOwner' => $user->isOwner(),
            'setting' => $setting,
            'branches' => $branches,
            'users' => $users,
            'activityLogs' => $logs,
            'backups' => $user->isOwner() ? BackupController::listBackups() : [],
            'dbDriver' => config('database.default'),
        ]);
    }

    public function updateBranchSetting(Request $request): RedirectResponse
    {
        $branch = $request->attributes->get('branch');
        $validated = $request->validate([
            'tax_rate' => ['required', 'integer', 'min:0', 'max:100'],
            'tax_inclusive' => ['boolean'],
            'receipt_prefix' => ['required', 'string', 'max:20'],
            'printer_name' => ['required', 'string', 'max:120'],
            'paper_width' => ['required', 'integer', 'in:58,80'],
            'chars_per_line' => ['required', 'integer', 'min:24', 'max:48'],
            'auto_open_drawer' => ['boolean'],
            'receipt_header' => ['nullable', 'string', 'max:500'],
            'receipt_footer' => ['nullable', 'string', 'max:500'],
        ]);

        $setting = Setting::query()->updateOrCreate(
            ['branch_id' => $branch->id],
            $validated
        );

        ActivityLog::record(
            action: 'settings.updated',
            description: "Pengaturan struk/pajak/printer cabang {$branch->code} diperbarui",
            branchId: $branch->id,
            userId: $request->user()->id,
            subject: $setting
        );

        return back()->with('success', "Pengaturan cabang {$branch->code} berhasil disimpan.");
    }

    public function storeBranch(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:branches,code'],
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $branch = Branch::create([
            ...$validated,
            'code' => strtoupper($validated['code']),
        ]);

        Setting::create([
            'branch_id' => $branch->id,
            'tax_rate' => 11,
            'receipt_prefix' => 'INV',
            'printer_name' => 'EPSON TM-T82 (58mm)',
            'paper_width' => 58,
            'chars_per_line' => 32,
            'auto_open_drawer' => true,
            'receipt_header' => "NUSAPOS RETAIL — {$branch->code}\n{$branch->address}",
            'receipt_footer' => 'Terima kasih telah berbelanja!',
        ]);

        return back()->with('success', "Cabang {$branch->code} — {$branch->name} berhasil ditambahkan.");
    }

    public function storeUser(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'in:owner,admin,kasir,gudang'],
            'pin' => ['nullable', 'string', 'min:4', 'max:8'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
        ]);

        $newUser = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'pin' => ! empty($validated['pin']) ? Hash::make($validated['pin']) : null,
            'is_active' => true,
        ]);

        if (! empty($validated['branch_ids'])) {
            $newUser->branches()->sync($validated['branch_ids']);
        }

        ActivityLog::record(
            action: 'user.created',
            description: "Pengguna baru {$newUser->name} ({$newUser->role}) ditambahkan",
            branchId: $request->attributes->get('branch')?->id,
            userId: $request->user()->id,
            subject: $newUser
        );

        return back()->with('success', "Pengguna {$newUser->name} berhasil dibuat.");
    }

    public function updateUser(Request $request, User $managedUser): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', 'in:owner,admin,kasir,gudang'],
            'password' => ['nullable', 'string', 'min:6'],
            'pin' => ['nullable', 'string', 'min:4', 'max:8'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
        ]);

        $updateData = [
            'name' => $validated['name'],
            'role' => $validated['role'],
        ];
        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }
        if (! empty($validated['pin'])) {
            $updateData['pin'] = Hash::make($validated['pin']);
        }

        $managedUser->update($updateData);
        if ($validated['role'] !== 'owner') {
            $managedUser->branches()->sync($validated['branch_ids'] ?? []);
        }

        ActivityLog::record(
            action: 'user.updated',
            description: "Data pengguna {$managedUser->name} ({$managedUser->role}) diperbarui",
            branchId: $request->attributes->get('branch')?->id,
            userId: $request->user()->id,
            subject: $managedUser
        );

        return back()->with('success', "Data pengguna {$managedUser->name} berhasil diperbarui.");
    }

    public function toggleUserActive(Request $request, User $managedUser): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);
        if ($managedUser->id === $request->user()->id) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        $managedUser->update(['is_active' => ! $managedUser->is_active]);
        $statusLabel = $managedUser->is_active ? 'diaktifkan' : 'dinonaktifkan';

        ActivityLog::record(
            action: 'user.status_changed',
            description: "Pengguna {$managedUser->name} {$statusLabel}",
            branchId: $request->attributes->get('branch')?->id,
            userId: $request->user()->id,
            subject: $managedUser
        );

        return back()->with('success', "Pengguna {$managedUser->name} berhasil {$statusLabel}.");
    }

    public function updateBranch(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_active' => ['boolean'],
        ]);

        $branch->update($validated);

        return back()->with('success', "Cabang {$branch->code} berhasil diperbarui.");
    }
}
