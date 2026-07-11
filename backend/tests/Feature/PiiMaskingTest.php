<?php

namespace Tests\Feature;

use App\Ai\ChartContext;
use App\Ai\Tools\RecentOrdersTool;
use App\Ai\Tools\TopCustomersTool;
use App\Models\Order;
use App\Models\User;
use App\Traits\MasksEmails;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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

    public function test_mask_email_handles_edge_cases(): void
    {
        $masker = new class
        {
            use MasksEmails;

            public function mask(string $email): string
            {
                return $this->maskEmail($email);
            }
        };

        $this->assertSame('j***@example.com', $masker->mask('jane@example.com'));
        $this->assertSame('a***@b.com', $masker->mask('a@b.com'));
        $this->assertSame('not-an-email', $masker->mask('not-an-email'));
    }
}
