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

    'cache' => [
        /**
         * The key that will be used to cache the redirects.
         */
        'key' => 'esign.laravel-redirects.redirects',

        /**
         * The duration for which database redirects will be cached.
         */
        'ttl' => \DateInterval::createFromDateString('24 hours'),

        /**
         * The cache store to be used for database redirects.
         * Use null to utilize the default cache store from the cache.php config file.
         * To disable caching, you can use the 'array' store.
         */
        'store' => null,
    ],
];
