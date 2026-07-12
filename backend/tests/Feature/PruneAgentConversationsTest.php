<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PruneAgentConversationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_conversations_older_than_the_retention_window_are_pruned_with_their_messages(): void
    {
        $staleId = $this->createConversation(updatedAt: now()->subDays(45));
        $freshId = $this->createConversation(updatedAt: now()->subDays(5));

        $this->artisan('ai:prune-conversations')
            ->expectsOutputToContain('Pruned 1 conversations')
            ->assertSuccessful();

        $this->assertDatabaseMissing('agent_conversations', ['id' => $staleId]);
        $this->assertDatabaseMissing('agent_conversation_messages', ['conversation_id' => $staleId]);
        $this->assertDatabaseHas('agent_conversations', ['id' => $freshId]);
        $this->assertDatabaseHas('agent_conversation_messages', ['conversation_id' => $freshId]);
    }

    public function test_the_retention_window_is_configurable(): void
    {
        $conversationId = $this->createConversation(updatedAt: now()->subDays(10));

        $this->artisan('ai:prune-conversations', ['--days' => 7])->assertSuccessful();

        $this->assertDatabaseMissing('agent_conversations', ['id' => $conversationId]);
    }

    public function test_it_reports_when_nothing_needs_pruning(): void
    {
        $this->artisan('ai:prune-conversations')
            ->expectsOutputToContain('No conversations older than 30 days.')
            ->assertSuccessful();
    }

    private function createConversation(Carbon $updatedAt): string
    {
        $conversationId = (string) Str::uuid();

        DB::table('agent_conversations')->insert([
            'id' => $conversationId,
            'user_id' => null,
            'title' => 'Test conversation',
            'created_at' => $updatedAt,
            'updated_at' => $updatedAt,
        ]);

        DB::table('agent_conversation_messages')->insert([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversationId,
            'user_id' => null,
            'agent' => 'test-agent',
            'role' => 'user',
            'content' => 'Hello',
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'created_at' => $updatedAt,
            'updated_at' => $updatedAt,
        ]);

        return $conversationId;
    }
}
