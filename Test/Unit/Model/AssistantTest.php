<?php
/**
 * Copyright (c) 2026 Ievgenii Gryshkun (angeo.dev)
 * MIT License — see LICENSE for full terms.
 */

declare(strict_types=1);

namespace Angeo\AiAssist\Test\Unit\Model;

use Angeo\AiAssist\Model\Assistant;
use Angeo\AiAssist\Model\Config;
use Angeo\AiAssist\Model\ContextCollector;
use Angeo\AiAssist\Model\ContextSanitizer;
use Angeo\AiAssist\Model\PromptBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class AssistantTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $settings;
    private FakeStore $store;
    private FakeProvider $provider;

    protected function setUp(): void
    {
        $this->settings = [
            Config::XML_ENABLED     => '1',
            Config::XML_PROVIDER    => 'anthropic',
            Config::XML_API_KEY     => 'encrypted',
            Config::XML_DAILY_LIMIT => '2',
            Config::XML_CACHE_DAYS  => '7',
        ];
        $this->store    = new FakeStore();
        $this->provider = new FakeProvider();
    }

    private function assistant(): Assistant
    {
        $settings = &$this->settings;
        $scope = new class ($settings) implements ScopeConfigInterface {
            /** @param array<string,mixed> $settings */
            public function __construct(private array &$settings)
            {
            }

            public function getValue($path, $scopeType = self::SCOPE_TYPE_DEFAULT, $scopeCode = null)
            {
                return $this->settings[$path] ?? null;
            }

            public function isSetFlag($path, $scopeType = self::SCOPE_TYPE_DEFAULT, $scopeCode = null)
            {
                return (bool) ($this->settings[$path] ?? false);
            }
        };
        $crypt = new class implements EncryptorInterface {
            public function getHash($password, $salt = false, $version = self::HASH_VERSION_LATEST)
            {
                return '';
            }

            public function hash($data, $version = self::HASH_VERSION_LATEST)
            {
                return '';
            }

            public function validateHash($password, $hash)
            {
                return false;
            }

            public function isValidHash($password, $hash)
            {
                return false;
            }

            public function validateHashVersion($hash, $validateCount = false)
            {
                return false;
            }

            public function encrypt($data)
            {
                return $data;
            }

            public function decrypt($data)
            {
                return $data === 'encrypted' ? 'sk-test' : '';
            }

            public function validateKey($key)
            {
                return null;
            }
        };
        $events = new class implements ManagerInterface {
            public function dispatch($eventName, array $data = [])
            {
                if (($data['source'] ?? '') === 'aeo_audit') {
                    $data['context']->setData(['title' => 'Audit', 'facts' => ['score' => 50]]);
                }
            }
        };
        $sanitizer = new ContextSanitizer();

        return new Assistant(
            new Config($scope, $crypt),
            new ContextCollector($events, $sanitizer),
            new PromptBuilder($sanitizer),
            $this->store,
            ['anthropic' => $this->provider]
        );
    }

    public function testOffMeansNoProviderCall(): void
    {
        $this->settings[Config::XML_ENABLED] = '0';
        try {
            $this->assistant()->ask('aeo_audit', '1', 'explain', '', 5, 'English');
            self::assertTrue(false, 'expected an exception');
        } catch (LocalizedException $e) {
            self::assertStringContainsString('off', $e->getMessage());
        }
        self::assertSame(0, $this->provider->calls);
    }

    public function testAnswerIsStoredThenServedFromCache(): void
    {
        $first  = $this->assistant()->ask('aeo_audit', '1', 'explain', '', 5, 'English');
        $second = $this->assistant()->ask('aeo_audit', '1', 'explain', '', 6, 'English');

        self::assertFalse($first['cached']);
        self::assertTrue($second['cached']);
        self::assertSame(1, $this->provider->calls);
        self::assertSame('sk-test', $this->provider->lastKey);
        self::assertSame('claude-sonnet-5-5', $first['model']);
        self::assertSame(1, $first['remaining']);
    }

    public function testOtherLanguageIsNotTheCachedAnswer(): void
    {
        $this->assistant()->ask('aeo_audit', '1', 'explain', '', 5, 'English');
        $this->assistant()->ask('aeo_audit', '1', 'explain', '', 5, 'Dutch');

        self::assertSame(2, $this->provider->calls);
    }

    public function testDailyLimitStopsNewAnswersButNotCachedOnes(): void
    {
        $this->assistant()->ask('aeo_audit', '1', 'explain', '', 5, 'English');
        $this->assistant()->ask('aeo_audit', '1', 'fix', '', 5, 'English');

        $cached = $this->assistant()->ask('aeo_audit', '1', 'explain', '', 5, 'English');
        self::assertTrue($cached['cached']);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('limit');
        $this->assistant()->ask('aeo_audit', '1', 'ask', 'Why?', 5, 'English');
    }

    public function testCutAnswerIsMarked(): void
    {
        $this->provider->stop = 'max_tokens';
        $answer = $this->assistant()->ask('aeo_audit', '1', 'explain', '', 5, 'English');

        self::assertStringContainsString('cut at the length limit', $answer['answer']);
    }

    public function testUnknownSourceGivesAClearError(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('No data for this page');
        $this->assistant()->ask('brand_visibility', '1', 'explain', '', 5, 'English');
    }
}
