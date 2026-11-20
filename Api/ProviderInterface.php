<?php
/**
 * Copyright (c) 2026 Ievgenii Gryshkun (angeo.dev)
 * MIT License — see LICENSE for full terms.
 */

declare(strict_types=1);

namespace Angeo\AiAssist\Api;

use Magento\Framework\Exception\LocalizedException;

/**
 * One AI provider. Sends a system and a user prompt, returns text.
 *
 * @api
 */
interface ProviderInterface
{
    /**
     * @return array{text:string,input_tokens:?int,output_tokens:?int,stop:?string}
     * @throws LocalizedException on transport errors, API errors and empty answers
     */
    public function complete(string $system, string $user, string $apiKey, string $model, int $maxTokens, int $timeout): array;
}
