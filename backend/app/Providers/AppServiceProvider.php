<?php

namespace App\Providers;

use App\Contracts\GoogleWorkspaceUserDeleter;
use App\Contracts\GoogleWorkspaceUserSuspender;
use App\Services\GoogleWorkspaceDirectoryUserDeleter;
use App\Services\GoogleWorkspaceDirectoryUserSuspender;
use App\Services\NullGoogleWorkspaceUserDeleter;
use App\Services\NullGoogleWorkspaceUserSuspender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(GoogleWorkspaceUserDeleter::class, function (): GoogleWorkspaceUserDeleter {
            if (! config('google_workspace.delete_enabled')) {
                return new NullGoogleWorkspaceUserDeleter;
            }

            /** @var array<int, string> $scopes */
            $scopes = config('google_workspace.scopes');

            return new GoogleWorkspaceDirectoryUserDeleter(
                credentialsPath: (string) config('google_workspace.credentials_path'),
                impersonateEmail: (string) config('google_workspace.impersonate_email'),
                scopes: $scopes,
            );
        });

        $this->app->singleton(GoogleWorkspaceUserSuspender::class, function (): GoogleWorkspaceUserSuspender {
            if (! config('google_workspace.suspend_enabled')) {
                return new NullGoogleWorkspaceUserSuspender;
            }

            /** @var array<int, string> $scopes */
            $scopes = config('google_workspace.scopes');

            return new GoogleWorkspaceDirectoryUserSuspender(
                credentialsPath: (string) config('google_workspace.credentials_path'),
                impersonateEmail: (string) config('google_workspace.impersonate_email'),
                scopes: $scopes,
            );
        });

        if (class_exists(TelescopeApplicationServiceProvider::class)) {
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('sensitive', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });
    }
}
