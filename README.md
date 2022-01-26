# Control redirects from the database

[![Latest Version on Packagist](https://img.shields.io/packagist/v/esign/laravel-redirects.svg?style=flat-square)](https://packagist.org/packages/esign/laravel-redirects)
[![Total Downloads](https://img.shields.io/packagist/dt/esign/laravel-redirects.svg?style=flat-square)](https://packagist.org/packages/esign/laravel-redirects)
[![Github Workflow Status](https://img.shields.io/github/workflow/status/esign/laravel-redirects/run-tests?label=tests)](https://github.com/esign/laravel-redirects/actions)

This package provides an easy way to load redirects from the database instead of defining them in your route files. By default the package will only load your redirects when the original request results in a 404.

## Installation

You can install the package via composer:

```bash
composer require esign/laravel-redirects
```

The package will automatically register a service provider.

For the redirects to be active you must register the `Esign\Redirects\Http\Middleware\CheckForRedirects` middleware.
```php
// app/Http/Kernel.php

protected $middleware = [
    ...
    Esign\Redirects\Http\Middleware\CheckForRedirects::class,
];
```

This package comes with a migration to store your redirects. In case you want to modify this migration you may publish it using:
```bash
php artisan vendor:publish --provider="Esign\Redirects\RedirectsServiceProvider" --tag="migrations"
```

Next up, you can publish the configuration file:
```bash
php artisan vendor:publish --provider="Esign\Redirects\RedirectsServiceProvider" --tag="config"
```

The config file will be published as `config/redirects.php` with the following content:
```php
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
```

## Usage
Defining redirects in the database is pretty straight forward:
```php
Redirect::create([
    'old_url' => 'my-old-url',
    'new_url' => 'my-new-url',
]);
```

It's also possible to define route parameters just like the way you're used to in Laravel:
```php
Redirect::create([
    'old_url' => 'my-old-url/{slug}',
    'new_url' => 'my-new-url/{slug}',
]);
```

*When using route parameters, the following parameters are reserved by Laravel and cannot be used: `destination` and `status`.*

You may even swap the order of the route parameters
```php
Redirect::create([
    'old_url' => 'my-old-url/{slug}/{year}',
    'new_url' => 'my-new-url/{year}/{slug}',
]);
```

By default a `302` status will be used, but you can also supply a custom status code
```php
Redirect::create([
    'old_url' => 'my-old-url/{slug}/{year}',
    'new_url' => 'my-new-url/{year}/{slug}',
    'status_code' => 301,
]);
```

It's also possible to redirect to external urls
```php
Redirect::create([
    'old_url' => 'my-old-url',
    'new_url' => 'https://www.esign.eu',
]);
```

This package also allows you to define [constraints](https://laravel.com/docs/routing#parameters-regular-expression-constraints) for your routes:
```php
Redirect::create([
    'old_url' => 'user/{id}',
    'new_url' => 'users/{id}',
    'constraints' => ['id' => '[0-9]+'],
]);

Redirect::create([
    'old_url' => 'nl/{any?}',
    'new_url' => 'nl-be/{any?}',
    'constraints' => ['any' => '.*'],
]);
```

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
