<?php

namespace App\Support;

/**
 * Application version (VERSION file, semantic versioning) and build identifier.
 * The build is APP_BUILD from the environment (set by CI / deployment) or, when the
 * code is a git checkout, the short commit hash read directly from .git (no shell).
 */
final class AppVersion
{
    protected static ?string $version = null;

    protected static ?string $build = null;

    public static function version(): string
    {
        if (self::$version === null) {
            $file = base_path('VERSION');
            self::$version = is_file($file) ? trim((string) file_get_contents($file)) : '0.0.0';
        }

        return self::$version;
    }

    public static function build(): string
    {
        if (self::$build === null) {
            self::$build = (string) (env('APP_BUILD') ?: self::gitCommit() ?: '');
        }

        return self::$build;
    }

    /** "1.2.0" or "1.2.0 (a1b2c3d)" */
    public static function label(): string
    {
        return self::version().(self::build() ? ' ('.self::build().')' : '');
    }

    protected static function gitCommit(): ?string
    {
        $git = base_path('.git');
        $head = @file_get_contents($git.'/HEAD');
        if (! $head) {
            return null;
        }
        $head = trim($head);
        if (! str_starts_with($head, 'ref: ')) {
            return substr($head, 0, 7); // detached HEAD
        }
        $ref = substr($head, 5);
        $hash = @file_get_contents($git.'/'.$ref);
        if (! $hash && is_file($git.'/packed-refs')) {
            foreach (file($git.'/packed-refs', FILE_IGNORE_NEW_LINES) as $line) {
                if (str_ends_with($line, ' '.$ref)) {
                    $hash = strtok($line, ' ');
                    break;
                }
            }
        }

        return $hash ? substr(trim($hash), 0, 7) : null;
    }

    /** Next version for a bump level. */
    public static function bump(string $current, string $level): string
    {
        [$major, $minor, $patch] = array_map('intval', array_pad(explode('.', $current), 3, 0));

        return match ($level) {
            'major' => ($major + 1).'.0.0',
            'minor' => $major.'.'.($minor + 1).'.0',
            default => $major.'.'.$minor.'.'.($patch + 1),
        };
    }
}
