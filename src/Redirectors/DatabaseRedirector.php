<?php

namespace Esign\Redirects\Redirectors;

use Esign\Redirects\Contracts\RedirectContract;
use Esign\Redirects\Contracts\RedirectorContract;
use Esign\Redirects\DataTransferObjects\RedirectDTO;
use Esign\Redirects\RedirectsServiceProvider;
use Illuminate\Http\Request;

class DatabaseRedirector implements RedirectorContract
{
    public function getRedirectsForRequest(Request $request): array
    {
        $redirectModel = RedirectsServiceProvider::getRedirectModel();

        return $redirectModel::get()->map(function (RedirectContract $redirect) {
            return RedirectDTO::fromRedirect($redirect);
        })->toArray();
    }
}