<?php
/**
 * Copyright (c) 2026 Ievgenii Gryshkun (angeo.dev)
 * MIT License — see LICENSE for full terms.
 */

declare(strict_types=1);

namespace Angeo\AiAssist\Test\Unit\Model;

use Angeo\AiAssist\Model\ContextCollector;
use Angeo\AiAssist\Model\ContextSanitizer;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class ContextCollectorTest extends TestCase
{
    /**
     * @param callable(array<string,mixed>):void $observer
     */
    private function collector(callable $observer): ContextCollector
    {
        $events = new class ($observer) implements ManagerInterface {
            /** @var callable */
            private $observer;

            public function __construct(callable $observer)
            {
                $this->observer = $observer;
            }

            public function dispatch($eventName, array $data = [])
            {
                if ($eventName === ContextCollector::EVENT) {
                    ($this->observer)($data);
                }
            }
        };

        return new ContextCollector($events, new ContextSanitizer());
    }

    public function testObserverFactsBecomeAContext(): void
    {
        $context = $this->collector(static function (array $data): void {
            if ($data['source'] === 'aeo_audit' && $data['ref'] === '7') {
                $data['context']->setData([
                    'title'    => 'Audit #7',
                    'summary'  => 'Readiness',
                    'facts'    => ['score' => 80, 'password' => 'x'],
                    'glossary' => ['weight' => 'how much it counts', 5 => 'dropped'],
                ]);
            }
        })->collect('aeo_audit', '7');

        self::assertSame('Audit #7', $context->getTitle());
        self::assertSame(['score' => 80], $context->getFacts());
        self::assertSame(['weight' => 'how much it counts'], $context->getGlossary());
    }

    public function testNoObserverMeansNoData(): void
    {
        $this->expectException(LocalizedException::class);
        $this->collector(static function (): void {
        })->collect('aeo_audit', '7');
    }

    public function testDeniedIsRefused(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('not allowed');
        $this->collector(static function (array $data): void {
            $data['context']->setData(['denied' => true, 'facts' => ['score' => 1]]);
        })->collect('aeo_audit', '7');
    }

    public function testBadSourceOrRefNeverReachesObservers(): void
    {
        $called = false;
        $collector = $this->collector(static function () use (&$called): void {
            $called = true;
        });
        foreach ([['AEO', '1'], ['aeo_audit', '1 OR 1=1'], ['aeo_audit', ''], ['x', '1']] as [$source, $ref]) {
            try {
                $collector->collect($source, $ref);
            } catch (LocalizedException) {
            }
        }

        self::assertFalse($called);
    }

    public function testSameFactsSameHash(): void
    {
        $observer = static function (array $data): void {
            $data['context']->setData(['title' => 'T', 'facts' => ['a' => 1]]);
        };

        self::assertSame(
            $this->collector($observer)->collect('aeo_audit', '1')->hash(),
            $this->collector($observer)->collect('aeo_audit', '1')->hash()
        );
    }
}
