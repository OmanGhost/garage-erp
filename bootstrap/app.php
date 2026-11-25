<?php

use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\EnsureUserType;
use App\Http\Middleware\PreventDuplicateSubmission;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetTenant::class,
            SetLocale::class,
            SecurityHeaders::class,
            PreventDuplicateSubmission::class,
        ]);
        // API: tenant & idempotency run after auth:sanctum (see routes/api.php)
        $middleware->alias([
            'tenant' => SetTenant::class,
            'idempotent' => PreventDuplicateSubmission::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'module' => EnsureModuleEnabled::class,
            'subscription' => EnsureSubscriptionActive::class,
            'usertype' => EnsureUserType::class,
        ]);
        // tenant scope must exist BEFORE implicit route-model binding resolves records
        $middleware->prependToPriorityList(\Illuminate\Routing\Middleware\SubstituteBindings::class, SetTenant::class);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
        $middleware->validateCsrfTokens(except: ['payments/webhook/*']);
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([App\Exceptions\BusinessException::class]);
    })->create();
