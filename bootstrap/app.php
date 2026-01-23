<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\JWTAuthMiddleware;
use Illuminate\Console\Scheduling\Schedule; // Changed from Facades\Schedule
use App\Console\Commands\SendMailCommand;
use App\Http\Middleware\CheckDeviceToken;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'jwt.auth' => JWTAuthMiddleware::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'check.page.permission' => \App\Http\Middleware\CheckPagePermission::class,
            'check.device' => CheckDeviceToken::class,
        ]);
    })
    ->withCommands([
        '/var/www/html/app/Console/Commands',
    ])
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command(SendMailCommand::class)->everyMinute();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();