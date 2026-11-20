<?php
/**
 * Copyright (c) 2026 Ievgenii Gryshkun (angeo.dev)
 * MIT License — see LICENSE for full terms.
 */

declare(strict_types=1);

namespace Angeo\AiAssist\Test\Unit\Model;

use Angeo\AiAssist\Model\ContextSanitizer;
use PHPUnit\Framework\TestCase;

class ContextSanitizerTest extends TestCase
{
    private ContextSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new ContextSanitizer();
    }

    public function testSecretAndPersonalKeysAreRemoved(): void
    {
        $out = $this->sanitizer->sanitize([
            'score'          => 72,
            'api_key'        => 'sk-123',
            'customerEmail'  => 'a@b.c',
            'auth_token'     => 'x',
            'remote_addr'    => '1.2.3.4',
            'details'        => ['ip' => '1.2.3.4', 'robots_token' => 'GPTBot', 'agent_card' => 'pass'],
        ]);

        self::assertSame(72, $out['score']);
        self::assertArrayNotHasKey('api_key', $out);
        self::assertArrayNotHasKey('customerEmail', $out);
        self::assertArrayNotHasKey('auth_token', $out);
        self::assertArrayNotHasKey('remote_addr', $out);
        self::assertArrayNotHasKey('ip', $out['details']);
        // Public identifiers that merely end in a blocked word stay.
        self::assertSame('GPTBot', $out['details']['robots_token']);
        self::assertSame('pass', $out['details']['agent_card']);
    }

    public function testLongStringsAndListsAreCut(): void
    {
        $out = $this->sanitizer->sanitize([
            'text'  => str_repeat('a', 2000),
            'items' => range(1, 200),
        ]);

        self::assertLessThanOrEqual(ContextSanitizer::MAX_STRING + 1, mb_strlen($out['text']));
        self::assertCount(ContextSanitizer::MAX_ITEMS + 1, $out['items']);
        self::assertStringContainsString('140 more items', (string) end($out['items']));
    }

    public function testDeepNestingIsDropped(): void
    {
        $deep = ['x' => 1];
        for ($i = 0; $i < 10; $i++) {
            $deep = ['level' => $deep];
        }
        $json = $this->sanitizer->json($this->sanitizer->sanitize($deep));

        self::assertStringContainsString('nested data left out', $json);
    }

    public function testObjectsAndControlCharactersNeverPass(): void
    {
        $out = $this->sanitizer->sanitize(['obj' => new \stdClass(), 'text' => "ok\x00\x07done", 'nan' => NAN]);

        self::assertNull($out['obj']);
        self::assertSame('okdone', $out['text']);
        self::assertNull($out['nan']);
    }

    public function testTotalSizeIsBounded(): void
    {
        $rows = [];
        for ($i = 0; $i < 60; $i++) {
            $rows[] = ['answer' => str_repeat('word ', 110), 'provider' => 'p' . $i];
        }
        $out = $this->sanitizer->sanitize(['results' => $rows, 'score' => 50]);

        self::assertLessThanOrEqual(ContextSanitizer::MAX_BYTES, strlen($this->sanitizer->json($out)));
        self::assertSame(50, $out['score']);
    }
}
