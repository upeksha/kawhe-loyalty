<?php

namespace App\Providers\Filament;

use App\Http\Middleware\EnsureMerchantHasStore;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class MerchantPanelProvider extends ServiceProvider
{
    public function register(): void
    {
        Filament::registerPanel(fn (): Panel => $this->panel(Panel::make()));
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('merchant')
            ->path('merchant')
            ->login()
            ->emailVerification(false)
            ->spaUrlExceptions(['*scanner*'])
            ->userMenuItems([
                'profile' => Action::make('profile')
                    ->label(__('Profile'))
                    ->url(fn () => route('profile.edit'))
                    ->openUrlInNewTab()
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->sort(0),
                Action::make('billing')
                    ->label('Billing')
                    ->url(fn () => route('billing.index'))
                    ->openUrlInNewTab()
                    ->icon(Heroicon::OutlinedCreditCard)
                    ->sort(1),
            ])
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Merchant/Resources'), for: 'App\\Filament\\Merchant\\Resources')
            ->discoverPages(in: app_path('Filament/Merchant/Pages'), for: 'App\\Filament\\Merchant\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Merchant/Widgets'), for: 'App\\Filament\\Merchant\\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                EnsureMerchantHasStore::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
