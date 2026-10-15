<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['GET', '/users'])]
    #[TestWith(['GET', '/users/create'])]
    #[TestWith(['GET', '/users/1/edit'])]
    #[TestWith(['POST', '/users'])]
    #[TestWith(['PUT', '/users/1'])]
    public function test_guests_cannot_manage_users(string $method, string $path): void
    {
        $this->call($method, $path)->assertRedirectToRoute('login');
    }

    #[TestWith(['GET', '/users'])]
    #[TestWith(['GET', '/users/create'])]
    #[TestWith(['GET', '/users/1/edit'])]
    #[TestWith(['POST', '/users'])]
    #[TestWith(['PUT', '/users/1'])]
    public function test_regular_users_cannot_manage_users(string $method, string $path): void
    {
        $user = User::factory()->create(['id' => 1, 'is_admin' => false]);

        $this->actingAs($user)->call($method, $path)->assertForbidden();
    }

    #[TestWith(['dashboard'])]
    #[TestWith(['employees.index'])]
    #[TestWith(['office-units.index'])]
    #[TestWith(['attendance.index'])]
    #[TestWith(['schedules.index'])]
    #[TestWith(['biometric.devices.index'])]
    #[TestWith(['leaves.index'])]
    #[TestWith(['reports.index'])]
    public function test_technicians_can_view_operational_modules_without_the_user_management_menu(string $routeName): void
    {
        $technician = User::factory()->create(['is_admin' => false, 'is_active' => true]);

        $this->withoutVite()->actingAs($technician)->get(route($routeName))
            ->assertOk()->assertDontSee('Usuarios del sistema');
    }

    public function test_the_technician_role_is_available_in_user_forms_and_listings(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $technician = User::factory()->create(['is_admin' => false, 'is_active' => true]);

        $this->withoutVite()->actingAs($admin)->get(route('users.create'))->assertSee('Técnico');
        $this->get(route('users.edit', $technician))->assertSee('Técnico');
        $this->get(route('users.index'))->assertSee('Técnico');
    }

    public function test_admin_can_search_users_and_open_forms(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['name' => '<script>Example</script>', 'email' => 'find@example.com']);

        $this->actingAs($admin)->get('/users?search=find@example.com')
            ->assertSee($user->name)->assertDontSee($user->name, false)->assertDontSee($admin->email);
        $this->get('/users/create')->assertSee('Registrar usuario');
        $this->get('/users/'.$user->id.'/edit')->assertSee($user->email)->assertDontSee($user->password);
        $this->get('/users?search=absent')->assertSee('No se encontraron usuarios.');
    }

    public function test_admin_can_create_user_with_hashed_password(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/users', [
            'name' => 'Operador', 'email' => 'operator@example.com',
            'password' => 'new-password', 'password_confirmation' => 'new-password',
            'is_admin' => false, 'is_active' => true, 'last_login_at' => '2000-01-01',
        ])->assertRedirectToRoute('users.index');

        $user = User::where('email', 'operator@example.com')->sole();
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertFalse($user->is_admin);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->last_login_at);
    }

    public function test_update_preserves_blank_password_and_changes_role_and_status(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $password = $user->password;

        $this->actingAs($admin)->put('/users/'.$user->id, [
            'name' => 'Nuevo nombre', 'email' => $user->email, 'password' => '',
            'is_admin' => true, 'is_active' => false,
        ])->assertRedirectToRoute('users.index');

        $user->refresh();
        $this->assertSame('Nuevo nombre', $user->name);
        $this->assertSame($password, $user->password);
        $this->assertTrue($user->is_admin);
        $this->assertFalse($user->is_active);
    }

    public function test_admin_can_change_password(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($admin)->put('/users/'.$user->id, [
            'name' => $user->name, 'email' => $user->email,
            'password' => 'changed-password', 'password_confirmation' => 'changed-password',
            'is_admin' => false, 'is_active' => true,
        ])->assertRedirectToRoute('users.index');

        $this->assertTrue(Hash::check('changed-password', $user->fresh()->password));
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_admin_cannot_remove_own_access(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->put('/users/'.$admin->id, [
            'name' => $admin->name, 'email' => $admin->email,
            'is_admin' => false, 'is_active' => false,
        ])->assertSessionHasErrors([
            'is_admin' => 'No puedes quitarte el rol de administrador.',
            'is_active' => 'No puedes desactivar tu propia cuenta.',
        ]);

        $this->assertTrue($admin->fresh()->is_active);
        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_rejects_duplicate_email_and_unconfirmed_password(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/users', [
            'name' => 'Otro', 'email' => $admin->email, 'password' => 'long-password',
            'password_confirmation' => 'different-password', 'is_admin' => false, 'is_active' => true,
        ])->assertSessionHasErrors(['email', 'password']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_requires_valid_user_fields(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/users', [])->assertSessionHasErrors(['name', 'email', 'password', 'is_admin', 'is_active']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_inactive_account_loses_session_access(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)->get('/')->assertRedirectToRoute('login');

        $this->assertGuest();
    }

    public function test_inactive_account_cannot_log_in(): void
    {
        $user = User::factory()->create(['is_active' => false, 'password' => 'secret-password']);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password'])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
