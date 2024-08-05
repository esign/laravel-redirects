## From v1 to v2
This package now has improved support for caching.
- A new `ClearRedirectsCacheCommand` has been added to clear the cache (`php artisan redirects:clear-cache`).
- You may now specify the cache store to be used for database redirects.
- The default cache ttl has been changed from 15 seconds to 24 hours.
- The cache will be automatically cleared when a redirect model is created, updated or deleted.

### Config changes
The cache configuration is now specified under the `cache` key in the `redirects.php` config file.    
In case you have published the configuration file, you should update it to reflect the following changes:

```diff
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

-    /**
-     * The key that will be used to cache the redirects.
-     */
-    'cache_key' => 'redirects',
-
-    /**
-     * The amount of seconds the redirects will be cached for.
-     */
-    'cache_remember' => 15,

+    'cache' => [
+        /**
+         * The key that will be used to cache the redirects.
+         */
+        'key' => 'esign.laravel-redirects.redirects',
+
+        /**
+         * The duration for which database redirects will be cached.
+         */
+        'ttl' => \DateInterval::createFromDateString('24 hours'),
+
+        /**
+         * The cache store to be used for database redirects.
+         * Use null to utilize the default cache store from the cache.php config file.
+         * To disable caching, you can use the 'array' store.
+         */
+        'store' => null,
+    ],
];
```
