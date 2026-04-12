<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Unit\Src;

use Horde\Imap\Client\Event\AlertReceived;
use Horde\Imap\Client\Event\AuthenticationFailed;
use Horde\Imap\Client\Event\AuthenticationSucceeded;
use Horde\Imap\Client\Event\CacheDeleted;
use Horde\Imap\Client\Event\CacheRetrieved;
use Horde\Imap\Client\Event\CacheStored;
use Horde\Imap\Client\Event\CapabilityIgnored;
use Horde\Imap\Client\Event\CapabilityNegotiated;
use Horde\Imap\Client\Event\ConnectionClosed;
use Horde\Imap\Client\Event\ConnectionEstablished;
use Horde\Imap\Client\Event\ImapEvent;
use Horde\Imap\Client\Event\MailboxExpunged;
use Horde\Imap\Client\Event\MailboxSelected;
use Horde\Imap\Client\Event\SlowCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(ImapEvent::class)]
#[CoversClass(ConnectionEstablished::class)]
#[CoversClass(CacheStored::class)]
class ImapEventEdgeCaseTest extends TestCase
{
    public function testComplexNestedContext(): void
    {
        $ctx = [
            'server' => ['host' => 'mx.example.com', 'port' => 993],
            'tls' => ['version' => 'TLSv1.3'],
        ];
        $event = new ConnectionEstablished('connected', $ctx);

        $this->assertSame('mx.example.com', $event->getContext()['server']['host']);
        $this->assertSame(993, $event->getContext()['server']['port']);
        $this->assertSame('TLSv1.3', $event->getContext()['tls']['version']);
    }

    public function testContextWithNumericKeys(): void
    {
        $ctx = [0 => 'first', 1 => 'second'];
        $event = new ConnectionEstablished('msg', $ctx);
        $this->assertSame($ctx, $event->getContext());
    }

    public function testContextWithMixedValueTypes(): void
    {
        $ctx = [
            'int' => 42,
            'float' => 3.14,
            'bool' => true,
            'null' => null,
            'array' => [1, 2, 3],
        ];
        $event = new ConnectionEstablished('msg', $ctx);
        $result = $event->getContext();

        $this->assertSame(42, $result['int']);
        $this->assertSame(3.14, $result['float']);
        $this->assertTrue($result['bool']);
        $this->assertNull($result['null']);
        $this->assertSame([1, 2, 3], $result['array']);
    }

    public function testEmptyStringMessageExplicit(): void
    {
        $event = new ConnectionEstablished('');
        $this->assertSame('', $event->getMessage());
    }

    public function testLongMessage(): void
    {
        $msg = str_repeat('a', 10000);
        $event = new ConnectionEstablished($msg);
        $this->assertSame(10000, strlen($event->getMessage()));
    }

    public function testGetMessageReturnsSameValue(): void
    {
        $event = new ConnectionEstablished('test');
        $this->assertSame($event->getMessage(), $event->getMessage());
    }

    public function testGetContextReturnsSameStructure(): void
    {
        $event = new ConnectionEstablished('test', ['k' => 'v']);
        $this->assertSame($event->getContext(), $event->getContext());
    }

    public function testDiagnosticEventInheritsMessageAndContext(): void
    {
        $event = new CacheStored('cache hit', ['key' => 'uid:1']);
        $this->assertSame('cache hit', $event->getMessage());
        $this->assertSame(['key' => 'uid:1'], $event->getContext());
    }

    public static function lifecycleEventClassProvider(): array
    {
        $classes = [
            ConnectionEstablished::class,
            ConnectionClosed::class,
            AuthenticationSucceeded::class,
            AuthenticationFailed::class,
            CapabilityNegotiated::class,
            MailboxSelected::class,
            MailboxExpunged::class,
            AlertReceived::class,
            SlowCommand::class,
        ];
        $pairs = [];
        for ($i = 0; $i < count($classes); $i++) {
            for ($j = $i + 1; $j < count($classes); $j++) {
                $a = (new ReflectionClass($classes[$i]))->getShortName();
                $b = (new ReflectionClass($classes[$j]))->getShortName();
                $pairs["$a vs $b"] = [$classes[$i], $classes[$j]];
            }
        }
        return $pairs;
    }

    #[DataProvider('lifecycleEventClassProvider')]
    public function testEachLifecycleEventIsDistinctClass(string $a, string $b): void
    {
        $this->assertNotSame($a, $b);
    }

    public static function diagnosticEventClassProvider(): array
    {
        $classes = [
            CacheStored::class,
            CacheRetrieved::class,
            CacheDeleted::class,
            CapabilityIgnored::class,
        ];
        $pairs = [];
        for ($i = 0; $i < count($classes); $i++) {
            for ($j = $i + 1; $j < count($classes); $j++) {
                $a = (new ReflectionClass($classes[$i]))->getShortName();
                $b = (new ReflectionClass($classes[$j]))->getShortName();
                $pairs["$a vs $b"] = [$classes[$i], $classes[$j]];
            }
        }
        return $pairs;
    }

    #[DataProvider('diagnosticEventClassProvider')]
    public function testEachDiagnosticEventIsDistinctClass(string $a, string $b): void
    {
        $this->assertNotSame($a, $b);
    }
}
