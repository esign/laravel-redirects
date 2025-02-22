<?php

namespace Esign\Redirects\Tests;

use PHPUnit\Framework\Attributes\Test;
use Esign\Redirects\Exceptions\InvalidConfiguration;
use Esign\Redirects\RedirectsServiceProvider;
use Esign\Redirects\Tests\Models\CustomRedirectModel;
use Esign\Redirects\Tests\Models\InvalidRedirectModel;
use Illuminate\Support\Facades\Config;

class CustomRedirectModelTest extends TestCase
{
    #[Test]
    public function it_can_redirect_using_a_custom_model()
    {
        Config::set('redirects.redirect_model', CustomRedirectModel::class);
        CustomRedirectModel::create(['old_url' => 'my-old-url', 'new_url' => 'my-new-url']);

        $this
            ->get('my-old-url')
            ->assertRedirect('my-new-url');
    }

    #[Test]
    public function it_will_throw_an_exception_when_the_model_does_not_implement_the_redirect_contract()
    {
        Config::set('redirects.redirect_model', InvalidRedirectModel::class);
        $this->expectException(InvalidConfiguration::class);

        RedirectsServiceProvider::getRedirectModel();
    }
}
