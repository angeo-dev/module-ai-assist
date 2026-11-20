<?php
/**
 * Copyright (c) 2026 Ievgenii Gryshkun (angeo.dev)
 * MIT License — see LICENSE for full terms.
 */

declare(strict_types=1);

namespace Angeo\AiAssist\Model;

use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

/**
 * Asks the module that owns a page for the facts of one record.
 *
 * The browser sends only a source ("aeo_audit") and a record reference
 * ("42"). The facts are built on the server, by the module that owns the
 * data, in an observer of "angeo_ai_assist_context". So the browser can never
 * choose what is sent to the AI provider, and this module needs no code
 * dependency on the others. See CONTEXT.md for the contract.
 */
class ContextCollector
{
    public const EVENT = 'angeo_ai_assist_context';

    public function __construct(
        private readonly ManagerInterface $eventManager,
        private readonly ContextSanitizer $sanitizer
    ) {
    }

    public static function validSource(string $source): bool
    {
        return preg_match('/^[a-z][a-z0-9_]{1,31}$/', $source) === 1;
    }

    public static function validRef(string $ref): bool
    {
        return preg_match('/^[A-Za-z0-9_.:=&\-]{1,64}$/', $ref) === 1;
    }

    /**
     * @throws LocalizedException when the source is unknown, the record does
     *         not exist, or the admin may not see it
     */
    public function collect(string $source, string $ref): Context
    {
        if (!self::validSource($source) || !self::validRef($ref)) {
            throw new LocalizedException(new Phrase('This page cannot be explained.'));
        }

        $holder = new DataObject();
        $this->eventManager->dispatch(self::EVENT, [
            'source'  => $source,
            'ref'     => $ref,
            'context' => $holder,
        ]);

        if ($holder->getData('denied') === true) {
            throw new LocalizedException(new Phrase('You are not allowed to view this data.'));
        }

        $facts = $holder->getData('facts');
        if (!is_array($facts) || $facts === []) {
            throw new LocalizedException(new Phrase(
                'No data for this page. The module that owns it may be disabled, too old, or the record was deleted.'
            ));
        }

        $glossary = [];
        foreach ((array) $holder->getData('glossary') as $term => $meaning) {
            if (is_string($term) && is_string($meaning) && count($glossary) < 30) {
                $glossary[mb_substr($term, 0, 60)] = mb_substr($meaning, 0, 300);
            }
        }

        return new Context(
            $source,
            $ref,
            mb_substr((string) $holder->getData('title'), 0, 200) ?: $source,
            mb_substr((string) $holder->getData('summary'), 0, 500),
            $this->sanitizer->sanitize($facts),
            $glossary
        );
    }
}
