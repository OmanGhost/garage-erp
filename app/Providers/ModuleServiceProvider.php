<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Modular monolith loader: every folder in app/Modules can ship
 * Database/Migrations, Resources/views (namespace = lowercase module name)
 * and an optional {Module}ServiceProvider.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ($this->modules() as $name => $dir) {
            $provider = "App\\Modules\\{$name}\\{$name}ServiceProvider";
            if (class_exists($provider)) {
                $this->app->register($provider);
            }
        }
    }

    public function boot(): void
    {
        // CRUD resources (several declarative classes per module file)
        foreach ($this->modules() as $dir) {
            if (is_file($dir.'/Crud/Resources.php')) {
                require_once $dir.'/Crud/Resources.php';
            }
        }
        $resources = array_filter(get_declared_classes(), fn ($c) => is_subclass_of($c, \App\Support\Crud\CrudResource::class)
            && ! (new \ReflectionClass($c))->isAbstract());
        \App\Support\Crud\CrudRegistry::register($resources);

        foreach ($this->modules() as $name => $dir) {
            if (is_dir($dir.'/Database/Migrations')) {
                $this->loadMigrationsFrom($dir.'/Database/Migrations');
            }
            if (is_dir($dir.'/Resources/views')) {
                $this->loadViewsFrom($dir.'/Resources/views', strtolower($name));
            }
        }
    }

    /** @return array<string,string> */
    protected function modules(): array
    {
        $out = [];
        foreach (glob(app_path('Modules/*'), GLOB_ONLYDIR) ?: [] as $dir) {
            $out[basename($dir)] = $dir;
        }

        return $out;
    }
}
