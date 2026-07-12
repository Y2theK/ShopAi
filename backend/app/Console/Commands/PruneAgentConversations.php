<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('ai:prune-conversations {--days=30 : Delete conversations inactive for more than this many days}')]
#[Description('Delete AI agent conversations (and their messages) older than the retention window')]
class PruneAgentConversations extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $conversationsTable = config('ai.conversations.tables.conversations', 'agent_conversations');
        $messagesTable = config('ai.conversations.tables.messages', 'agent_conversation_messages');

        $staleIds = DB::table($conversationsTable)
            ->where('updated_at', '<', $cutoff)
            ->pluck('id');

        if ($staleIds->isEmpty()) {
            $this->info("No conversations older than {$days} days.");

            return self::SUCCESS;
        }

        // The messages table has no cascading foreign key, so delete both sides explicitly.
        $staleIds->chunk(500)->each(function ($ids) use ($conversationsTable, $messagesTable) {
            DB::table($messagesTable)->whereIn('conversation_id', $ids)->delete();
            DB::table($conversationsTable)->whereIn('id', $ids)->delete();
        });

        $this->info("Pruned {$staleIds->count()} conversations older than {$days} days.");

        return self::SUCCESS;
    }
}
