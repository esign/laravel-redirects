<?php

namespace Esign\Redirects\Redirectors;

use Esign\Redirects\Contracts\RedirectContract;
use Esign\Redirects\Contracts\RedirectorContract;
use Esign\Redirects\DataTransferObjects\RedirectDTO;
use Esign\Redirects\RedirectsCache;
use Esign\Redirects\RedirectsServiceProvider;
use Illuminate\Http\Request;

class DatabaseRedirector implements RedirectorContract
{
    public function __construct(protected RedirectsCache $redirectsCache)
    {
    }

    public function getRedirectsForRequest(Request $request): array
    {
        $redirectModel = RedirectsServiceProvider::getRedirectModel();
        $redirects = $this->redirectsCache->remember(fn () => $redirectModel::get());

        return $redirects->map(function (RedirectContract $redirect) {
            return RedirectDTO::fromRedirect($redirect);
        })->toArray();
    }
}
