<?php

namespace App\Providers\Filament;

use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->authGuard('web')
            ->brandName('Borgfish Admin')
            ->brandLogo(asset('images/borgfish.png'))
            ->favicon(asset('images/borgfish.png'))
            ->login(fn (): RedirectResponse => redirect()->route('login'))
            ->profile(isSimple: false)
            ->userMenuItems([
                'marketplace' => fn (): Action => Action::make('marketplace')
                    ->label('Kembali ke Marketplace')
                    ->icon('heroicon-o-globe-alt')
                    ->url(fn (): string => route('ikans.index')),
                'notifications' => fn (): Action => Action::make('notifications')
                    ->label(function (): string {
                        $admin = auth()->user();
                        $count = $admin ? $admin->unreadInAppNotifications()->count() : 0;

                        return $count > 0 ? "Notifikasi ({$count})" : 'Notifikasi';
                    })
                    ->icon('heroicon-o-bell-alert')
                    ->url(fn (): string => url('/admin/notifikasi')),
                'profile' => fn (Action $action): Action => $action->label('Profile Admin'),
            ])
            ->colors([
                'primary' => Color::Teal,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                \App\Filament\Widgets\StatsOverviewWidget::class,
                \App\Filament\Widgets\AdminHealthCheckWidget::class,
                \App\Filament\Widgets\LiveBidFeedWidget::class,
                \App\Filament\Widgets\UrgentAuctionsWidget::class,
                \App\Filament\Widgets\ActionRequiredTransactionsWidget::class,
                \App\Filament\Widgets\SellerSettlementOverviewWidget::class,
                \App\Filament\Widgets\SellerSettlementActionRequiredWidget::class,
                \App\Filament\Widgets\SellerSettlementOutstandingBySellerWidget::class,
                \App\Filament\Widgets\SellerSettlementPaidBySellerThisMonthWidget::class,
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
