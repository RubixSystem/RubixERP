<?php

namespace App\Providers;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Webkul\Security\Models\User;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Authenticatable::class, User::class);
    }

    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        FilamentAsset::register([
            Css::make('rubixerp-theme', resource_path('css/filament/theme.css')),
        ]);
    }
}
