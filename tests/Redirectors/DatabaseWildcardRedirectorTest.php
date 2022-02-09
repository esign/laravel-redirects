<?php

namespace Esign\Redirects\Tests\Redirectors;

use Esign\Redirects\Contracts\RedirectorContract;
use Esign\Redirects\Models\Redirect;
use Esign\Redirects\Redirectors\DatabaseWildcardRedirector;
use Esign\Redirects\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;

class DatabaseWildcardRedirectorTest extends TestCase
{
    use RefreshDatabase;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app->bind(RedirectorContract::class, DatabaseWildcardRedirector::class);
    }

    /** @test */
    public function it_can_use_wildcards()
    {
        Redirect::create([
            'old_url' => 'nl/*',
            'new_url' => 'nl-be/*',
        ]);

        $this
            ->get('nl/blog/my-blog-post')
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect('nl-be/blog/my-blog-post');
    }

    /** @test */
    public function it_can_mix_parameters_and_wilcards()
    {
        Redirect::create([
            'old_url' => 'nl/{year}/*',
            'new_url' => 'nl-be/{year}/*',
        ]);

        $this
            ->get('nl/2020/my-blog-post')
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect('nl-be/2020/my-blog-post');
    }

    /** @test */
    public function it_can_use_a_suffix_after_a_wildcard()
    {
        Redirect::create([
            'old_url' => 'nl/*/my-old-url',
            'new_url' => 'nl-be/*/my-new-url',
        ]);

        $this
            ->get('nl/blog/php/my-old-url')
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect('nl-be/blog/php/my-new-url');
    }
}
