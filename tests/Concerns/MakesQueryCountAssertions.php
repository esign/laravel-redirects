<?php

namespace Esign\Redirects\Tests\Concerns;

use Illuminate\Database\Connection;

trait MakesQueryCountAssertions
{
    protected Connection $database;

    protected function setUpMakesQueryCountAssertions(): void
    {
        $this->database = app(Connection::class);
        $this->database->enableQueryLog();
    }

    protected function assertQueryCount(int $expectedCount): void
    {
        $this->assertCount($expectedCount, $this->database->getQueryLog());
    }
}
