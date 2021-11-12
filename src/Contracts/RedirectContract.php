<?php

namespace Esign\Redirects\Contracts;

interface RedirectContract
{
    public function getOldUrl(): string;

    public function getNewUrl(): string;

    public function getStatusCode(): int;
}