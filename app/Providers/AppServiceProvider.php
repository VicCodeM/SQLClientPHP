<?php

namespace App\Providers;

use App\Services\Vault\Contracts\EncryptedVaultContract;
use App\Services\Vault\EncryptedVaultService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EncryptedVaultContract::class, EncryptedVaultService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
