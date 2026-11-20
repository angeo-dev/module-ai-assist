<?php
/**
 * Copyright (c) 2026 Ievgenii Gryshkun (angeo.dev)
 * MIT License — see LICENSE for full terms.
 */

declare(strict_types=1);

namespace Angeo\AiAssist\Model\Provider;

/**
 * Reads the answer text and token counts out of each provider's JSON.
 *
 * Kept apart from the HTTP code so every shape is unit tested without a
 * network or a Magento install.
 */
class ResponseParser
{
    /**
     * Anthropic Messages API: content[] blocks of type "text".
     *
     * @param array<string,mixed> $data
     * @return array{text:string,input_tokens:?int,output_tokens:?int,stop:?string}
     */
    public function anthropic(array $data): array
    {
        $text = '';
        foreach ((array) ($data['content'] ?? []) as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text' && is_string($block['text'] ?? null)) {
                $text .= ($text === '' ? '' : "\n") . $block['text'];
            }
        }

        return [
            'text'          => $text,
            'input_tokens'  => $this->int($data['usage']['input_tokens'] ?? null),
            'output_tokens' => $this->int($data['usage']['output_tokens'] ?? null),
            'stop'          => is_string($data['stop_reason'] ?? null) ? $data['stop_reason'] : null,
        ];
    }

    /**
     * OpenAI Chat Completions: choices[0].message.content.
     *
     * @param array<string,mixed> $data
     * @return array{text:string,input_tokens:?int,output_tokens:?int,stop:?string}
     */
    public function openai(array $data): array
    {
        $choice  = $data['choices'][0] ?? [];
        $content = $choice['message']['content'] ?? '';

        return [
            'text'          => is_string($content) ? $content : '',
            'input_tokens'  => $this->int($data['usage']['prompt_tokens'] ?? null),
            'output_tokens' => $this->int($data['usage']['completion_tokens'] ?? null),
            'stop'          => is_string($choice['finish_reason'] ?? null) ? $choice['finish_reason'] : null,
        ];
    }

    /**
     * Gemini generateContent: candidates[0].content.parts[].text.
     *
     * @param array<string,mixed> $data
     * @return array{text:string,input_tokens:?int,output_tokens:?int,stop:?string}
     */
    public function gemini(array $data): array
    {
        $candidate = $data['candidates'][0] ?? [];
        $text      = '';
        foreach ((array) ($candidate['content']['parts'] ?? []) as $part) {
            if (is_array($part) && is_string($part['text'] ?? null) && empty($part['thought'])) {
                $text .= $part['text'];
            }
        }

        return [
            'text'          => $text,
            'input_tokens'  => $this->int($data['usageMetadata']['promptTokenCount'] ?? null),
            'output_tokens' => $this->int($data['usageMetadata']['candidatesTokenCount'] ?? null),
            'stop'          => is_string($candidate['finishReason'] ?? null) ? $candidate['finishReason'] : null,
        ];
    }

    /**
     * The provider's own error text, short, for the admin to read.
     *
     * @param array<string,mixed> $data
     */
    public function error(array $data, string $raw): string
    {
        foreach ([
            $data['error']['message'] ?? null,
            $data['error']['type'] ?? null,
            $data['message'] ?? null,
        ] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return mb_substr($candidate, 0, 300);
            }
        }

        return mb_substr(trim(strip_tags($raw)), 0, 200);
    }

    private function int(mixed $value): ?int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : null);
    }
}
