<?php

namespace Esign\Redirects\Redirectors;

use Esign\Redirects\DataTransferObjects\RedirectDTO;
use Illuminate\Http\Request;

class DatabaseWildcardRedirector extends DatabaseRedirector
{
    public function getRedirectsForRequest(Request $request): array
    {
        return array_map(function (RedirectDTO $redirectDTO) {
            if (str_contains($redirectDTO->oldUrl, '*')) {
                $redirectDTO->oldUrl = str_replace('*', '{any?}', $redirectDTO->oldUrl);
                $redirectDTO->newUrl = str_replace('*', '{any?}', $redirectDTO->newUrl);
                $redirectDTO->constraints = [...$redirectDTO->constraints, 'any' => '.*'];
            }

            return $redirectDTO;
        }, parent::getRedirectsForRequest($request));
    }
}
