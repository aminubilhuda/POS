<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'pos:backup {--keep= : Jumlah hari retensi backup}';

    protected $description = 'Cadangkan database POS (MySQL atau SQLite) dan hapus backup lama secara otomatis';

    public function handle(): int
    {
        $connection = config('database.default');
        $dbConfig = config("database.connections.{$connection}");
        $backupDir = storage_path('app/private/backups');

        File::ensureDirectoryExists($backupDir);

        $timestamp = now()->format('Ymd-His');

        if ($connection === 'sqlite') {
            $source = $dbConfig['database'] ?? database_path('database.sqlite');
            if ($source === ':memory:' || ! is_file($source)) {
                $target = "{$backupDir}/pos-backup-{$timestamp}.sqlite.txt";
                File::put($target, "SQLite in-memory backup marker ({$timestamp})\n");
                $this->info("Backup marker dibuat: {$target}");
            } else {
                $target = "{$backupDir}/pos-backup-{$timestamp}.sqlite";
                File::copy($source, $target);
                $this->info("Backup SQLite berhasil: {$target}");
            }
        } elseif (in_array($connection, ['mysql', 'mariadb'], true)) {
            $target = "{$backupDir}/pos-backup-{$timestamp}.sql";
            $process = new Process([
                'mysqldump',
                '--single-transaction',
                '--quick',
                '-h', (string) ($dbConfig['host'] ?? '127.0.0.1'),
                '-P', (string) ($dbConfig['port'] ?? '3306'),
                '-u', (string) ($dbConfig['username'] ?? 'root'),
                (string) ($dbConfig['database'] ?? ''),
            ], null, ['MYSQL_PWD' => (string) ($dbConfig['password'] ?? '')]);

            $process->setTimeout(300);
            $process->run();

            if (! $process->isSuccessful()) {
                $this->error('Backup MySQL gagal: '.$process->getErrorOutput());

                return self::FAILURE;
            }

            File::put($target, $process->getOutput());
            $this->info("Backup MySQL berhasil: {$target}");
        } else {
            $this->error("Driver database [{$connection}] belum didukung oleh pos:backup.");

            return self::FAILURE;
        }

        // Hapus backup yang lebih lama dari retensi
        $keepDays = (int) ($this->option('keep') ?: config('pos.backup_retention_days', 14));
        $cutoff = now()->subDays($keepDays)->timestamp;

        foreach (File::files($backupDir) as $file) {
            if ($file->getMTime() < $cutoff) {
                File::delete($file->getPathname());
            }
        }

        return self::SUCCESS;
    }
}
