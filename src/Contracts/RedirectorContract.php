<?php

namespace Esign\Redirects\Contracts;

use Illuminate\Http\Request;

interface RedirectorContract
{
    /** @return \Esign\Redirects\DataTransferObjects\RedirectDTO[] */
    public function getRedirectsForRequest(Request $request): array;
}
