<?php

namespace Esign\Redirects\Tests;

use Esign\Redirects\Http\Middleware\CheckForRedirects;
use Esign\Redirects\RedirectsServiceProvider;
use Esign\Redirects\Tests\Concerns\MakesQueryCountAssertions;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRoutes();
    }

    protected function setUpTraits(): void
    {
        $uses = parent::setUpTraits();

        if (isset($uses[MakesQueryCountAssertions::class])) {
            $this->setUpMakesQueryCountAssertions();
        }
    }

    protected function getPackageProviders($app): array
    {
        return [RedirectsServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app->make(Kernel::class)->pushMiddleware(CheckForRedirects::class);
        $this->setUpDatabase();
    }

    protected function setUpRoutes(): void
    {
        Route::get('existing-url', fn () => 'existing url');
        Route::get('status-code/{code}', fn (int $code) => abort($code));
    }

    protected function setUpDatabase(): void
    {
        $migration = include __DIR__ . '/../database/migrations/create_redirects_table.php.stub';
        $migration->up();
    }
}
