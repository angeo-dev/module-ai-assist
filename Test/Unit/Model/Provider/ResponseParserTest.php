<?php
/**
 * Copyright (c) 2026 Ievgenii Gryshkun (angeo.dev)
 * MIT License — see LICENSE for full terms.
 */

declare(strict_types=1);

namespace Angeo\AiAssist\Test\Unit\Model\Provider;

use Angeo\AiAssist\Model\Provider\ResponseParser;
use PHPUnit\Framework\TestCase;

class ResponseParserTest extends TestCase
{
    public function testAnthropicJoinsTextBlocksAndSkipsOthers(): void
    {
        $out = (new ResponseParser())->anthropic([
            'content'     => [
                ['type' => 'thinking', 'thinking' => 'hidden'],
                ['type' => 'text', 'text' => 'One.'],
                ['type' => 'text', 'text' => 'Two.'],
            ],
            'usage'       => ['input_tokens' => 120, 'output_tokens' => 30],
            'stop_reason' => 'end_turn',
        ]);

        self::assertSame("One.\nTwo.", $out['text']);
        self::assertSame(120, $out['input_tokens']);
        self::assertSame('end_turn', $out['stop']);
    }

    public function testOpenAiReadsFirstChoice(): void
    {
        $out = (new ResponseParser())->openai([
            'choices' => [['message' => ['content' => 'Hello'], 'finish_reason' => 'length']],
            'usage'   => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        ]);

        self::assertSame('Hello', $out['text']);
        self::assertSame(5, $out['output_tokens']);
        self::assertSame('length', $out['stop']);
    }

    public function testGeminiSkipsThoughtParts(): void
    {
        $out = (new ResponseParser())->gemini([
            'candidates'    => [[
                'content'      => ['parts' => [['text' => 'plan', 'thought' => true], ['text' => 'Answer']]],
                'finishReason' => 'STOP',
            ]],
            'usageMetadata' => ['promptTokenCount' => 7, 'candidatesTokenCount' => 3],
        ]);

        self::assertSame('Answer', $out['text']);
        self::assertSame(3, $out['output_tokens']);
    }

    public function testMissingFieldsGiveEmptyText(): void
    {
        $parser = new ResponseParser();

        self::assertSame('', $parser->anthropic([])['text']);
        self::assertSame('', $parser->openai(['choices' => [['message' => ['content' => null]]]])['text']);
        self::assertSame('', $parser->gemini(['candidates' => []])['text']);
    }

    public function testErrorPrefersProviderMessage(): void
    {
        $parser = new ResponseParser();

        self::assertSame('invalid x-api-key', $parser->error(['error' => ['type' => 'auth', 'message' => 'invalid x-api-key']], ''));
        self::assertSame('Bad gateway', $parser->error([], '<html><b>Bad gateway</b></html>'));
    }
}
