<?php

namespace Esign\Redirects;

use Esign\Redirects\Commands\ClearRedirectsCacheCommand;
use Esign\Redirects\Contracts\RedirectContract;
use Esign\Redirects\Contracts\RedirectorContract;
use Esign\Redirects\Exceptions\InvalidConfiguration;
use Esign\Redirects\Models\Redirect;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class RedirectsServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ClearRedirectsCacheCommand::class,
            ]);

            $this->publishes([
                $this->configPath() => config_path('redirects.php'),
            ], 'config');

            $this->publishes([
                $this->migrationPath() => database_path('migrations/' . date('Y_m_d_His', time()) . '_create_redirects_table.php'),
            ], 'migrations');
        }
    }

    public function register()
    {
        $this->mergeConfigFrom($this->configPath(), 'redirects');
        $this->registerRedirectsCache();
        $this->app->bind(RedirectorContract::class, config('redirects.redirector'));
    }

    protected function configPath(): string
    {
        return __DIR__ . '/../config/redirects.php';
    }

    protected function migrationPath(): string
    {
        return __DIR__ . '/../database/migrations/create_redirects_table.php.stub';
    }

    public static function getRedirectModel(): string
    {
        $redirectModel = config('redirects.redirect_model', Redirect::class);

        if (! is_a($redirectModel, RedirectContract::class, true) || ! is_a($redirectModel, Model::class, true)) {
            throw InvalidConfiguration::invalidModel($redirectModel);
        }

        return $redirectModel;
    }

    protected function registerRedirectsCache(): void
    {
        $this->app->bind(RedirectsCache::class, function (Application $app) {
            $cacheManager = $app->make(CacheManager::class);

            return new RedirectsCache(
                $cacheManager->store(config('redirects.cache.store')),
                config('redirects.cache.key'),
                config('redirects.cache.ttl'),
            );
        });
    }
}
