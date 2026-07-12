<?php

namespace Tests\Feature;

use App\Ai\Agents\ShoppingAssistantAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Ai\Ai;
use Tests\TestCase;

class ChatCheckoutMarkerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var array<string, string|null> */
    private const DELIVERY_ADDRESS = [
        'phone' => '0912345678',
        'secondary_phone' => null,
        'address' => '123 Main Street',
        'city' => 'Yangon',
        'state' => 'Yangon Region',
        'country' => 'Myanmar',
    ];

    public function test_the_checkout_marker_is_appended_when_a_delivery_address_is_attached(): void
    {
        $user = User::factory()->create();

        Ai::fakeAgent(ShoppingAssistantAgent::class, ['Order placed!']);

        $this->actingAs($user)->postJson('/api/v1/chat', [
            'message' => 'Confirm, place my order.',
            'delivery_address' => self::DELIVERY_ADDRESS,
        ])->assertOk();

        Ai::assertAgentWasPrompted(ShoppingAssistantAgent::class, function ($prompt) {
            return str_contains($prompt->prompt, '[Store checkout: the delivery form is completed')
                && ! str_contains($prompt->prompt, '123 Main Street')
                && ! str_contains($prompt->prompt, '0912345678');
        });
    }

    public function test_the_checkout_marker_is_absent_without_a_delivery_address(): void
    {
        $user = User::factory()->create();

        Ai::fakeAgent(ShoppingAssistantAgent::class, ['Sure!']);

        $this->actingAs($user)->postJson('/api/v1/chat', [
            'message' => 'Show me yoga mats',
        ])->assertOk();

        Ai::assertAgentWasPrompted(ShoppingAssistantAgent::class, function ($prompt) {
            return ! str_contains($prompt->prompt, '[Store checkout:');
        });
    }
}
