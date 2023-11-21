<?php

namespace Esign\Redirects\Routing;

use Esign\Redirects\Routing\Matching\QueryParameterAwareUriValidator;
use Illuminate\Routing\Matching\HostValidator;
use Illuminate\Routing\Matching\MethodValidator;
use Illuminate\Routing\Matching\SchemeValidator;
use Illuminate\Routing\Matching\UriValidator;
use Illuminate\Routing\Matching\ValidatorInterface;
use Illuminate\Routing\Route;

class QueryParameterAwareRoute extends Route
{
    public static function getValidators(): array
    {
        return array_map(function (ValidatorInterface $validator) {
            if ($validator instanceof UriValidator) {
                return new QueryParameterAwareUriValidator();
            }

            return $validator;
        }, parent::getValidators());
    }
}
