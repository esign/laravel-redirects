<?php

namespace Esign\Redirects\Contracts;

use Illuminate\Http\Request;

interface RedirectorContract {
    public function getRedirectsForRequest(Request $request): array;
}