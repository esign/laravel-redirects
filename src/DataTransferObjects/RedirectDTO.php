<?php

namespace Esign\Redirects\DataTransferObjects;

use Esign\Redirects\Contracts\RedirectContract;
use Illuminate\Http\Response;

class RedirectDTO
{
    public function __construct(
        public string $oldUrl,
        public string $newUrl,
        public int $statusCode = Response::HTTP_FOUND,
    ) {}

    public static function fromRedirect(RedirectContract $redirect): self
    {
        return new self(
            $redirect->getOldUrl(),
            $redirect->getNewUrl(),
            $redirect->getStatusCode(),
        );
    }
}