<?php

namespace Esign\Redirects\Redirectors;

use Esign\Redirects\Contracts\RedirectContract;
use Esign\Redirects\Contracts\RedirectorContract;
use Esign\Redirects\DataTransferObjects\RedirectDTO;
use Esign\Redirects\RedirectsServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DatabaseRedirector implements RedirectorContract
{
    public function getRedirectsForRequest(Request $request): array
    {
        $redirectModel = RedirectsServiceProvider::getRedirectModel();
        $redirects = Cache::remember(
            config('redirects.cache_key', 'redirects'),
            config('redirects.cache_remember', 15),
            fn () => $redirectModel::get()
        );

        return $redirects->map(function (RedirectContract $redirect) {
            return RedirectDTO::fromRedirect($redirect);
        })->toArray();
    }
}
