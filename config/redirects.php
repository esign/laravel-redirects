<?php

return [
    /**
     * This is the model used by the DatabaseRedirector.
     * It should implement the RedirectContract interface and extend the Model class.
     */
    'redirect_model' => Esign\Redirects\Models\Redirect::class,

    /**
     * This class provides the redicect url's to the CheckForRedirects middleware.
     * It should implement the RedirectorContract interface.
     */
    'redirector' => Esign\Redirects\Redirectors\DatabaseRedirector::class,

    /**
     * The key that will be used to cache the redirects.
     */
    'cache_key' => 'redirects',

    /**
     * The amount of seconds the redirects will be cached for.
     */
    'cache_remember' => 15,
];
