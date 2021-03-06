<?php

namespace Modules\DevelUsers\Tests\Feature;

use Devel\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Devel\Database\Seeders\DevelDatabaseSeeder;
use Devel\Models\Auth\Role;
use Modules\DevelDashboard\Database\Seeders\DevelDashboardDatabaseSeeder;

class CrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DevelDatabaseSeeder::class);
        $this->seed(DevelDashboardDatabaseSeeder::class);

        $this->userModel = config('auth.providers.users.model');

        $this->root = $this->userModel::find(1);
        $this->admin = factory($this->userModel)->create();

        $this->admin->roles()->attach('admin');
    }

    /** @test */
    public function roots_can_view_user_lists()
    {
        $response = $this->actingAs($this->root)
            ->get(route('dashboard.develusers.users.get'))
            ->assertStatus(200);

        $data = $response->json();

        $this->assertEquals($this->userModel::count(), $data['total']);
    }

    /** @test */
    public function roots_can_create_users()
    {
        $data = [
            'name' => 'user',
            'email' => 'user@example.com',
            'password' => 'qwerty1234',
            'roles' => ['user'],
        ];

        $this->assertDatabaseMissing('users', ['email' => $data['email']]);

        $this->actingAs($this->root)
            ->post(route('dashboard.develusers.users.store'), $data)
            ->assertStatus(201);

        $this->assertDatabaseHas('users', ['email' => $data['email']]);

        // Assert the role was assigned to the user
        $user = $this->userModel::where('email', $data['email'])->first();

        $this->assertCount(1, $user->roles);
        $this->assertEquals($data['roles'][0], $user->roles->pluck('key')[0]);
    }

    /** @test */
    public function users_without_roles_can_be_created()
    {
        $data = [
            'name' => 'user',
            'email' => 'user@example.com',
            'password' => 'qwerty1234',
            'roles' => [],
        ];

        $this->assertDatabaseMissing('users', ['email' => $data['email']]);

        $this->actingAs($this->root)
            ->post(route('dashboard.develusers.users.store'), $data)
            ->assertStatus(201);

        $this->assertDatabaseHas('users', ['email' => $data['email']]);

        // Assert the role was assigned to the user
        $user = $this->userModel::where('email', $data['email'])->first();

        $this->assertCount(0, $user->roles);
    }

    /** @test */
    public function roots_can_view_users()
    {
        $user = factory($this->userModel)->create();

        $this->actingAs($this->root)
            ->get(route('dashboard.develusers.users.edit', $user->id))
            ->assertStatus(200);
    }

    /** @test */
    public function roots_can_edit_users()
    {
        $user = factory($this->userModel)->create();

        $data = [
            'name' => 'user',
            'email' => 'user@example.com',
            'password' => 'qwerty1234',
        ];

        $this->actingAs($this->root)
            ->post(route('dashboard.develusers.users.update', $user->id), $data)
            ->assertStatus(200);

        $user = $user->refresh();

        $this->assertEquals([
            'name' => $data['name'],
            'email' => $data['email'],
        ], [
            'name' => $user['name'],
            'email' => $user['email'],
        ]);

        $this->assertTrue(Hash::check($data['password'], $user->password));
    }

    /** @test */
    public function all_user_roles_can_be_detached()
    {
        $user = factory($this->userModel)->create();
        $user->roles()->attach(factory(Role::class)->create()->key);

        $data = [
            'name' => 'user',
            'email' => 'user@example.com',
            'password' => 'qwerty1234',
            'roles' => [],
        ];

        $this->actingAs($this->root)
            ->post(route('dashboard.develusers.users.update', $user->id), $data)
            ->assertStatus(200);

        $user = $user->refresh();

        $this->assertEquals([
            'name' => $data['name'],
            'email' => $data['email'],
        ], [
            'name' => $user['name'],
            'email' => $user['email'],
        ]);

        $this->assertTrue(Hash::check($data['password'], $user->password));
        $this->assertCount(0, $user->roles);
    }

    /** @test */
    public function roots_can_delete_users()
    {
        $user = factory($this->userModel)->create();

        $this->actingAs($this->root)
            ->delete(route('dashboard.develusers.users.destroy', $user->id))
            ->assertStatus(200);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    /** @test */
    public function roots_can_grant_personal_permissions_to_users()
    {
        $user = factory($this->userModel)->create();

        $this->assertCount(0, $user->permissions);

        $data = [
            'name' => 'user',
            'email' => 'user@example.com',
            'password' => 'qwerty1234',
            'permissions' => ['users.add', 'users.edit'],
        ];

        $this->actingAs($this->root)
            ->post(route('dashboard.develusers.users.update', $user->id), $data)
            ->assertStatus(200);

        $user = $user->refresh();

        $this->assertCount(count($data['permissions']), $user->permissions);

        $this->assertEquals(
            $data['permissions'],
            $user->permissions->pluck('key')->toArray()
        );
    }

    /** @test */
    public function assigning_roles_requires_a_special_permission()
    {
        $this->admin->permissions()->attach('users.edit');

        $user = factory($this->userModel)->create();

        $this->assertCount(0, $user->roles);

        $data = [
            'name' => 'user',
            'email' => 'user@example.com',
            'roles' => ['admin'],
        ];

        // Admin doesn't have the permission
        $this->actingAs($this->admin)
            ->postJson(route('dashboard.develusers.users.update', $user->id), $data)
            ->assertStatus(200);

        $this->assertCount(0, $user->fresh()->roles);

        // The root has the permission
        $this->actingAs($this->root)
            ->postJson(route('dashboard.develusers.users.update', $user->id), $data)
            ->assertStatus(200);

        $user = $user->refresh();

        $this->assertCount(count($data['roles']), $user->roles);

        $this->assertEquals(
            $data['roles'][0],
            $user->roles[0]['key']
        );
    }

    /** @test */
    public function granting_personal_permissions_requires_a_special_permission()
    {
        $this->admin->permissions()->attach('users.edit');

        $user = factory($this->userModel)->create();

        $this->assertCount(0, $user->roles);

        $data = [
            'name' => 'user',
            'email' => 'user@example.com',
            'permissions' => ['users.edit'],
        ];

        // Admin doesn't have the permission
        $this->actingAs($this->admin)
            ->postJson(route('dashboard.develusers.users.update', $user->id), $data)
            ->assertStatus(200);

        $this->assertCount(0, $user->fresh()->permissions);

        // The root has the permission
        $this->actingAs($this->root)
            ->postJson(route('dashboard.develusers.users.update', $user->id), $data)
            ->assertStatus(200);

        $user = $user->refresh();

        $this->assertCount(count($data['permissions']), $user->permissions);

        $this->assertEquals(
            $data['permissions'][0],
            $user->permissions[0]['key']
        );
    }
}
