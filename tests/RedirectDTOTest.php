<?php

namespace Esign\Redirects\Tests;

use PHPUnit\Framework\Attributes\Test;
use Esign\Redirects\DataTransferObjects\RedirectDTO;
use Esign\Redirects\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;

final class RedirectDTOTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_from_a_redirect(): void
    {
        $redirect = Redirect::create([
            'old_url' => 'my-old-url',
            'new_url' => 'my-new-url',
            'status_code' => Response::HTTP_I_AM_A_TEAPOT,
        ]);

        $redirectDto = RedirectDTO::fromRedirect($redirect);

        $this->assertEquals('my-old-url', $redirectDto->oldUrl);
        $this->assertEquals('my-new-url', $redirectDto->newUrl);
        $this->assertEquals(Response::HTTP_I_AM_A_TEAPOT, $redirectDto->statusCode);
    }
}
