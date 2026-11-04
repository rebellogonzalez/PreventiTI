<?php

namespace App\Providers;

use App\Models\Asset;
use App\Models\Maintenance;
use App\Models\Report;
use App\Models\User;
use App\Policies\AssetPolicy;
use App\Policies\MaintenancePolicy;
use App\Policies\ReportPolicy;
use Illuminate\Support\Facades\Gate;
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
        \Illuminate\Support\Facades\RateLimiter::for('login', fn (\Illuminate\Http\Request $request) => [
            \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by('account:'.mb_strtolower(trim((string) $request->input('email'))).'|'.$request->ip()),
            \Illuminate\Cache\RateLimiting\Limit::perMinute(30)->by('ip:'.$request->ip()),
        ]);
        Gate::policy(Asset::class, AssetPolicy::class);
        Gate::policy(Report::class, ReportPolicy::class);
        Gate::policy(Maintenance::class, MaintenancePolicy::class);

        // Gates para áreas globales
        Gate::define('access-area', function (User $user, string $area) {
            return $user->canAccessArea($area);
        });

        Gate::define('manage-infrastructure', function (User $user) {
            return $user->hasHierarchyAtLeast(2);
        });

        Gate::define('view-global-stats', function (User $user) {
            return $user->hasHierarchyAtLeast(2);
        });
    }
}
