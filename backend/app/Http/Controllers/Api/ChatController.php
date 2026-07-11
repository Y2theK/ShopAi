<?php

namespace App\Http\Controllers\Api;

use App\Ai\AgentContext;
use App\Ai\Agents\ShoppingAssistantAgent;
use App\Ai\PromptInjectionDetector;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatController extends Controller
{
    use ApiResponseTrait;

    public function chat(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'string', 'uuid'],
        ]);

        $user = $request->user();

        if ($pattern = (new PromptInjectionDetector)->detect($payload['message'])) {
            Log::warning('Possible prompt injection attempt', [
                'user_id' => $user->id,
                'pattern' => $pattern,
                'endpoint' => 'chat',
            ]);
        }

        try {
            $context = new AgentContext;
            $agent = new ShoppingAssistantAgent($user, $context);

            $conversationId = $payload['conversation_id'] ?? null;

            if ($conversationId) {
                $exists = DB::table('agent_conversations')
                    ->where('id', $conversationId)
                    ->where('user_id', $user->id)
                    ->exists();

                if ($exists) {
                    $agent->continue($conversationId, $user);
                } else {
                    $agent->forUser($user);
                }
            } else {
                $agent->forUser($user);
            }

            $response = $agent->prompt($payload['message']);

            return $this->successResponse([
                'reply' => (string) $response,
                'conversation_id' => $response->conversationId,
                'products' => $context->getProducts(),
                'order_placed' => $context->orderWasPlaced(),
            ]);
        } catch (Throwable $e) {
            Log::error('Shopping assistant chat failed', [
                'user_id' => $user->id,
                'exception' => $e,
            ]);

            return $this->errorResponse('The assistant is unavailable right now. Please try again shortly.', 500);
        }
    }
}
