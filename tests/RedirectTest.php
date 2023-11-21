<?php

namespace Esign\Redirects\Tests;

use Esign\Redirects\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;

class RedirectTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_redirect_using_plain_urls()
    {
        Redirect::create(['old_url' => 'my-old-url', 'new_url' => 'my-new-url']);

        $this
            ->get('my-old-url')
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect('my-new-url');
    }

    /** @test */
    public function it_can_redirect_using_query_parameters()
    {
        Redirect::create(['old_url' => 'my-old-url?page=2', 'new_url' => 'my-new-url', 'constraints' => ['any' => '.*']]);

        $this
            ->get('my-old-url?page=2')
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect('my-new-url');
    }

    /** @test */
    public function it_can_redirect_using_query_parameters_if_they_are_in_the_wrong_order()
    {
        Redirect::create(['old_url' => 'my-old-url?utm_source=website&page=2', 'new_url' => 'my-new-url', 'constraints' => ['any' => '.*']]);

        $this
            ->get('my-old-url?page=2')
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect('my-new-url');
    }

    /** @test */
    public function it_can_redirect_using_route_parameters()
    {
        Redirect::create(['old_url' => 'my-old-url/{slug}', 'new_url' => 'my-new-url/{slug}']);

        $this
            ->get('my-old-url/abc')
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect('my-new-url/abc');
    }

    /** @test */
    public function it_can_redirect_using_multiple_route_parameters()
    {
        Redirect::create(['old_url' => 'my-old-url/{slug}/{year}', 'new_url' => 'my-new-url/{year}/{slug}']);

        $this
            ->get('my-old-url/abc/2020')
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect('my-new-url/2020/abc');
    }

    /** @test */
    public function it_can_redirect_to_external_urls()
    {
        Redirect::create(['old_url' => 'my-old-url', 'new_url' => 'https://www.example.com']);

        $this
            ->get('my-old-url')
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect('https://www.example.com');
    }

    /** @test */
    public function it_can_redirect_using_a_custom_status_code()
    {
        Redirect::create([
            'old_url' => 'my-old-url',
            'new_url' => 'my-new-url',
            'status_code' => Response::HTTP_PERMANENTLY_REDIRECT,
        ]);

        $this
            ->get('my-old-url')
            ->assertStatus(Response::HTTP_PERMANENTLY_REDIRECT)
            ->assertRedirect('my-new-url');
    }

    /** @test */
    public function it_can_apply_constraints()
    {
        Redirect::create([
            'old_url' => 'user/{id}',
            'new_url' => 'users/{id}',
            'constraints' => ['id' => '[0-9]+'],
        ]);

        $this
            ->get('user/1')
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect('users/1');

        $this->get('user/john-doe')->assertNotFound();
    }

    /** @test */
    public function it_can_apply_nullable_constraints()
    {
        Redirect::create([
            'old_url' => 'nl/{any?}',
            'new_url' => 'nl-be/{any?}',
            'constraints' => ['any' => '.*'],
        ]);

        $this
            ->get('nl')
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect('nl-be');

        $this
            ->get('nl/esign')
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect('nl-be/esign');
    }

    /** @test */
    public function it_can_apply_constraints_matching_multiple_slashes()
    {
        Redirect::create([
            'old_url' => 'nl/{any?}',
            'new_url' => 'nl-be/{any?}',
            'constraints' => ['any' => '.*'],
        ]);

        $this
            ->get('nl/blog/my-blog-post')
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect('nl-be/blog/my-blog-post');
    }

    /** @test */
    public function it_wont_affect_existing_routes()
    {
        $this
            ->get('existing-url')
            ->assertSuccessful()
            ->assertSee('existing url');
    }

    /** @test */
    public function it_will_only_redirect_a_404_status()
    {
        $this
            ->get('status-code/418')
            ->assertStatus(418);
    }
}
