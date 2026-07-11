<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_is_throttled_after_five_attempts_per_email(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'attacker@example.com',
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'attacker@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertJson([
                'code' => 429,
                'success' => false,
            ]);
    }

    public function test_login_throttle_keys_are_isolated_per_email(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'first@example.com',
                'password' => 'wrong-password',
            ]);
        }

        // A different email from the same IP is not locked out.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'second@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    public function test_chat_is_throttled_after_ten_requests_per_minute(): void
    {
        $user = User::factory()->create();

        // Empty payloads fail validation (422) after passing the throttle,
        // so no real LLM call is ever made.
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->postJson('/api/v1/chat', [])->assertUnprocessable();
        }

        $this->actingAs($user)->postJson('/api/v1/chat', [])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertJson([
                'code' => 429,
                'success' => false,
            ]);
    }

    public function test_admin_chat_is_throttled_after_twenty_requests_per_minute(): void
    {
        $admin = User::factory()->admin()->create();

        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($admin)->postJson('/api/v1/admin/chat', [])->assertUnprocessable();
        }

        $this->actingAs($admin)->postJson('/api/v1/admin/chat', [])->assertTooManyRequests();
    }
}
