<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappContactLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_page_whatsapp_button_includes_authenticated_user_context(): void
    {
        $user = User::factory()->create([
            'name' => 'Sabiq Tester',
            'email' => 'sabiq.tester@example.com',
            'role' => 'pembeli',
            'whatsapp_number' => '6281234567890',
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession(['otp_verified_user_id' => $user->id])
            ->get(route('pages.contact'));

        $response->assertOk();
        $response->assertSee(urlencode('Role: Pembeli'), false);
        $response->assertSee(urlencode('Nama: Sabiq Tester'), false);
        $response->assertSee(urlencode('Email: sabiq.tester@example.com'), false);
        $response->assertSee(urlencode('No. Telepon: 6281234567890'), false);
    }
}
