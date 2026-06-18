<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Jobs\ProcessNotificationOutboxJob;
use App\Jobs\RunAuctionAutomationJob;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\App\Http\Middleware\ServeStaticMaintenancePage::class);

        $middleware->alias([
            'penjual' => \App\Http\Middleware\EnsurePenjual::class,
            'pembeli' => \App\Http\Middleware\EnsurePembeli::class,
            'non_superadmin' => \App\Http\Middleware\RedirectSuperAdminToAdminPanel::class,
            'onboarding.complete' => \App\Http\Middleware\EnsureOnboardingCompleted::class,
            'otp.verified' => \App\Http\Middleware\EnsureOtpVerifiedSession::class,
            'marketplace.ready' => \App\Http\Middleware\EnsureAuthenticatedMarketplaceAccess::class,
            'user.active' => \App\Http\Middleware\EnsureActiveUserStatus::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->job(new RunAuctionAutomationJob(), 'automation')
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->job(new ProcessNotificationOutboxJob(250), 'notifications')
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->command('payments:reconcile-pending --limit=50')
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->command('settlements:promote-ready --limit=100')
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->command('queue:prune-failed --hours=168')
            ->dailyAt('02:30')
            ->onOneServer();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
