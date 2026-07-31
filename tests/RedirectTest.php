<?php

namespace Esign\Redirects\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
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

    public static function redirectInsertionOrderProvider(): array
    {
        return [
            'parametric first, exact second' => [
                ['old_url' => 'nl/blog/{slug}', 'new_url' => 'nl/new-blog/{slug}'],
                ['old_url' => 'nl/blog/article-c', 'new_url' => 'nl/new-blog/new-article-c'],
            ],
            'exact first, parametric second' => [
                ['old_url' => 'nl/blog/article-c', 'new_url' => 'nl/new-blog/new-article-c'],
                ['old_url' => 'nl/blog/{slug}', 'new_url' => 'nl/new-blog/{slug}'],
            ],
        ];
    }

    #[Test]
    #[DataProvider('redirectInsertionOrderProvider')]
    public function it_prioritizes_exact_matches_over_parametric_redirects_regardless_of_database_order(array $first, array $second): void
    {
        Redirect::create($first);
        Redirect::create($second);

        $this->get('nl/blog/article-a')
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect('nl/new-blog/article-a');

        $this->get('nl/blog/article-b')
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect('nl/new-blog/article-b');

        $this->get('nl/blog/article-c')
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect('nl/new-blog/new-article-c');
    }

    public static function multiParameterInsertionOrderProvider(): array
    {
        return [
            'two-param first, one-param second' => [
                ['old_url' => 'nl/blog/{category}/{slug}', 'new_url' => 'nl/new-blog/{category}/{slug}'],
                ['old_url' => 'nl/blog/news/{slug}', 'new_url' => 'nl/news/{slug}'],
            ],
            'one-param first, two-param second' => [
                ['old_url' => 'nl/blog/news/{slug}', 'new_url' => 'nl/news/{slug}'],
                ['old_url' => 'nl/blog/{category}/{slug}', 'new_url' => 'nl/new-blog/{category}/{slug}'],
            ],
        ];
    }

    #[Test]
    #[DataProvider('multiParameterInsertionOrderProvider')]
    public function it_prioritizes_fewer_parameter_routes_over_more_generic_ones_regardless_of_database_order(array $first, array $second): void
    {
        Redirect::create($first);
        Redirect::create($second);

        // /nl/blog/news/{slug} is more specific and should win for news URLs
        $this->get('nl/blog/news/article-a')
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect('nl/news/article-a');

        // /nl/blog/{category}/{slug} should handle other categories
        $this->get('nl/blog/sports/article-b')
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect('nl/new-blog/sports/article-b');
    }

    public static function literalSegmentInsertionOrderProvider(): array
    {
        return [
            'generic two-param first, specific two-param second' => [
                ['old_url' => '{locale}/blog/{slug}', 'new_url' => '{locale}/new-blog/{slug}'],
                ['old_url' => 'nl/blog/{slug}', 'new_url' => 'nl/new-blog/{slug}'],
            ],
            'specific two-param first, generic two-param second' => [
                ['old_url' => 'nl/blog/{slug}', 'new_url' => 'nl/new-blog/{slug}'],
                ['old_url' => '{locale}/blog/{slug}', 'new_url' => '{locale}/new-blog/{slug}'],
            ],
        ];
    }

    #[Test]
    #[DataProvider('literalSegmentInsertionOrderProvider')]
    public function it_prioritizes_more_literal_segments_over_more_generic_ones_regardless_of_database_order(array $first, array $second): void
    {
        Redirect::create($first);
        Redirect::create($second);

        // nl/blog/{slug} has more literal segments and should win for nl URLs
        $this->get('nl/blog/article-a')
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect('nl/new-blog/article-a');

        // {locale}/blog/{slug} should handle other locales
        $this->get('fr/blog/article-a')
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect('fr/new-blog/article-a');
    }

    public static function greedyWildcardInsertionOrderProvider(): array
    {
        return [
            'greedy first, specific second' => [
                ['old_url' => 'nl/{any?}', 'new_url' => 'nl-be/{any?}', 'constraints' => ['any' => '.*']],
                ['old_url' => 'nl/blog/{slug}', 'new_url' => 'nl/new-blog/{slug}'],
            ],
            'specific first, greedy second' => [
                ['old_url' => 'nl/blog/{slug}', 'new_url' => 'nl/new-blog/{slug}'],
                ['old_url' => 'nl/{any?}', 'new_url' => 'nl-be/{any?}', 'constraints' => ['any' => '.*']],
            ],
        ];
    }

    #[Test]
    #[DataProvider('greedyWildcardInsertionOrderProvider')]
    public function it_prioritizes_specific_routes_over_greedy_wildcard_routes_regardless_of_database_order(array $first, array $second): void
    {
        Redirect::create($first);
        Redirect::create($second);

        // nl/blog/{slug} is more specific and should win over the greedy nl/{any?}
        $this->get('nl/blog/article-a')
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect('nl/new-blog/article-a');

        // nl/{any?} should still handle other paths
        $this->get('nl/contact')
            ->assertStatus(Response::HTTP_FOUND)
            ->assertRedirect('nl-be/contact');
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
