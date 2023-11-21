<?php

namespace Esign\Redirects\Routing\Matching;

use Illuminate\Http\Request;
use Illuminate\Routing\Matching\UriValidator;
use Illuminate\Routing\Route;

class QueryParameterAwareUriValidator extends UriValidator
{
    public function matches(Route $route, Request $request)
    {
        $path = rtrim($request->getRequestUri(), '/') ?: '/';

        return preg_match($route->getCompiled()->getRegex(), rawurldecode($path));
    }
}
