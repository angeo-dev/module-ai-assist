<?php
/**
 * Copyright (c) 2026 Ievgenii Gryshkun (angeo.dev)
 * MIT License — see LICENSE for full terms.
 */

declare(strict_types=1);

namespace Angeo\AiAssist\Test\Unit\Model;

use Angeo\AiAssist\Model\AnswerStore;

/** In-memory AnswerStore. */
class FakeStore extends AnswerStore
{
    /** @var array<int,array<string,mixed>> */
    public array $rows = [];

    public function __construct()
    {
    }

    public function find(string $source, string $ref, string $task, string $questionHash, string $contextHash, string $model, int $maxAgeDays): ?string
    {
        foreach (array_reverse($this->rows) as $row) {
            if ([$row['source'], $row['ref'], $row['task'], $row['q'], $row['c'], $row['model']] === [$source, $ref, $task, $questionHash, $contextHash, $model]) {
                return $row['answer'];
            }
        }

        return null;
    }

    public function countToday(int $adminUserId): int
    {
        return count(array_filter($this->rows, static fn (array $r): bool => $r['user'] === $adminUserId));
    }

    public function save(int $adminUserId, string $source, string $ref, string $task, string $questionHash, string $contextHash, string $provider, string $model, string $answer, ?int $inputTokens, ?int $outputTokens): void
    {
        $this->rows[] = ['user' => $adminUserId, 'source' => $source, 'ref' => $ref, 'task' => $task, 'q' => $questionHash, 'c' => $contextHash, 'model' => $model, 'answer' => $answer];
    }
}
