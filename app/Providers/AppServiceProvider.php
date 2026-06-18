<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\PaymentGateway\PaymentGatewayInterface::class, function ($app) {
            return new \App\Services\PaymentGateway\TriPayGateway();
        });

        $this->app->singleton(\App\Services\Whatsapp\WhatsappMessageProvider::class, function ($app) {
            return match (strtolower((string) config('whatsapp.driver', 'fonnte'))) {
                'wablas' => new \App\Services\Whatsapp\WablasWhatsappProvider(),
                'generic' => new \App\Services\Whatsapp\GenericWhatsappProvider(),
                'log' => new \App\Services\Whatsapp\LogWhatsappProvider(),
                default => new \App\Services\Whatsapp\FonnteWhatsappProvider(),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('lot-state', function (Request $request): Limit {
            return Limit::perMinute(120)->by(
                ($request->user()?->id !== null ? 'user:'.$request->user()->id : 'guest').
                '|ip:'.$request->ip()
            );
        });

        RateLimiter::for('bid-actions', function (Request $request): Limit {
            return Limit::perMinute(30)->by(
                ($request->user()?->id !== null ? 'user:'.$request->user()->id : 'guest').
                '|ip:'.$request->ip()
            );
        });

        RateLimiter::for('payment-token', function (Request $request): Limit {
            return Limit::perMinute(20)->by(
                ($request->user()?->id !== null ? 'user:'.$request->user()->id : 'guest').
                '|ip:'.$request->ip()
            );
        });

        RateLimiter::for('payment-webhook', function (Request $request): Limit {
            return Limit::perMinute(180)->by('ip:'.$request->ip());
        });

        RateLimiter::for('otp-resend', function (Request $request): Limit {
            return Limit::perMinute(5)->by(
                ($request->user()?->id !== null ? 'user:'.$request->user()->id : 'guest').
                '|ip:'.$request->ip()
            );
        });

        RateLimiter::for('otp-verify', function (Request $request): Limit {
            return Limit::perMinute(10)->by(
                ($request->user()?->id !== null ? 'user:'.$request->user()->id : 'guest').
                '|ip:'.$request->ip()
            );
        });

        Gate::before(function ($user) {
            return $user->isSuperAdmin() ? true : null;
        });

        View::composer('layouts.app', function ($view): void {
            $user = auth()->user();

            if (! $user) {
                $view->with('headerNotifications', collect());
                $view->with('headerUnreadNotificationCount', 0);

                return;
            }

            $view->with('headerNotifications', $user->inAppNotifications()->limit(8)->get());
            $view->with('headerUnreadNotificationCount', $user->unreadInAppNotifications()->count());
        });
    }
}
