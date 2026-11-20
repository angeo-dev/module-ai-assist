<?php
/**
 * Copyright (c) 2026 Ievgenii Gryshkun (angeo.dev)
 * MIT License — see LICENSE for full terms.
 */

declare(strict_types=1);

namespace Angeo\AiAssist\Model;

/**
 * Makes the facts a module hands over small and safe to send.
 *
 * The facts come from the store: audit messages, URLs, answers that other AI
 * models gave in a brand-visibility run. They go to an outside AI provider,
 * so they are bounded here, whatever the module sent:
 *
 *  - strings are cut to MAX_STRING characters, control characters removed;
 *  - lists keep their first MAX_ITEMS items, with a note how many were cut;
 *  - nesting deeper than MAX_DEPTH is dropped;
 *  - keys that look like secrets (password, token, key, secret, email,
 *    phone, address, ip) are removed, even if a module sends them by mistake;
 *  - the whole JSON is cut to MAX_BYTES by trimming the longest lists first.
 *
 * Pure PHP, no Magento classes, so it is unit tested directly.
 */
class ContextSanitizer
{
    public const MAX_STRING = 600;
    public const MAX_ITEMS  = 60;
    public const MAX_DEPTH  = 6;
    public const MAX_BYTES  = 24000;

    /**
     * Keys never sent. Compared after camelCase → snake_case: an exact match,
     * or the key ends with "_" + one of them ("customer_email", "auth_token").
     * Not a substring match: "robots_token" is a bot name, not a secret, so
     * only the token words that are secrets are listed.
     */
    private const BLOCKED_KEYS = [
        'password', 'passwd', 'secret', 'api_key', 'apikey', 'token', 'access_token', 'auth_token',
        'refresh_token', 'bearer', 'authorization', 'cookie', 'session_id', 'email', 'e_mail', 'phone',
        'telephone', 'street', 'address', 'ip', 'ip_address', 'remote_addr', 'firstname', 'lastname',
        'first_name', 'last_name', 'customer_name', 'dob', 'card_number', 'iban',
    ];

    /** Bot identifiers end in "_token" but are public; never blocked. */
    private const ALLOWED_KEYS = ['robots_token', 'ua_token', 'ua_tokens'];

    /**
     * @param array<mixed> $facts
     * @return array<mixed>
     */
    public function sanitize(array $facts): array
    {
        $clean = $this->walk($facts, 0);

        // Still too big: halve the longest list until it fits. Order of keys
        // is kept, so the reader (and the model) sees the same structure.
        $guard = 0;
        while (strlen($this->json($clean)) > self::MAX_BYTES && $guard++ < 40) {
            if (!$this->shrinkLongestList($clean)) {
                break;
            }
        }

        if (strlen($this->json($clean)) > self::MAX_BYTES) {
            // A single huge string or flat object: last resort, cut the JSON.
            return ['_truncated' => mb_strcut($this->json($clean), 0, self::MAX_BYTES - 200)];
        }

        return $clean;
    }

    /**
     * @param array<mixed> $facts
     */
    public function json(array $facts): string
    {
        return (string) json_encode(
            $facts,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function walk(mixed $value, int $depth): mixed
    {
        if (is_string($value)) {
            return $this->text($value);
        }
        if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return is_float($value) && !is_finite($value) ? null : $value;
        }
        if (!is_array($value)) {
            // Objects, resources: never sent. A module must hand over arrays.
            return null;
        }
        if ($depth >= self::MAX_DEPTH) {
            return '[nested data left out]';
        }

        $out   = [];
        $isList = array_is_list($value);
        $count = 0;
        foreach ($value as $key => $item) {
            if (!$isList && is_string($key) && $this->isBlockedKey($key)) {
                continue;
            }
            if ($isList && $count >= self::MAX_ITEMS) {
                $out[] = sprintf('[%d more items left out]', count($value) - self::MAX_ITEMS);
                break;
            }
            $out[$isList ? $count : $this->text((string) $key, 80)] = $this->walk($item, $depth + 1);
            $count++;
        }

        return $out;
    }

    public function isBlockedKey(string $key): bool
    {
        $snake = strtolower((string) preg_replace('/(?<=[a-z0-9])([A-Z])/', '_$1', $key));
        $snake = str_replace('-', '_', $snake);
        if (in_array($snake, self::ALLOWED_KEYS, true)) {
            return false;
        }
        foreach (self::BLOCKED_KEYS as $blocked) {
            if ($snake === $blocked || str_ends_with($snake, '_' . $blocked)) {
                return true;
            }
        }

        return false;
    }

    private function text(string $value, int $max = self::MAX_STRING): string
    {
        $value = (string) mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        if (mb_strlen($value) > $max) {
            $value = mb_substr($value, 0, $max) . '…';
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     */
    private function shrinkLongestList(array &$data): bool
    {
        $path = null;
        $best = 1;
        $this->findLongestList($data, [], $path, $best);
        if ($path === null) {
            return false;
        }

        $ref = &$data;
        foreach ($path as $key) {
            $ref = &$ref[$key];
        }
        $keep = max(1, intdiv(count($ref), 2));
        $cut  = count($ref) - $keep;
        $ref  = array_slice($ref, 0, $keep);
        $ref[] = sprintf('[%d more items left out]', $cut);

        return true;
    }

    /**
     * @param array<mixed>           $data
     * @param array<int,int|string>  $at
     * @param array<int,int|string>|null $path
     */
    private function findLongestList(array $data, array $at, ?array &$path, int &$best): void
    {
        if (array_is_list($data) && count($data) > $best) {
            $best = count($data);
            $path = $at;
        }
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->findLongestList($value, array_merge($at, [$key]), $path, $best);
            }
        }
    }
}
