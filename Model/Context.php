<?php
/**
 * Copyright (c) 2026 Ievgenii Gryshkun (angeo.dev)
 * MIT License — see LICENSE for full terms.
 */

declare(strict_types=1);

namespace Angeo\AiAssist\Model;

/**
 * What one admin page tells the assistant about itself.
 *
 * Built by ContextCollector from what an observer of the
 * "angeo_ai_assist_context" event wrote. See CONTEXT.md for the contract.
 */
class Context
{
    /**
     * @param array<mixed>         $facts
     * @param array<string,string> $glossary
     */
    public function __construct(
        private readonly string $source,
        private readonly string $ref,
        private readonly string $title,
        private readonly string $summary,
        private readonly array $facts,
        private readonly array $glossary = []
    ) {
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getRef(): string
    {
        return $this->ref;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    /** @return array<mixed> */
    public function getFacts(): array
    {
        return $this->facts;
    }

    /** @return array<string,string> */
    public function getGlossary(): array
    {
        return $this->glossary;
    }

    /**
     * Same facts → same hash. A cached answer is reused only while the facts
     * it was written for have not changed.
     */
    public function hash(): string
    {
        return hash('sha256', (string) json_encode(
            [$this->source, $this->ref, $this->title, $this->summary, $this->facts, $this->glossary],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
        ));
    }
}
