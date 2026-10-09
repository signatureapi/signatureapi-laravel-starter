<?php

namespace App\Providers;

use App\Services\SignatureApi;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SignatureApi::class, fn () => new SignatureApi(
            config('services.signatureapi.url'),
            (string) config('services.signatureapi.key'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! $this->app->runningInConsole() && blank(config('services.signatureapi.key'))) {
            throw new RuntimeException('SIGNATUREAPI_KEY is not set. Run `npx --yes signatureapi init` to write a test key to .env.');
        }
    }
}
