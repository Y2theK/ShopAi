<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AdminChatTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_cannot_access_the_report_chat(): void
    {
        $response = $this->postJson('/api/v1/admin/chat', ['message' => 'Best sellers?']);

        $response->assertUnauthorized();
    }

    public function test_non_admin_users_cannot_access_the_report_chat(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/admin/chat', ['message' => 'Best sellers?']);

        $response->assertForbidden();
    }

    public function test_admin_users_pass_the_admin_middleware(): void
    {
        $admin = User::factory()->admin()->create();

        // An empty payload fails validation (422), proving the request got past the admin middleware
        // without triggering a real LLM call.
        $response = $this->actingAs($admin)->postJson('/api/v1/admin/chat', []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['message']);
    }

    public function test_message_is_limited_to_2000_characters(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/chat', [
            'message' => str_repeat('a', 2001),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['message']);
    }
}
