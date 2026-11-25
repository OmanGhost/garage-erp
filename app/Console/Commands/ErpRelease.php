<?php

namespace App\Console\Commands;

use App\Support\AppVersion;
use Illuminate\Console\Command;

/**
 * Bump the application version and record the change in CHANGELOG.md.
 *
 *   php artisan erp:release patch "Fixed fuel level rounding on reception"
 *   php artisan erp:release minor "Clients page" "Excel export of clients"
 *   php artisan erp:release major "New database structure (migration required)"
 *
 * Then commit, tag and push:  git commit -am "Release vX.Y.Z" && git tag vX.Y.Z && git push --follow-tags
 */
class ErpRelease extends Command
{
    protected $signature = 'erp:release {level=patch : patch (fixes) | minor (new features) | major (breaking changes)} {notes?* : One line per change}';

    protected $description = 'Bump the version (VERSION file) and add a CHANGELOG.md entry';

    public function handle(): int
    {
        $level = $this->argument('level');
        if (! in_array($level, ['patch', 'minor', 'major'], true)) {
            $this->error('Level must be patch, minor or major.');

            return self::INVALID;
        }
        $notes = array_values(array_filter(array_map('trim', (array) $this->argument('notes'))));
        if (! $notes) {
            $this->error('Describe the change, e.g.: php artisan erp:release patch "Fixed ..."');

            return self::INVALID;
        }

        $old = AppVersion::version();
        $new = AppVersion::bump($old, $level);
        file_put_contents(base_path('VERSION'), $new.PHP_EOL);

        $file = base_path('CHANGELOG.md');
        $log = is_file($file) ? file_get_contents($file) : "# Changelog\n";
        $entry = "## [$new] - ".now()->toDateString()."\n".implode("\n", array_map(fn ($n) => '- '.$n, $notes))."\n\n";
        // insert the new entry above the most recent one (entries are newest first)
        $pos = strpos($log, "\n## [");
        $log = $pos === false ? rtrim($log)."\n\n".$entry : substr($log, 0, $pos + 1).$entry.substr($log, $pos + 1);
        file_put_contents($file, $log);

        $this->info("Version $old → $new");
        $this->line('Next: git commit -am "Release v'.$new.'" && git tag v'.$new.' && git push --follow-tags');

        return self::SUCCESS;
    }
}
