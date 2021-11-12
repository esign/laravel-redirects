<?php

namespace Esign\Redirects\Tests\Redirectors;

use Closure;
use Esign\Redirects\Models\Redirect;
use Esign\Redirects\Redirectors\DatabaseRedirector;
use Esign\Redirects\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class DatabaseRedirectorTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_cache_redirects()
    {
        Redirect::create(['old_url' => 'my-old-url', 'new_url' => 'my-new-url']);
        Cache::shouldReceive('remember')
            ->once()
            ->with('redirects', 15, Closure::class)
            ->andReturn(Redirect::get());

        (new DatabaseRedirector())->getRedirectsForRequest(request());
    }

    /** @test */
    public function it_can_configure_the_cache_key()
    {
        Redirect::create(['old_url' => 'my-old-url', 'new_url' => 'my-new-url']);
        Config::set('redirects.cache_key', 'custom-redirects-key');
        Cache::shouldReceive('remember')
            ->once()
            ->with('custom-redirects-key', 15, Closure::class)
            ->andReturn(Redirect::get());

        (new DatabaseRedirector())->getRedirectsForRequest(request());
    }

    /** @test */
    public function it_can_configure_the_cache_remember()
    {
        Redirect::create(['old_url' => 'my-old-url', 'new_url' => 'my-new-url']);
        Config::set('redirects.cache_remember', 30);
        Cache::shouldReceive('remember')
            ->once()
            ->with('redirects', 30, Closure::class)
            ->andReturn(Redirect::get());

        (new DatabaseRedirector())->getRedirectsForRequest(request());
    }
}
