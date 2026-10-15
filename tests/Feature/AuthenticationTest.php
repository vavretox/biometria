<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_login_form(): void
    {
        $this->withoutVite();
        $this->get('/login')->assertOk()->assertSee('Iniciar sesión');
    }

    public function test_active_user_can_log_in(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertRedirectToRoute('dashboard');

        $this->assertAuthenticatedAs($user);
    }
}
