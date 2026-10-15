<?php

namespace Tests\Feature;

use App\Http\Controllers\Biometric\BiometricDeviceController;
use App\Models\Biometric\BiometricDevice;
use App\Models\User;
use App\Services\Biometric\BiometricDeviceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Mockery\MockInterface;
use Tests\TestCase;

class BiometricDeviceUsersTest extends TestCase
{
    public function test_regular_user_cannot_list_or_delete_users_on_a_clock(): void
    {
        $this->bindDevice();
        $this->actingAs(User::factory()->make(['id' => 1, 'is_admin' => false, 'is_active' => true]));
        $this->mock(BiometricDeviceService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('getUsers');
            $mock->shouldNotReceive('deleteUser');
        });

        $this->getJson(route('biometric.devices.users', 1))->assertForbidden();
        $this->deleteJson(route('biometric.devices.users.destroy', 1), ['user_ids' => [1], 'confirmed' => true])->assertForbidden();
    }

    public function test_deletion_requires_selection_and_explicit_confirmation(): void
    {
        $this->bindDevice();
        $this->actingAs(User::factory()->make(['id' => 1, 'is_admin' => true, 'is_active' => true]));
        $this->mock(BiometricDeviceService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('deleteUser');
        });

        $this->deleteJson(route('biometric.devices.users.destroy', 1), [])
            ->assertUnprocessable()->assertJsonValidationErrors(['user_ids', 'confirmed']);
        $this->deleteJson(route('biometric.devices.users.destroy', 1), ['user_ids' => [1], 'confirmed' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('confirmed');
    }

    public function test_unlisted_users_and_clock_administrators_cannot_be_deleted(): void
    {
        $this->bindDevice();
        $this->actingAs(User::factory()->make(['id' => 1, 'is_admin' => true, 'is_active' => true]));
        $this->mock(BiometricDeviceService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('getUsers');
            $mock->shouldNotReceive('deleteUser');
        });

        $this->deleteJson(route('biometric.devices.users.destroy', 1), ['user_ids' => [9], 'confirmed' => true])->assertUnprocessable();
        $this->withSession(['biometric_user_selection' => [1 => [9 => ['uid' => 9, 'userid' => '9', 'name' => 'Administrador', 'role' => 14]]]])
            ->deleteJson(route('biometric.devices.users.destroy', 1), ['user_ids' => [9], 'confirmed' => true])->assertUnprocessable();
    }

    public function test_selected_user_is_deleted_and_removed_from_the_session_snapshot(): void
    {
        $user = ['uid' => 42, 'userid' => 'EMP42', 'name' => 'Ana Pérez', 'role' => 0];
        $request = $this->deletionRequest($user);
        $device = (new BiometricDevice)->forceFill(['id' => 1, 'protocol' => 'ZKProtocol']);
        $this->mock(BiometricDeviceService::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('getUsers')->once()->with(1)->andReturn([$user]);
            $mock->shouldReceive('deleteUser')->once()->with(1, 42)->andReturnTrue();
            $mock->shouldReceive('disconnect')->once()->andReturnTrue();
        });

        $response = app(BiometricDeviceController::class)->deleteDeviceUsers($device, $request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([42], $response->getData(true)['deleted']);
        $this->assertSame([], $request->session()->get('biometric_user_selection.1'));
    }

    public function test_changed_user_identity_cannot_be_deleted(): void
    {
        $user = ['uid' => 42, 'userid' => 'EMP42', 'name' => 'Ana Pérez', 'role' => 0];
        $request = $this->deletionRequest($user);
        $device = (new BiometricDevice)->forceFill(['id' => 1, 'protocol' => 'ZKProtocol']);
        $this->mock(BiometricDeviceService::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('getUsers')->once()->with(1)->andReturn([[...$user, 'userid' => 'NEW42']]);
            $mock->shouldNotReceive('deleteUser');
            $mock->shouldReceive('disconnect')->once()->andReturnTrue();
        });

        $response = app(BiometricDeviceController::class)->deleteDeviceUsers($device, $request);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame([42 => $user], $request->session()->get('biometric_user_selection.1'));
    }

    public function test_device_list_hides_credentials_and_keeps_a_selection_snapshot(): void
    {
        $user = ['uid' => 42, 'userid' => 'EMP42', 'name' => 'Ana Pérez', 'role' => 0];
        $request = $this->deletionRequest($user);
        $device = (new BiometricDevice)->forceFill(['id' => 1, 'protocol' => 'ZKProtocol']);
        $this->mock(BiometricDeviceService::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('getUsers')->once()->with(1)->andReturn([[...$user, 'password' => 'secret']]);
            $mock->shouldReceive('disconnect')->once()->andReturnTrue();
        });

        $response = app(BiometricDeviceController::class)->deviceUsers($device, $request);

        $this->assertSame(['users' => [$user]], $response->getData(true));
        $this->assertSame([42 => $user], $request->session()->get('biometric_user_selection.1'));
    }

    public function test_partial_failure_reports_deleted_users_and_keeps_remaining_selection(): void
    {
        $first = ['uid' => 42, 'userid' => 'EMP42', 'name' => 'Ana Pérez', 'role' => 0];
        $second = ['uid' => 43, 'userid' => 'EMP43', 'name' => 'Luis Pérez', 'role' => 0];
        $request = $this->deletionRequest($first);
        $request->merge(['user_ids' => [42, 43]]);
        $request->session()->put('biometric_user_selection.1', [42 => $first, 43 => $second]);
        $device = (new BiometricDevice)->forceFill(['id' => 1, 'protocol' => 'ZKProtocol']);
        $this->mock(BiometricDeviceService::class, function (MockInterface $mock) use ($first, $second): void {
            $mock->shouldReceive('getUsers')->once()->with(1)->andReturn([$first, $second]);
            $mock->shouldReceive('deleteUser')->once()->with(1, 42)->andReturnTrue();
            $mock->shouldReceive('deleteUser')->once()->with(1, 43)->andThrow(new \RuntimeException('Sin respuesta'));
            $mock->shouldReceive('disconnect')->once()->andReturnTrue();
        });

        $response = app(BiometricDeviceController::class)->deleteDeviceUsers($device, $request);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame([42], $response->getData(true)['deleted']);
        $this->assertSame([43 => $second], $request->session()->get('biometric_user_selection.1'));
    }

    private function bindDevice(): void
    {
        Route::bind('device', fn (string $id): BiometricDevice => (new BiometricDevice)->forceFill(['id' => (int) $id, 'protocol' => 'ZKProtocol']));
    }

    /** @param array{uid: int, userid: string, name: string, role: int} $user */
    private function deletionRequest(array $user): Request
    {
        $administrator = User::factory()->make(['id' => 1, 'is_admin' => true, 'is_active' => true]);
        $request = Request::create('/biometric/devices/1/users', 'DELETE', ['user_ids' => [42], 'confirmed' => true]);
        $request->setUserResolver(fn () => $administrator);
        $request->setLaravelSession(app('session')->driver());
        $request->session()->put('biometric_user_selection.1', [42 => $user]);

        return $request;
    }
}
