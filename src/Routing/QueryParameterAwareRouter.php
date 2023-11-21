<?php

namespace Esign\Redirects\Routing;

use Illuminate\Routing\Router;

class QueryParameterAwareRouter extends Router
{
    public function newRoute($methods, $uri, $action)
    {
        return (new QueryParameterAwareRoute($methods, $uri, $action))
            ->setRouter($this)
            ->setContainer($this->container);
    }
}
