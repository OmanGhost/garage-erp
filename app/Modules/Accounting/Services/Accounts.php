<?php

namespace App\Modules\Accounting\Services;

use App\Exceptions\BusinessException;
use App\Modules\Accounting\Models\Account;

/**
 * Resolves system accounts (by system_key) for the current company.
 */
class Accounts
{
    /** @var array<int,array<string,int>> */
    protected static array $cache = [];

    public static function id(string $key): int
    {
        $company = tenant()->id() ?? 0;
        if (! isset(self::$cache[$company][$key])) {
            $id = Account::query()->where('system_key', $key)->where('is_group', false)->value('id');
            if (! $id) {
                throw new BusinessException(__('System account [:key] is not configured in the chart of accounts.', ['key' => $key]));
            }
            self::$cache[$company][$key] = $id;
        }

        return self::$cache[$company][$key];
    }

    public static function get(string $key): Account
    {
        return Account::query()->findOrFail(self::id($key));
    }

    public static function flush(): void
    {
        self::$cache = [];
    }
}
