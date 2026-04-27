<?php

namespace App\Providers\Filament;

use App\Http\Middleware\SetLocale;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class CustomerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('customer')
            ->path('/')
            ->homeUrl(url('/'))
            ->authPasswordBroker('customers')
            ->profile(isSimple: false)
            ->favicon(asset('images/favicon.ico'))
            ->brandLogo(asset('images/logo.svg'))
            ->darkMode(false)
            ->brandLogoHeight('2rem')
            ->colors([
                'primary' => [
                    50  => '243, 244, 251',
                    100 => '226, 230, 243',
                    200 => '193, 201, 232',
                    300 => '153, 164, 215',
                    400 => '79, 99, 184',
                    500 => '58, 74, 150',
                    600 => '39, 49, 115',
                    700 => '32, 40, 95',
                    800 => '23, 32, 68',
                    900 => '17, 24, 49',
                    950 => '10, 14, 30',
                ],
            ])
            ->topNavigation()
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn () => view('filament.components.language-switcher'),
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn () => view('filament.components.language-switcher'),
            )
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
                SetLocale::class,
            ])
            ->authGuard('customer');
    }
}
