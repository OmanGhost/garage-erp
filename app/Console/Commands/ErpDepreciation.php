<?php

namespace App\Console\Commands;

use App\Modules\Accounting\Services\FinanceOperations;
use App\Modules\Core\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ErpDepreciation extends Command
{
    protected $signature = 'erp:depreciation {month? : Y-m, default previous month}';

    protected $description = 'Post monthly fixed asset depreciation for all companies';

    public function handle(): int
    {
        $month = $this->argument('month') ? Carbon::createFromFormat('Y-m', $this->argument('month')) : now()->subMonth();
        foreach (Company::query()->where('is_active', true)->get() as $company) {
            if (! $company->moduleEnabled('accounting')) {
                continue;
            }
            tenant()->run($company->id, function () use ($company, $month) {
                try {
                    $done = app(FinanceOperations::class)->runDepreciation($month);
                    $this->line($company->name.': '.count($done).' assets');
                } catch (\Throwable $e) {
                    $this->error($company->name.': '.$e->getMessage());
                }
            });
        }

        return self::SUCCESS;
    }
}
