<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Full backup: MySQL dump + private/public uploaded files in one zip under
 * storage/app/backups. Old backups beyond BACKUP_KEEP_DAYS are pruned.
 */
class ErpBackup extends Command
{
    protected $signature = 'erp:backup {--no-files : Database only}';

    protected $description = 'Create a full backup (database + uploaded files)';

    public function handle(): int
    {
        $dir = storage_path('app/backups');
        @mkdir($dir, 0775, true);
        $stamp = now()->format('Ymd-His');
        $sqlFile = "$dir/db-$stamp.sql";
        $c = config('database.connections.'.config('database.default'));

        if (($c['driver'] ?? '') === 'mysql' || ($c['driver'] ?? '') === 'mariadb') {
            $cmd = [config('erp.backup.mysqldump'), '--single-transaction', '--routines', '--no-tablespaces', '--default-character-set=utf8mb4',
                '-h', $c['host'], '-P', (string) $c['port'], '-u', $c['username'], $c['database'], '--result-file='.$sqlFile];
            $env = $c['password'] !== '' && $c['password'] !== null ? ['MYSQL_PWD' => $c['password']] : [];
            $p = new Process($cmd, null, $env, null, 3600);
            $p->run();
            if (! $p->isSuccessful()) {
                $this->error('mysqldump failed: '.$p->getErrorOutput());

                return self::FAILURE;
            }
        } elseif (($c['driver'] ?? '') === 'sqlite') {
            copy($c['database'], $sqlFile.'.sqlite');
            $sqlFile .= '.sqlite';
        }

        $zipPath = "$dir/backup-$stamp.zip";
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($sqlFile, basename($sqlFile));
        if (! $this->option('no-files')) {
            foreach (['attachments', 'signatures'] as $folder) {
                foreach (Storage::disk('local')->allFiles($folder) as $f) {
                    $zip->addFile(storage_path('app/'.$f), 'files/private/'.$f);
                }
            }
            foreach (Storage::disk('public')->allFiles() as $f) {
                $zip->addFile(storage_path('app/public/'.$f), 'files/public/'.$f);
            }
        }
        $zip->setArchiveComment('Garage ERP backup '.$stamp.' app='.config('app.url'));
        $zip->close();
        @unlink($sqlFile);

        // retention
        $keep = now()->subDays(config('erp.backup.keep_days'))->getTimestamp();
        foreach (glob("$dir/backup-*.zip") as $old) {
            if (filemtime($old) < $keep) {
                @unlink($old);
            }
        }
        $this->info('Backup created: '.basename($zipPath).' ('.round(filesize($zipPath) / 1048576, 2).' MB)');

        return self::SUCCESS;
    }
}
