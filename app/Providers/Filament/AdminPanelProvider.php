<?php

namespace App\Providers\Filament;

use App\Filament\Support\InitialsAvatarProvider;
use App\Http\Middleware\EnsureEntitySelected;
use App\Http\Middleware\EnsurePasswordNotExpired;
use App\Http\Middleware\IdleTimeout;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The PACE admin panel. Sign-in happens in the main app (email + password +
 * entity), so the panel has no login page of its own; it works in the same
 * current entity as the rest of the session.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('PACE Admin')
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->colors([
                'primary' => Color::hex('#4f5db8'),
                'gray' => Color::Slate,
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
            ->widgets([])
            ->navigationGroups([
                NavigationGroup::make(__('Access')),
                NavigationGroup::make(__('Master data')),
                NavigationGroup::make(__('Organisation')),
                NavigationGroup::make(__('System')),
            ])
            ->navigationItems([
                NavigationItem::make(__('Back to PACE'))
                    ->url(fn (): string => route('dashboard'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->sort(-10),
            ])
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn () => view('filament.current-entity'))
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
            // Entity first: canAccessPanel() (run by Authenticate) needs the permission team set.
            // Both middleware let guests through, and Authenticate then redirects them to sign in.
            ->authMiddleware([
                IdleTimeout::class,
                EnsureEntitySelected::class,
                Authenticate::class,
                EnsurePasswordNotExpired::class,
            ], isPersistent: true);
    }
}
