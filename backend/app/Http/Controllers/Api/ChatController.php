<?php

namespace App\Http\Controllers\Api;

use App\Ai\AgentContext;
use App\Ai\Agents\ShoppingAssistantAgent;
use App\Ai\PiiMasker;
use App\Ai\PromptInjectionDetector;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class ChatController extends Controller
{
    use ApiResponseTrait;

    public function chat(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'string', 'uuid'],
            'delivery_address' => ['nullable', 'array'],
            'delivery_address.phone' => ['required_with:delivery_address', 'string', 'max:30'],
            'delivery_address.secondary_phone' => ['nullable', 'string', 'max:30'],
            'delivery_address.address' => ['required_with:delivery_address', 'string', 'max:500'],
            'delivery_address.city' => ['required_with:delivery_address', 'string', 'max:100'],
            'delivery_address.state' => ['required_with:delivery_address', 'string', 'max:100'],
            'delivery_address.country' => ['required_with:delivery_address', 'string', 'max:100'],
        ]);

        $user = $request->user();

        if ($pattern = (new PromptInjectionDetector)->detect($payload['message'])) {
            Log::warning('Possible prompt injection attempt', [
                'user_id' => $user->id,
                'pattern' => $pattern,
                'endpoint' => 'chat',
            ]);

            // Behavioral throttle: a single match is never blocked, but three
            // flagged messages within ten minutes stop reaching the agent.
            RateLimiter::hit($throttleKey = "chat-injection:{$user->id}", 600);

            if (RateLimiter::attempts($throttleKey) >= 3) {
                return $this->errorResponse('Too many suspicious messages. Please try again later.', 429);
            }
        }

        // Masked before the agent sees it, so the provider payload, the stored
        // transcript, and every future history replay all get the masked text.
        $message = (new PiiMasker)->mask($payload['message']);

        try {
            $context = new AgentContext;

            if (! empty($payload['delivery_address'])) {
                $context->setDeliveryAddress($payload['delivery_address']);
            }

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

            $response = $agent->prompt($message);

            return $this->successResponse([
                'reply' => (string) $response,
                'conversation_id' => $response->conversationId,
                'products' => $context->getProducts(),
                'order_placed' => $context->orderWasPlaced(),
                'order_info' => $context->getOrderInfo(),
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
