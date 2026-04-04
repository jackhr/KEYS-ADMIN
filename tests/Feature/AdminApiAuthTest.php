<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_a_token_for_active_admin_user(): void
    {
        AdminUser::query()->create([
            'username' => 'admin',
            'password_hash' => Hash::make('super-secret'),
            'role' => 'admin',
            'active' => true,
        ]);

        $response = $this->postJson('/api/admin/login', [
            'username' => 'admin',
            'password' => 'super-secret',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => [
                        'id',
                        'username',
                        'role',
                        'active',
                    ],
                ],
            ]);
    }

    public function test_protected_admin_routes_require_an_auth_token(): void
    {
        $response = $this->getJson('/api/admin/me');

        $response->assertUnauthorized();
    }
}
