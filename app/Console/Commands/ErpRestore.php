<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * php artisan erp:restore backup-20260101-020000.zip [--force]
 * Restores the database dump and uploaded files from a backup archive.
 * Put the application in maintenance mode first (php artisan down).
 */
class ErpRestore extends Command
{
    protected $signature = 'erp:restore {file : Backup zip name in storage/app/backups or absolute path} {--force} {--db-only}';

    protected $description = 'Restore database and files from a backup archive';

    public function handle(): int
    {
        $file = $this->argument('file');
        $path = is_file($file) ? $file : storage_path('app/backups/'.basename($file));
        if (! is_file($path)) {
            $this->error('Backup not found: '.$path);

            return self::FAILURE;
        }
        if (! $this->option('force') && ! $this->confirm('This will OVERWRITE the current database and files. Continue?')) {
            return self::FAILURE;
        }
        $tmp = storage_path('app/restore-'.uniqid());
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $this->error('Cannot open archive.');

            return self::FAILURE;
        }
        $zip->extractTo($tmp);
        $zip->close();

        $sql = glob($tmp.'/db-*.sql')[0] ?? null;
        $c = config('database.connections.'.config('database.default'));
        if ($sql) {
            $cmd = [config('erp.backup.mysql'), '-h', $c['host'], '-P', (string) $c['port'], '-u', $c['username'], '--default-character-set=utf8mb4', $c['database']];
            $p = new Process($cmd, null, $c['password'] ? ['MYSQL_PWD' => $c['password']] : [], fopen($sql, 'r'), 3600);
            $p->run();
            if (! $p->isSuccessful()) {
                $this->error('Database restore failed: '.$p->getErrorOutput());

                return self::FAILURE;
            }
            $this->info('Database restored.');
        }
        if (! $this->option('db-only')) {
            foreach (['private' => storage_path('app'), 'public' => storage_path('app/public')] as $kind => $target) {
                $src = "$tmp/files/$kind";
                if (! is_dir($src)) {
                    continue;
                }
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS));
                foreach ($it as $f) {
                    $rel = substr($f->getPathname(), strlen($src) + 1);
                    @mkdir(dirname("$target/$rel"), 0775, true);
                    copy($f->getPathname(), "$target/$rel");
                }
            }
            $this->info('Files restored.');
        }
        \Illuminate\Support\Facades\File::deleteDirectory($tmp);
        $this->call('optimize:clear');

        return self::SUCCESS;
    }
}
