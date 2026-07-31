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

        foreach ($this->sortRedirects($redirects) as $redirectDTO) {
            $router->redirect(
                $redirectDTO->oldUrl,
                $redirectDTO->newUrl,
                $redirectDTO->statusCode,
            )->where($redirectDTO->constraints);
        }

        return $router->dispatch($request);
    }

    /** @param RedirectDTO[] $redirects */
    protected function sortRedirects(array $redirects): array
    {
        // Pre-compute sort keys per redirect so string operations are not repeated
        // on every comparison, keeping the sort efficient for large redirect sets.
        $keys = array_map(fn (RedirectDTO $dto) => [
            // Greedy wildcards (.*) are least specific — always last
            in_array('.*', $dto->constraints) ? 1 : 0,
            // Fewer parameters = more specific = first
            substr_count($dto->oldUrl, '{'),
            // More literal segments = more specific = first
            -count(array_filter(
                explode('/', $dto->oldUrl),
                fn (string $segment) => ! str_contains($segment, '{'),
            )),
        ], $redirects);

        array_multisort(
            array_column($keys, 0), SORT_ASC,
            array_column($keys, 1), SORT_ASC,
            array_column($keys, 2), SORT_ASC,
            $redirects,
        );

        return $redirects;
    }
}
