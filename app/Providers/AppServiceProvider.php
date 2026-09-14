<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Blade::if('canView', function (string $module) {
            $user = auth()->user();

            return $user && $user->canView($module);
        });

        Blade::if('canWrite', function () {
            $user = auth()->user();

            return $user && $user->canWrite();
        });

        Blade::if('admin', function () {
            $user = auth()->user();

            return $user && $user->isAdmin();
        });
    }
}
