<?php

namespace App\Ai;

class PromptInjectionDetector
{
    /**
     * Known prompt-injection markers, keyed by a loggable name.
     * Detection is advisory: matches are logged for visibility, never blocked —
     * containment is enforced in code (tool caps, read-only tools, rate limits).
     *
     * @var array<string, string>
     */
    private const PATTERNS = [
        'ignore_instructions' => '/\bignore\s+(all\s+|any\s+)?(previous|prior|above|earlier)\s+(instructions?|prompts?|rules?)/i',
        'disregard_instructions' => '/\bdisregard\s+(your|the|all|any|previous)\b.{0,30}(instructions?|rules?|guidelines?)/i',
        'override_instructions' => '/\boverride\s+(your|the|all|any)\b.{0,30}(instructions?|rules?|guidelines?|safety)/i',
        'reveal_system_prompt' => '/\b(reveal|show|print|repeat|output|display)\b.{0,30}(system\s+prompt|your\s+(instructions?|prompt|rules?))/i',
        'system_prompt_probe' => '/\bsystem\s+prompt\b/i',
        'new_persona' => '/\byou\s+are\s+now\b/i',
        'act_without_rules' => '/\bact\s+as\s+if\s+you\s+have\s+no\s+(restrictions?|rules?|guidelines?|limitations?)/i',
        'developer_mode' => '/\bdeveloper\s+mode\b/i',
        'jailbreak' => '/\bjail\s?break\b/i',
        'do_anything_now' => '/\bdo\s+anything\s+now\b/i',
        'pretend_unrestricted' => '/\bpretend\s+(you\s+are|to\s+be)\b.{0,30}(unrestricted|without\s+rules?|not\s+an\s+ai)/i',
    ];

    /**
     * Return the name of the first matching injection pattern, or null when clean.
     */
    public function detect(string $message): ?string
    {
        $message = TextNormalizer::normalize($message);

        foreach (self::PATTERNS as $name => $pattern) {
            if (preg_match($pattern, $message) === 1) {
                return $name;
            }
        }

        return null;
    }
}
