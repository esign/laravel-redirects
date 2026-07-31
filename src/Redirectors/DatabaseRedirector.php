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
        $cached = $this->redirectsCache->remember(fn () =>
            $redirectModel::get()->map(fn (RedirectContract $redirect) => [
                'old_url'     => $redirect->getOldUrl(),
                'new_url'     => $redirect->getNewUrl(),
                'status_code' => $redirect->getStatusCode(),
                'constraints' => $redirect->getConstraints(),
            ])->values()->all()
        );

        // If the cache contains a stale Eloquent Collection (cached before this change),
        // bust it and re-fetch so we always work with plain arrays.
        if (! is_array($cached)) {
            $this->redirectsCache->forget();

            return $this->getRedirectsForRequest($request);
        }

        return array_map(
            fn (array $item) => new RedirectDTO(
                $item['old_url'],
                $item['new_url'],
                $item['status_code'],
                $item['constraints'],
            ),
            $cached
        );
    }
}
