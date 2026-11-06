<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginProviderTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_default_to_email_provider(): void
    {
        $user = User::factory()->create();

        $this->assertSame('email', $user->fresh()->provider);
    }

    public function test_provider_is_visible_on_the_show_user_endpoint(): void
    {
        // fresh() reloads the model so the DB-level default for `provider`
        // (not passed to factory()->create()) is present on the in-memory
        // instance, same as the other test in this file.
        $user = User::factory()->create()->fresh();
        // The `show` route is behind `auth:api`, which resolves to the
        // jwt-auth guard (see config/auth.php), not Sanctum. Sanctum::actingAs()
        // only authenticates the 'sanctum' guard, so it 401s here; JWTGuard::user()
        // returns whatever is set via setUser() before it tries to parse a token,
        // so actingAs($user, 'api') is the correct way to authenticate against
        // this guard without needing a real JWT.
        $this->actingAs($user, 'api');

        $response = $this->getJson('/api/user/show');

        $response->assertStatus(200);
        $this->assertSame('email', $response->json('data.user.provider'));
    }
}
