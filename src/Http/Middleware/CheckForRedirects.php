<?php

namespace Esign\Redirects\Http\Middleware;

use Closure;
use Esign\Redirects\Contracts\RedirectorContract;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Router;

class CheckForRedirects
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if (! $this->shouldRedirect($response)) {
            return $response;
        }

        return $this->attemptRedirect($request);
    }

    protected function shouldRedirect(Response $response): bool
    {
        return $response->getStatusCode() === Response::HTTP_NOT_FOUND;
    }

    protected function attemptRedirect(Request $request)
    {
        $redirects = app(RedirectorContract::class)->getRedirectsForRequest($request);
        $router = app(Router::class);
        foreach ($redirects as $redirectDTO) {
            $router->redirect(
                $redirectDTO->oldUrl,
                $redirectDTO->newUrl,
                $redirectDTO->statusCode,
            );
        }

        return $router->dispatch($request);
    }
}