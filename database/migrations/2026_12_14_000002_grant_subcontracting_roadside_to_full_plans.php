<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Subcontracting and roadside assistance are new: nobody used them before, so they are switched on only for plans that already
 * include every main module (Enterprise-like). Other plans get them when the platform admin ticks them.
 */
return new class extends Migration
{
    protected array $main = ['workshop', 'inventory', 'purchasing', 'sales', 'accounting', 'reports', 'dismantling', 'hr', 'insurance',
        'fleet', 'warranty', 'assets', 'appointments', 'portal', 'store', 'integrations'];

    protected array $grant = ['subcontracting', 'roadside'];

    public function up(): void
    {
        foreach (DB::table('plans')->get(['id', 'modules']) as $p) {
            $mods = json_decode((string) $p->modules, true);
            if (is_array($mods) && ! array_diff($this->main, $mods)) {
                DB::table('plans')->where('id', $p->id)->update(['modules' => json_encode(array_values(array_unique(array_merge($mods, $this->grant))))]);
            }
        }
    }

    public function down(): void {}
};
