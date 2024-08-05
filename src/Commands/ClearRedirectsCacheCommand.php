<?php

namespace Esign\Redirects\Commands;

use Esign\Redirects\RedirectsCache;
use Illuminate\Console\Command;

class ClearRedirectsCacheCommand extends Command
{
    protected $signature = 'redirects:clear-cache';
    protected $description = 'Clears the redirects cache';

    public function handle(RedirectsCache $redirectsCache): void
    {
        $redirectsCache->forget();

        $this->info('Successfully cleared the redirects cache.');
    }
}
