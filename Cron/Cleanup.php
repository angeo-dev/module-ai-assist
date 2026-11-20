<?php
/**
 * Copyright (c) 2026 Ievgenii Gryshkun (angeo.dev)
 * MIT License — see LICENSE for full terms.
 */

declare(strict_types=1);

namespace Angeo\AiAssist\Cron;

use Angeo\AiAssist\Model\AnswerStore;

/**
 * Stored answers are deleted after 90 days. They are only a cache and a
 * daily counter; nothing needs them longer.
 */
class Cleanup
{
    public const KEEP_DAYS = 90;

    public function __construct(
        private readonly AnswerStore $store
    ) {
    }

    public function execute(): void
    {
        $this->store->purgeOlderThan(self::KEEP_DAYS);
    }
}
