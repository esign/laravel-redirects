<?php

namespace Esign\Redirects\Http\Middleware;

use Closure;
use Esign\Redirects\Contracts\RedirectorContract;
use Esign\Redirects\DataTransferObjects\RedirectDTO;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Symfony\Component\HttpFoundation\Response;

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
        $router = new Router(app(Dispatcher::class), app(Container::class));

        usort($redirects, fn ($a, $b) => $this->compareRedirects($a, $b));

        foreach ($redirects as $redirectDTO) {
            $router->redirect(
                $redirectDTO->oldUrl,
                $redirectDTO->newUrl,
                $redirectDTO->statusCode,
            )->where($redirectDTO->constraints);
        }

        return $router->dispatch($request);
    }

    protected function compareRedirects(RedirectDTO $a, RedirectDTO $b): int
    {
        // Greedy wildcards (constraints containing .*) are the least specific — always last
        $aGreedy = in_array('.*', $a->constraints) ? 1 : 0;
        $bGreedy = in_array('.*', $b->constraints) ? 1 : 0;

        if ($aGreedy !== $bGreedy) {
            return $aGreedy <=> $bGreedy;
        }

        // Fewer parameters = more specific = first
        $aParams = substr_count($a->oldUrl, '{');
        $bParams = substr_count($b->oldUrl, '{');

        if ($aParams !== $bParams) {
            return $aParams <=> $bParams;
        }

        // Tie-break: more literal segments = more specific = first
        $aLiterals = count(array_filter(explode('/', $a->oldUrl), fn ($s) => ! str_contains($s, '{')));
        $bLiterals = count(array_filter(explode('/', $b->oldUrl), fn ($s) => ! str_contains($s, '{')));

        return $bLiterals <=> $aLiterals;
    }
}
