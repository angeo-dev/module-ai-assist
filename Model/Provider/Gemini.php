<?php
/**
 * Copyright (c) 2026 Ievgenii Gryshkun (angeo.dev)
 * MIT License — see LICENSE for full terms.
 */

declare(strict_types=1);

namespace Angeo\AiAssist\Model\Provider;

/**
 * Google Gemini generateContent API. The key goes in x-goog-api-key, not in
 * the URL query string.
 */
class Gemini extends AbstractHttpProvider
{
    private const URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    public function complete(string $system, string $user, string $apiKey, string $model, int $maxTokens, int $timeout): array
    {
        $data = $this->post(sprintf(self::URL, rawurlencode($model)), [
            'system_instruction' => ['parts' => [['text' => $system]]],
            'contents'           => [['role' => 'user', 'parts' => [['text' => $user]]]],
            'generationConfig'   => ['maxOutputTokens' => $maxTokens],
        ], ['x-goog-api-key' => $apiKey], $timeout);

        return $this->requireText($this->parser->gemini($data));
    }

    protected function name(): string
    {
        return 'Gemini';
    }
}
