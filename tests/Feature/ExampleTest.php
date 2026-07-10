<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * This backend serves no public landing page: `/` redirects straight to the login
     * screen (routes/web.php). The stock Breeze test asserted a 200 against a welcome
     * view this application does not have.
     */
    public function test_the_root_url_redirects_to_the_login_screen(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    /** ...and the screen it redirects to actually renders. */
    public function test_the_login_screen_renders(): void
    {
        $this->get(route('login'))->assertOk();
    }
}
