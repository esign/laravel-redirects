<?php

namespace Esign\Redirects\Http\Middleware;

use Esign\Redirects\Contracts\RedirectorContract;
use Esign\Redirects\Routing\QueryParameterAwareRouter;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;

class CheckForRedirectsIncludingQueryParameters extends CheckForRedirects
{
    protected function attemptRedirect(Request $request)
    {
        $redirects = app(RedirectorContract::class)->getRedirectsForRequest($request);
        $router = new QueryParameterAwareRouter(app(Dispatcher::class), app(Container::class));
        foreach ($redirects as $redirectDTO) {
            $router->redirect(
                $redirectDTO->oldUrl,
                $redirectDTO->newUrl,
                $redirectDTO->statusCode,
            )->where($redirectDTO->constraints);
        }

        return $router->dispatch($request);
    }
}
