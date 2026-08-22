<?php

namespace App\Providers;

use App\Services\RickAndMorty\ResponseParser;
use App\Services\RickAndMorty\RickAndMortyClient;
use App\Services\Sync\SyncService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // El cliente de la API externa se construye una sola vez con los valores
        // de config/services.php. Quien lo necesite lo recibe por inyección.
        $this->app->singleton(RickAndMortyClient::class, function (Application $app) {
            $config = $app->make('config')->get('services.rickandmorty');

            return new RickAndMortyClient(
                parser: $app->make(ResponseParser::class),
                baseUrl: rtrim($config['base_url'], '/'),
                timeoutSeconds: $config['timeout'],
                maxAttempts: $config['max_attempts'],
                retryDelayMs: $config['retry_delay_ms'],
            );
        });

        $this->app->bind(SyncService::class, function (Application $app) {
            return new SyncService(
                client: $app->make(RickAndMortyClient::class),
                parser: $app->make(ResponseParser::class),
                delayBetweenPagesMs: $app->make('config')->get('services.rickandmorty.delay_between_pages_ms'),
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
