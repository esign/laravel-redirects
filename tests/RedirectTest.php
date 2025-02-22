<?php

namespace Esign\Redirects\Tests;

use PHPUnit\Framework\Attributes\Test;
use Esign\Redirects\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;

final class RedirectTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_redirect_using_plain_urls(): void
    {
        Redirect::create(['old_url' => 'my-old-url', 'new_url' => 'my-new-url']);

        $this
            ->get('my-old-url')
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect('my-new-url');
    }

    #[Test]
    public function it_can_redirect_using_route_parameters(): void
    {
        Redirect::create(['old_url' => 'my-old-url/{slug}', 'new_url' => 'my-new-url/{slug}']);

        $this
            ->get('my-old-url/abc')
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect('my-new-url/abc');
    }

    #[Test]
    public function it_can_redirect_using_multiple_route_parameters(): void
    {
        Redirect::create(['old_url' => 'my-old-url/{slug}/{year}', 'new_url' => 'my-new-url/{year}/{slug}']);

        $this
            ->get('my-old-url/abc/2020')
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect('my-new-url/2020/abc');
    }

    #[Test]
    public function it_can_redirect_to_external_urls(): void
    {
        Redirect::create(['old_url' => 'my-old-url', 'new_url' => 'https://www.example.com']);

        $this
            ->get('my-old-url')
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect('https://www.example.com');
    }

    #[Test]
    public function it_can_redirect_using_a_custom_status_code(): void
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

    #[Test]
    public function it_can_apply_constraints(): void
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

    #[Test]
    public function it_can_apply_nullable_constraints(): void
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

    #[Test]
    public function it_can_apply_constraints_matching_multiple_slashes(): void
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

    #[Test]
    public function it_wont_affect_existing_routes(): void
    {
        $this
            ->get('existing-url')
            ->assertSuccessful()
            ->assertSee('existing url');
    }

    #[Test]
    public function it_will_only_redirect_a_404_status(): void
    {
        $this
            ->get('status-code/418')
            ->assertStatus(418);
    }
}
