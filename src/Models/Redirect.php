<?php

namespace Esign\Redirects\Models;

use Esign\Redirects\Contracts\RedirectContract;
use Illuminate\Database\Eloquent\Model;

class Redirect extends Model implements RedirectContract
{
    protected $guarded = [];
    protected $casts = [
        'status_code' => 'integer',
    ];

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
}