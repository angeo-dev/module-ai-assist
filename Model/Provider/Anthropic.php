<?php
/**
 * Copyright (c) 2026 Ievgenii Gryshkun (angeo.dev)
 * MIT License — see LICENSE for full terms.
 */

declare(strict_types=1);

namespace Angeo\AiAssist\Model\Provider;

/**
 * Anthropic Messages API.
 */
class Anthropic extends AbstractHttpProvider
{
    private const URL         = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public function complete(string $system, string $user, string $apiKey, string $model, int $maxTokens, int $timeout): array
    {
        $data = $this->post(self::URL, [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'system'     => $system,
            'messages'   => [['role' => 'user', 'content' => $user]],
        ], [
            'x-api-key'         => $apiKey,
            'anthropic-version' => self::API_VERSION,
        ], $timeout);

        return $this->requireText($this->parser->anthropic($data));
    }

    protected function name(): string
    {
        return 'Anthropic';
    }
}
