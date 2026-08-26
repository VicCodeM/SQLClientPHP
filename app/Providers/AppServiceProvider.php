<?php

namespace App\Providers;

use App\Services\Ai\Contracts\GroqAiContract;
use App\Services\Ai\GroqAiService;
use App\Services\Database\DatabaseDriverManager;
use App\Services\Query\Contracts\QueryExecutionEngineContract;
use App\Services\Query\QueryExecutionEngineService;
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
        $this->app->singleton(DatabaseDriverManager::class, fn () => new DatabaseDriverManager);
        $this->app->singleton(QueryExecutionEngineContract::class, QueryExecutionEngineService::class);
        $this->app->singleton(GroqAiContract::class, GroqAiService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
