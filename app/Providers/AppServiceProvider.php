<?php

namespace App\Providers;

use App\Services\Auth\ApiTokenService;
use App\Services\RickAndMorty\ResponseParser;
use App\Services\RickAndMorty\RickAndMortyClient;
use App\Services\Sync\SyncService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->app->singleton(ApiTokenService::class, function (Application $app) {
            return new ApiTokenService(
                lifetimeDays: $app->make('config')->get('auth.api_token.lifetime_days'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerApiTokenGuard();
        $this->registerRateLimiters();
    }

    /**
     * Driver de autenticación "api-token" (ver config/auth.php). Laravel llama
     * a esta función en cada petición protegida; si devuelve un usuario, la
     * petición queda autenticada; si devuelve null, responde 401.
     */
    private function registerApiTokenGuard(): void
    {
        Auth::viaRequest('api-token', function (Request $request) {
            return $this->app->make(ApiTokenService::class)->userFromToken($request->bearerToken());
        });
    }

    /**
     * Límites de peticiones. El de "login" es más estricto para frenar
     * intentos de adivinar contraseñas.
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
