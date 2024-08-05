<?php

namespace Esign\Redirects\Models;

use Esign\Redirects\Contracts\RedirectContract;
use Esign\Redirects\RedirectsCache;
use Illuminate\Database\Eloquent\Model;

class Redirect extends Model implements RedirectContract
{
    protected $guarded = [];
    protected $casts = [
        'status_code' => 'integer',
        'constraints' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => app(RedirectsCache::class)->forget());
        static::deleted(fn () => app(RedirectsCache::class)->forget());
    }

    public function getOldUrl(): string
    {
        return $this->old_url;
    }

    public function getNewUrl(): string
    {
        return $this->new_url;
    }

    public function getStatusCode(): int
    {
        return $this->status_code;
    }

    public function getConstraints(): array
    {
        return $this->constraints ?? [];
    }
}
