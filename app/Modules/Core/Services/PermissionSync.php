<?php

namespace App\Modules\Core\Services;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates all permissions from config('erp.permissions') and the default
 * global roles (company_id = null) with wildcard expansion.
 */
class PermissionSync
{
    public static function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $all = config('erp.permissions');
        $existing = Permission::query()->pluck('name')->all();
        foreach (array_diff($all, $existing) as $name) {
            Permission::create(['name' => $name, 'guard_name' => 'web']);
        }

        $prev = getPermissionsTeamId();
        setPermissionsTeamId(null);
        foreach (config('erp.roles') as $roleName => $patterns) {
            $role = Role::query()->whereNull('company_id')->where('name', $roleName)->first()
                ?? Role::create(['name' => $roleName, 'guard_name' => 'web', 'company_id' => null]);
            $role->syncPermissions(self::expand($patterns, $all));
        }
        setPermissionsTeamId($prev);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public static function expand(string|array $patterns, array $all): array
    {
        if ($patterns === '*') {
            return $all;
        }
        $out = [];
        foreach ((array) $patterns as $p) {
            if (str_contains($p, '*')) {
                $regex = '/^'.str_replace('\*', '[^.]+', preg_quote($p, '/')).'$/';
                $out = array_merge($out, preg_grep($regex, $all));
            } elseif (in_array($p, $all, true)) {
                $out[] = $p;
            }
        }

        return array_values(array_unique($out));
    }
}
