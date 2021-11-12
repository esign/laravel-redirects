<?php

namespace Esign\Redirects\Tests\Models;

use Esign\Redirects\Contracts\RedirectContract;
use Illuminate\Database\Eloquent\Model;

class CustomRedirectModel extends Model implements RedirectContract
{
    protected $guarded = [];
    protected $table = 'redirects';
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
