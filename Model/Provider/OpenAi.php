<?php
/**
 * Copyright (c) 2026 Ievgenii Gryshkun (angeo.dev)
 * MIT License — see LICENSE for full terms.
 */

declare(strict_types=1);

namespace Angeo\AiAssist\Model\Provider;

/**
 * OpenAI Chat Completions API.
 */
class OpenAi extends AbstractHttpProvider
{
    private const URL = 'https://api.openai.com/v1/chat/completions';

    /** Reasoning models take max_completion_tokens and reject temperature. */
    private const REASONING_MODEL = '/^(o\d|gpt-5)/i';

    public function complete(string $system, string $user, string $apiKey, string $model, int $maxTokens, int $timeout): array
    {
        $payload = [
            'model'    => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
        ];
        if (preg_match(self::REASONING_MODEL, $model) === 1) {
            $payload['max_completion_tokens'] = $maxTokens;
        } else {
            $payload['max_tokens'] = $maxTokens;
        }

        $data = $this->post(self::URL, $payload, ['Authorization' => 'Bearer ' . $apiKey], $timeout);

        return $this->requireText($this->parser->openai($data));
    }

    protected function name(): string
    {
        return 'OpenAI';
    }
}
