<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\Process;

class BackupController extends Controller
{
    public static function backupDirectory(): string
    {
        return storage_path('app/private/backups');
    }

    /**
     * @return array<int, array{name: string, size: int, size_human: string, created_at: string, driver: string}>
     */
    public static function listBackups(): array
    {
        $dir = self::backupDirectory();
        if (! is_dir($dir)) {
            return [];
        }

        return collect(File::files($dir))
            ->filter(fn ($f) => in_array($f->getExtension(), ['sql', 'sqlite', 'txt'], true))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->values()
            ->map(function ($f) {
                $bytes = $f->getSize();
                $human = $bytes >= 1048576
                    ? number_format($bytes / 1048576, 2).' MB'
                    : number_format(max(1, $bytes / 1024), 1).' KB';

                return [
                    'name' => $f->getFilename(),
                    'size' => $bytes,
                    'size_human' => $human,
                    'created_at' => date('d/m/Y H:i:s', $f->getMTime()),
                    'driver' => $f->getExtension() === 'sql' ? 'MySQL' : 'SQLite',
                ];
            })
            ->all();
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $exitCode = Artisan::call('pos:backup');
        if ($exitCode !== 0) {
            return back()->with('error', 'Backup database gagal: '.trim(Artisan::output()));
        }

        ActivityLog::record(
            action: 'system.backup_created',
            description: 'Backup database manual dibuat dari dashboard',
            branchId: $request->attributes->get('branch')?->id,
            userId: $request->user()->id
        );

        return back()->with('success', 'Backup database berhasil dibuat.');
    }

    public function download(Request $request, string $filename): BinaryFileResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $path = $this->resolveSafeBackupPath($filename);

        return response()->download($path, basename($path));
    }

    public function restore(Request $request, string $filename): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $validated = $request->validate([
            'pin' => ['required', 'string'],
        ]);

        if (! $request->user()->verifyPin($validated['pin'])) {
            return back()->withErrors(['pin' => 'PIN Owner tidak valid. Restore dibatalkan.']);
        }

        $path = $this->resolveSafeBackupPath($filename);

        return $this->performRestore($request, $path, basename($path));
    }

    public function uploadRestore(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:51200'],
            'pin' => ['required', 'string'],
        ]);

        if (! $request->user()->verifyPin($validated['pin'])) {
            return back()->withErrors(['pin' => 'PIN Owner tidak valid. Restore dibatalkan.']);
        }

        $uploaded = $request->file('file');
        $ext = strtolower($uploaded->getClientOriginalExtension());
        if (! in_array($ext, ['sql', 'sqlite'], true)) {
            return back()->with('error', 'Format file backup harus .sql atau .sqlite.');
        }

        return $this->performRestore($request, $uploaded->getRealPath(), $uploaded->getClientOriginalName());
    }

    public function destroy(Request $request, string $filename): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $path = $this->resolveSafeBackupPath($filename);
        File::delete($path);

        ActivityLog::record(
            action: 'system.backup_deleted',
            description: 'File backup dihapus: '.basename($path),
            branchId: $request->attributes->get('branch')?->id,
            userId: $request->user()->id
        );

        return back()->with('success', 'File backup berhasil dihapus.');
    }

    private function performRestore(Request $request, string $sourcePath, string $label): RedirectResponse
    {
        $connection = config('database.default');
        $dbConfig = config("database.connections.{$connection}");

        // Buat safety backup otomatis sebelum menimpa database
        Artisan::call('pos:backup');

        if ($connection === 'sqlite') {
            $target = $dbConfig['database'] ?? database_path('database.sqlite');
            if ($target !== ':memory:') {
                DB::disconnect($connection);
                File::copy($sourcePath, $target);
                DB::purge($connection);
                DB::reconnect($connection);
            }
        } elseif (in_array($connection, ['mysql', 'mariadb'], true)) {
            $sql = (string) File::get($sourcePath);
            $process = new Process([
                'mysql',
                '-h', (string) ($dbConfig['host'] ?? '127.0.0.1'),
                '-P', (string) ($dbConfig['port'] ?? '3306'),
                '-u', (string) ($dbConfig['username'] ?? 'root'),
                (string) ($dbConfig['database'] ?? ''),
            ], null, ['MYSQL_PWD' => (string) ($dbConfig['password'] ?? '')]);

            $process->setInput($sql);
            $process->setTimeout(300);
            $process->run();

            if (! $process->isSuccessful()) {
                return back()->with('error', 'Restore MySQL gagal: '.$process->getErrorOutput());
            }
        } else {
            return back()->with('error', "Restore belum didukung untuk driver [{$connection}].");
        }

        ActivityLog::record(
            action: 'system.database_restored',
            description: "Database berhasil di-restore dari {$label}",
            branchId: $request->attributes->get('branch')?->id,
            userId: $request->user()->id
        );

        return back()->with('success', "Database berhasil di-restore dari {$label}.");
    }

    private function resolveSafeBackupPath(string $filename): string
    {
        $clean = basename($filename);
        if (! preg_match('/^[A-Za-z0-9._-]+$/', $clean)) {
            abort(404);
        }

        $path = self::backupDirectory().DIRECTORY_SEPARATOR.$clean;
        if (! is_file($path)) {
            abort(404, 'File backup tidak ditemukan.');
        }

        return $path;
    }
}
