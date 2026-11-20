<?php
/**
 * Copyright (c) 2026 Ievgenii Gryshkun (angeo.dev)
 * MIT License — see LICENSE for full terms.
 */

declare(strict_types=1);

namespace Angeo\AiAssist\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Settings under Stores → Configuration → Angeo AEO → AI Assistant.
 *
 * Global scope only: the assistant runs in the admin, not per store view.
 * The API key is stored encrypted and is never sent to the browser.
 */
class Config
{
    public const XML_ENABLED     = 'angeo_ai_assist/general/enabled';
    public const XML_PROVIDER    = 'angeo_ai_assist/general/provider';
    public const XML_API_KEY     = 'angeo_ai_assist/general/api_key';
    public const XML_MODEL       = 'angeo_ai_assist/general/model';
    public const XML_MAX_TOKENS  = 'angeo_ai_assist/limits/max_output_tokens';
    public const XML_DAILY_LIMIT = 'angeo_ai_assist/limits/daily_limit';
    public const XML_CACHE_DAYS  = 'angeo_ai_assist/limits/cache_days';
    public const XML_TIMEOUT     = 'angeo_ai_assist/limits/timeout';

    public const PROVIDER_ANTHROPIC = 'anthropic';
    public const PROVIDER_OPENAI    = 'openai';
    public const PROVIDER_GEMINI    = 'gemini';

    /** Used when the Model field is empty. Change them without a release in the Model field. */
    public const DEFAULT_MODELS = [
        self::PROVIDER_ANTHROPIC => 'claude-sonnet-5-5',
        self::PROVIDER_OPENAI    => 'gpt-4.1-mini',
        self::PROVIDER_GEMINI    => 'gemini-2.5-flash',
    ];

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_ENABLED);
    }

    /** Enabled and a key is set: the only state in which the panel offers anything. */
    public function isReady(): bool
    {
        return $this->isEnabled() && $this->getApiKey() !== '';
    }

    public function getProvider(): string
    {
        $value = (string) $this->scopeConfig->getValue(self::XML_PROVIDER);

        return isset(self::DEFAULT_MODELS[$value]) ? $value : self::PROVIDER_ANTHROPIC;
    }

    public function getApiKey(): string
    {
        $stored = trim((string) $this->scopeConfig->getValue(self::XML_API_KEY));
        if ($stored === '') {
            return '';
        }

        return trim((string) $this->encryptor->decrypt($stored));
    }

    public function getModel(): string
    {
        $model = trim((string) $this->scopeConfig->getValue(self::XML_MODEL));
        // Model ids are short tokens. Anything else is a typo or an attempt to
        // smuggle a path into the Gemini URL; fall back to the default.
        if ($model === '' || preg_match('/^[A-Za-z0-9._:\-\/]{1,80}$/', $model) !== 1 || str_contains($model, '..')) {
            return self::DEFAULT_MODELS[$this->getProvider()];
        }

        return $model;
    }

    public function getMaxOutputTokens(): int
    {
        return $this->clamp((int) $this->scopeConfig->getValue(self::XML_MAX_TOKENS), 256, 4000, 1200);
    }

    /** Answers one admin user may request per day. Cached answers do not count. */
    public function getDailyLimit(): int
    {
        return $this->clamp((int) $this->scopeConfig->getValue(self::XML_DAILY_LIMIT), 1, 1000, 30);
    }

    public function getCacheDays(): int
    {
        return $this->clamp((int) $this->scopeConfig->getValue(self::XML_CACHE_DAYS), 0, 90, 7);
    }

    public function getTimeout(): int
    {
        return $this->clamp((int) $this->scopeConfig->getValue(self::XML_TIMEOUT), 10, 180, 60);
    }

    public function getProviderLabel(): string
    {
        return match ($this->getProvider()) {
            self::PROVIDER_OPENAI => 'OpenAI',
            self::PROVIDER_GEMINI => 'Google Gemini',
            default               => 'Anthropic Claude',
        };
    }

    private function clamp(int $value, int $min, int $max, int $default): int
    {
        if ($value === 0 && $min > 0) {
            return $default;
        }

        return max($min, min($max, $value));
    }
}
