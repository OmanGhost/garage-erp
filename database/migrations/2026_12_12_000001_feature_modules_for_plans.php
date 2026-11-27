<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The features of v1.17 – v1.20 became modules that plans switch on and off. Plans and companies that already
 * had the parent module keep the new features they were using: workshop → planning, vehicle tracking, DVI,
 * quality; inventory → inventory analytics, advanced stock operations.
 */
return new class extends Migration
{
    protected array $map = [
        'workshop' => ['planning', 'vehicle_tracking', 'dvi', 'quality'],
        'inventory' => ['inventory_analytics', 'inventory_ops'],
    ];

    protected function extend(?string $json): ?string
    {
        $mods = json_decode((string) $json, true);
        if (! is_array($mods)) {
            return $json;
        }
        foreach ($this->map as $parent => $children) {
            if (in_array($parent, $mods, true)) {
                $mods = array_values(array_unique(array_merge($mods, $children)));
            }
        }

        return json_encode($mods);
    }

    public function up(): void
    {
        foreach (DB::table('plans')->get(['id', 'modules']) as $p) {
            DB::table('plans')->where('id', $p->id)->update(['modules' => $this->extend($p->modules)]);
        }
        foreach (DB::table('companies')->whereNotNull('enabled_modules')->get(['id', 'enabled_modules']) as $c) {
            DB::table('companies')->where('id', $c->id)->update(['enabled_modules' => $this->extend($c->enabled_modules)]);
        }
    }

    public function down(): void {}
};
