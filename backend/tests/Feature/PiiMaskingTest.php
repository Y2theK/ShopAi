<?php

namespace Tests\Feature;

use App\Ai\Agents\ShoppingAssistantAgent;
use App\Ai\ChartContext;
use App\Ai\PiiMasker;
use App\Ai\Tools\RecentOrdersTool;
use App\Ai\Tools\TopCustomersTool;
use App\Models\Order;
use App\Models\User;
use App\Traits\MasksPii;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Ai;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class PiiMaskingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_top_customers_tool_masks_customer_emails(): void
    {
        $customer = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane.doe@example.com']);
        Order::factory()->recycle($customer)->create(['total_price' => 100]);

        $result = (string) (new TopCustomersTool(new ChartContext))->handle(new Request);

        $this->assertStringContainsString('Jane Doe (j***@example.com)', $result);
        $this->assertStringNotContainsString('jane.doe@example.com', $result);
    }

    public function test_recent_orders_tool_masks_customer_emails(): void
    {
        $customer = User::factory()->create(['name' => 'John Roe', 'email' => 'john@example.com']);
        Order::factory()->recycle($customer)->create();

        $result = (string) (new RecentOrdersTool)->handle(new Request);

        $this->assertStringContainsString('John Roe (j***@example.com)', $result);
        $this->assertStringNotContainsString('john@example.com', $result);
    }

    public function test_masks_pii_trait_handles_edge_cases(): void
    {
        $masker = new class
        {
            use MasksPii;

            public function email(string $email): string
            {
                return $this->maskEmail($email);
            }

            public function phone(string $phone): string
            {
                return $this->maskPhone($phone);
            }
        };

        $this->assertSame('j***@example.com', $masker->email('jane@example.com'));
        $this->assertSame('a***@b.com', $masker->email('a@b.com'));
        $this->assertSame('not-an-email', $masker->email('not-an-email'));

        $this->assertSame('********78', $masker->phone('0912345678'));
        $this->assertSame('***', $masker->phone('123'));
    }

    public function test_the_masker_redacts_structured_pii(): void
    {
        $masker = new PiiMasker;

        $cases = [
            ['email', 'reach me at jane.doe@example.com please'],
            ['nrc', 'my NRC is 12/YaKaNa(Naing)123456'],
            ['nrc', 'NRC: 9/MaHaMa(N)887766'],
            ['nrc_mm', 'မှတ်ပုံတင် ၁၂/ရကန(နိုင်)၁၂၃၄၅၆ ဖြစ်ပါတယ်'],
            ['ssn', 'ssn 123-45-6789'],
            ['mm_phone', 'call 09123456789 after lunch'],
            ['mm_phone', 'call 09-123-456-789 after lunch'],
            ['mm_phone', 'call +959123456789 after lunch'],
            ['passport', 'passport MD123456'],
            ['digit_run', 'card 4242 4242 4242 4242'],
            ['digit_run', 'account 12345678901234'],
        ];

        foreach ($cases as [$name, $text]) {
            $this->assertStringContainsString("[{$name}-redacted]", $masker->mask($text), "Expected {$name} redaction in: {$text}");
            $this->assertContains($name, $masker->detect($text), "Expected {$name} to be detected in: {$text}");
        }
    }

    public function test_the_masker_leaves_normal_shopping_messages_untouched(): void
    {
        $masker = new PiiMasker;

        $messages = [
            'I want 2 of the $1,137.96 bundle',
            'track my order ORD-AB12CD34',
            'can it arrive by 12/08/2026?',
            'show me 20 products under $50',
            'do you have the RTX 4090 in stock?',
            'မြန်မာစာအုပ်တွေ ရှိလား',
            'my postcode is 11181',
        ];

        foreach ($messages as $message) {
            $this->assertSame($message, $masker->mask($message), "False positive on: {$message}");
            $this->assertSame([], $masker->detect($message));
        }
    }

    public function test_chat_messages_are_masked_before_reaching_the_agent(): void
    {
        $user = User::factory()->create();

        Ai::fakeAgent(ShoppingAssistantAgent::class, ['Sure, happy to help!']);

        $this->actingAs($user)->postJson('/api/v1/chat', [
            'message' => 'My phone is 09123456789 and NRC 12/YaKaNa(Naing)123456',
        ])->assertOk();

        Ai::assertAgentWasPrompted(ShoppingAssistantAgent::class, function ($prompt) {
            return str_contains($prompt->prompt, '[mm_phone-redacted]')
                && str_contains($prompt->prompt, '[nrc-redacted]')
                && ! str_contains($prompt->prompt, '09123456789')
                && ! str_contains($prompt->prompt, '123456');
        });
    }

    public function test_the_canary_logs_when_an_agent_reply_contains_pii(): void
    {
        $user = User::factory()->create();

        Ai::fakeAgent(ShoppingAssistantAgent::class, ['You can email jane.doe@example.com for support.']);

        Log::spy();

        $this->actingAs($user)->postJson('/api/v1/chat', [
            'message' => 'Who do I contact for support?',
        ])->assertOk();

        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context = []) {
            return $message === 'Possible PII leak in agent reply'
                && in_array('email', $context['patterns'] ?? [], true)
                && ! str_contains(json_encode($context), 'jane.doe');
        })->once();
    }

    public function test_the_canary_stays_quiet_for_clean_replies(): void
    {
        $user = User::factory()->create();

        Ai::fakeAgent(ShoppingAssistantAgent::class, ['We have 5 mice in stock at $29.99.']);

        Log::spy();

        $this->actingAs($user)->postJson('/api/v1/chat', [
            'message' => 'Any mice in stock?',
        ])->assertOk();

        Log::shouldNotHaveReceived('warning');
    }
}
