<?php

namespace App\Providers;

use App\Http\Responses\Auth\LogoutResponse;
use App\Services\Maps\MapsLinkResolver;
use App\Services\SerpApi\QuotaGuard;
use App\Services\SerpApi\SerpApiClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Ganti tujuan setelah logout panel Filament:
        // SSO aktif → halaman utama Portal; SSO nonaktif → login Insight.
        $this->app->bind(
            \Filament\Http\Responses\Auth\Contracts\LogoutResponse::class,
            LogoutResponse::class,
        );

        $this->app->singleton(MapsLinkResolver::class, function (): MapsLinkResolver {
            return new MapsLinkResolver(
                timeout: (int) config('serpapi.timeout', 20),
            );
        });

        $this->app->singleton(SerpApiClient::class, function (): SerpApiClient {
            return new SerpApiClient(
                apiKey: config('serpapi.key'),
                baseUrl: (string) config('serpapi.base_url'),
                engine: (string) config('serpapi.engine'),
                hl: (string) config('serpapi.hl'),
                timeout: (int) config('serpapi.timeout'),
                retry: (int) config('serpapi.retry'),
                retryDelay: (int) config('serpapi.retry_delay'),
                maxPages: (int) config('serpapi.max_pages_per_place'),
            );
        });

        $this->app->singleton(QuotaGuard::class, function (): QuotaGuard {
            return new QuotaGuard(
                dailyLimit: (int) config('serpapi.daily_search_limit'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
