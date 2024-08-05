<?php

namespace Esign\Redirects;

use Closure;
use DateInterval;
use Illuminate\Cache\Repository;

class RedirectsCache
{
    public function __construct(
        protected Repository $store,
        protected string $key,
        protected DateInterval $ttl
    ) {
    }

    public function remember(Closure $callback): mixed
    {
        return $this->store->remember($this->key, $this->ttl, $callback);
    }

    public function forget(): bool
    {
        return $this->store->forget($this->key);
    }
}
