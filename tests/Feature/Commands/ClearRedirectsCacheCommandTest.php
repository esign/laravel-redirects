<?php

namespace Esign\Redirects\Tests\Feature\Commands;

use PHPUnit\Framework\Attributes\Test;
use Esign\Redirects\Commands\ClearRedirectsCacheCommand;
use Esign\Redirects\Redirectors\DatabaseRedirector;
use Esign\Redirects\Tests\Concerns\MakesQueryCountAssertions;
use Esign\Redirects\Tests\TestCase;

class ClearRedirectsCacheCommandTest extends TestCase
{
    use MakesQueryCountAssertions;

    #[Test]
    public function it_can_clear_the_redirects_cache()
    {
        // Request the redirects so the database redirects get queried and cached.
        // This causes the first database query.
        app(DatabaseRedirector::class)->getRedirectsForRequest(request());

        $this->artisan(ClearRedirectsCacheCommand::class);

        // Request the redirects so the database redirects get queried and cached once again.
        // This causes the second database query.
        app(DatabaseRedirector::class)->getRedirectsForRequest(request());

        $this->assertQueryCount(2);
    }
}
