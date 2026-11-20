<?php
/**
 * Copyright (c) 2026 Ievgenii Gryshkun (angeo.dev)
 * MIT License — see LICENSE for full terms.
 */

declare(strict_types=1);

namespace Angeo\AiAssist\Test\Unit\Model;

use Angeo\AiAssist\Api\ProviderInterface;

/** Provider stub that counts calls. */
class FakeProvider implements ProviderInterface
{
    public int $calls = 0;
    public string $lastKey = '';
    public ?string $stop = 'end_turn';

    public function complete(string $system, string $user, string $apiKey, string $model, int $maxTokens, int $timeout): array
    {
        $this->calls++;
        $this->lastKey = $apiKey;

        return ['text' => 'Answer ' . $this->calls, 'input_tokens' => 10, 'output_tokens' => 5, 'stop' => $this->stop];
    }
}
