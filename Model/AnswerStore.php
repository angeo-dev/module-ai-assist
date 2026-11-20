<?php
/**
 * Copyright (c) 2026 Ievgenii Gryshkun (angeo.dev)
 * MIT License — see LICENSE for full terms.
 */

declare(strict_types=1);

namespace Angeo\AiAssist\Model;

use Magento\Framework\App\ResourceConnection;

/**
 * Answers already paid for, and the daily count per admin user.
 *
 * Stored: who asked (admin user id), about what (source, ref, task), a hash
 * of the question and of the facts, the answer, the model. Not stored: the
 * question text and the facts themselves.
 */
class AnswerStore
{
    public const TABLE = 'angeo_ai_assist_answer';

    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * An answer for exactly these facts and this question, not older than
     * $maxAgeDays. Shared between admin users: the facts are the same.
     */
    public function find(
        string $source,
        string $ref,
        string $task,
        string $questionHash,
        string $contextHash,
        string $model,
        int $maxAgeDays
    ): ?string {
        if ($maxAgeDays <= 0) {
            return null;
        }
        $db     = $this->resource->getConnection();
        $select = $db->select()
            ->from($this->resource->getTableName(self::TABLE), ['answer'])
            ->where('source = ?', $source)
            ->where('ref = ?', $ref)
            ->where('task = ?', $task)
            ->where('question_hash = ?', $questionHash)
            ->where('context_hash = ?', $contextHash)
            ->where('model = ?', $model)
            ->where('created_at >= ?', gmdate('Y-m-d H:i:s', time() - $maxAgeDays * 86400))
            ->order('id DESC')
            ->limit(1);
        $answer = $db->fetchOne($select);

        return is_string($answer) && $answer !== '' ? $answer : null;
    }

    /** New answers this admin user got since midnight UTC. */
    public function countToday(int $adminUserId): int
    {
        $db = $this->resource->getConnection();

        return (int) $db->fetchOne(
            $db->select()
                ->from($this->resource->getTableName(self::TABLE), ['COUNT(*)'])
                ->where('admin_user_id = ?', $adminUserId)
                ->where('created_at >= ?', gmdate('Y-m-d 00:00:00'))
        );
    }

    public function save(
        int $adminUserId,
        string $source,
        string $ref,
        string $task,
        string $questionHash,
        string $contextHash,
        string $provider,
        string $model,
        string $answer,
        ?int $inputTokens,
        ?int $outputTokens
    ): void {
        $this->resource->getConnection()->insert($this->resource->getTableName(self::TABLE), [
            'admin_user_id' => $adminUserId,
            'source'        => $source,
            'ref'           => $ref,
            'task'          => $task,
            'question_hash' => $questionHash,
            'context_hash'  => $contextHash,
            'provider'      => mb_substr($provider, 0, 16),
            'model'         => mb_substr($model, 0, 80),
            'answer'        => $answer,
            'input_tokens'  => $inputTokens,
            'output_tokens' => $outputTokens,
            'created_at'    => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function purgeOlderThan(int $days): int
    {
        return (int) $this->resource->getConnection()->delete(
            $this->resource->getTableName(self::TABLE),
            ['created_at < ?' => gmdate('Y-m-d H:i:s', time() - max(1, $days) * 86400)]
        );
    }
}
