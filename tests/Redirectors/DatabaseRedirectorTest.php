<?php

namespace Esign\Redirects\Tests\Redirectors;

use PHPUnit\Framework\Attributes\Test;
use Esign\Redirects\Models\Redirect;
use Esign\Redirects\Redirectors\DatabaseRedirector;
use Esign\Redirects\RedirectsCache;
use Esign\Redirects\Tests\Concerns\MakesQueryCountAssertions;
use Esign\Redirects\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class DatabaseRedirectorTest extends TestCase
{
    use RefreshDatabase;
    use MakesQueryCountAssertions;

    protected RedirectsCache $redirectsCache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->redirectsCache = app(RedirectsCache::class);
        $this->redirectsCache->forget();
    }

    #[Test]
    public function it_can_cache_redirects(): void
    {
        // Request the redirects so the database redirects get queried and cached
        // This causes the first database query
        app(DatabaseRedirector::class)->getRedirectsForRequest(request());

        // Request the redirects so the database redirects get retrieved from the cache.
        // This should not trigger a database query and leave the query count at 1.
        app(DatabaseRedirector::class)->getRedirectsForRequest(request());

        $this->assertQueryCount(1);
    }

    #[Test]
    public function it_can_clear_the_cache_when_updating_redirects(): void
    {
        // Create the database redirect, which causes the first query.
        $redirect = Redirect::create(['old_url' => 'my-old-url', 'new_url' => 'my-new-url']);

        // Request the redirects so the database redirects get queried and cached.
        // This causes the second database query.
        app(DatabaseRedirector::class)->getRedirectsForRequest(request());

        // Create a new database redirects so the cache gets busted.
        // This causes the third database query.
        $redirect->update(['new_url' => 'my-updated-url']);

        // Request the redirects so the database redirects get queried and cached once again.
        // This causes the fourth database query.
        app(DatabaseRedirector::class)->getRedirectsForRequest(request());

        $this->assertQueryCount(4);
    }

    #[Test]
    public function it_can_clear_the_cache_when_deleting_a_redirect(): void
    {
        // Create the database redirects, which causes the first query.
        $redirect = Redirect::create(['old_url' => 'my-old-url', 'new_url' => 'my-new-url']);

        // Request the redirects so the database redirects get queried and cached.
        // This causes the second database query.
        app(DatabaseRedirector::class)->getRedirectsForRequest(request());

        // Delete the database redirects so the cache gets busted.
        // This causes the third database query.
        $redirect->delete();

        // Request the redirects so the database redirects get queried and cached once again.
        // This causes the fourth database query.
        app(DatabaseRedirector::class)->getRedirectsForRequest(request());

        $this->assertQueryCount(4);
    }
}
