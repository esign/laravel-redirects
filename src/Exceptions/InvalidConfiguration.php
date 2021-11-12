<?php

namespace Esign\Redirects\Exceptions;

use Esign\Redirects\Contracts\RedirectContract;
use Exception;
use Illuminate\Database\Eloquent\Model;

class InvalidConfiguration extends Exception
{
    public static function invalidModel(string $className): self
    {
        return new static(sprintf(
            'The configured model class `%s` does not implement the `%s` interface or does not extend the `%s` class.',
            $className,
            RedirectContract::class,
            Model::class,
        ));
    }
}
