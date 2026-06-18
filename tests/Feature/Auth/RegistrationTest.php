<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_registration_redirects_to_google_oauth_with_selected_role(): void
    {
        $response = $this->post('/register', [
            'role' => 'pembeli',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('auth.google.redirect', [
            'flow' => 'register',
            'role' => 'pembeli',
        ], absolute: false));
    }
}
