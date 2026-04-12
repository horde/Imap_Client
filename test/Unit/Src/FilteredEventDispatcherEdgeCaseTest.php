<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Unit\Src;

use Horde\Imap\Client\Event\CacheDeleted;
use Horde\Imap\Client\Event\CacheRetrieved;
use Horde\Imap\Client\Event\CacheStored;
use Horde\Imap\Client\Event\CapabilityIgnored;
use Horde\Imap\Client\Event\ConnectionClosed;
use Horde\Imap\Client\Event\ConnectionEstablished;
use Horde\Imap\Client\Event\DiagnosticEvent;
use Horde\Imap\Client\FilteredEventDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use stdClass;

#[CoversClass(FilteredEventDispatcher::class)]
class FilteredEventDispatcherEdgeCaseTest extends TestCase
{
    public static function diagnosticSubclassProvider(): array
    {
        return [
            'CacheStored' => [new CacheStored()],
            'CacheRetrieved' => [new CacheRetrieved()],
            'CacheDeleted' => [new CacheDeleted()],
            'CapabilityIgnored' => [new CapabilityIgnored()],
        ];
    }

    #[DataProvider('diagnosticSubclassProvider')]
    public function testSuppressDiagnosticBaseSuppressesSubclass(object $event): void
    {
        $inner = $this->createMock(EventDispatcherInterface::class);
        $inner->expects($this->never())->method('dispatch');

        $dispatcher = new FilteredEventDispatcher($inner);
        $result = $dispatcher->dispatch($event);
        $this->assertSame($event, $result);
    }

    public function testMultipleSuppressClasses(): void
    {
        $inner = $this->createMock(EventDispatcherInterface::class);
        $inner->expects($this->never())->method('dispatch');

        $dispatcher = new FilteredEventDispatcher($inner, [
            ConnectionEstablished::class,
            ConnectionClosed::class,
        ]);

        $dispatcher->dispatch(new ConnectionEstablished());
        $dispatcher->dispatch(new ConnectionClosed());
    }

    public function testMultipleSuppressLetOtherEventsThrough(): void
    {
        $event = new CacheStored();

        $inner = $this->createMock(EventDispatcherInterface::class);
        $inner->expects($this->once())
            ->method('dispatch')
            ->with($event)
            ->willReturn($event);

        $dispatcher = new FilteredEventDispatcher($inner, [
            ConnectionEstablished::class,
            ConnectionClosed::class,
        ]);

        $dispatcher->dispatch($event);
    }

    public function testInnerDispatcherExceptionPropagates(): void
    {
        $inner = $this->createMock(EventDispatcherInterface::class);
        $inner->method('dispatch')->willThrowException(new RuntimeException('bus failure'));

        $dispatcher = new FilteredEventDispatcher($inner);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('bus failure');
        $dispatcher->dispatch(new ConnectionEstablished());
    }

    public function testNonImapEventPassesThroughWithDefaultSuppress(): void
    {
        $obj = new stdClass();

        $inner = $this->createMock(EventDispatcherInterface::class);
        $inner->expects($this->once())
            ->method('dispatch')
            ->with($obj)
            ->willReturn($obj);

        $dispatcher = new FilteredEventDispatcher($inner);
        $result = $dispatcher->dispatch($obj);
        $this->assertSame($obj, $result);
    }

    public function testSuppressReturnsSameEventInstance(): void
    {
        $inner = $this->createMock(EventDispatcherInterface::class);
        $event = new CacheStored('msg');

        $dispatcher = new FilteredEventDispatcher($inner);
        $result = $dispatcher->dispatch($event);

        $this->assertSame($event, $result);
    }

    public function testPassthroughReturnsSameEventInstance(): void
    {
        $event = new ConnectionEstablished();

        $inner = $this->createMock(EventDispatcherInterface::class);
        $inner->method('dispatch')->willReturn($event);

        $dispatcher = new FilteredEventDispatcher($inner);
        $result = $dispatcher->dispatch($event);

        $this->assertSame($event, $result);
    }

    public function testInnerDispatcherCanReturnDifferentObject(): void
    {
        $input = new ConnectionEstablished('in');
        $output = new ConnectionEstablished('out');

        $inner = $this->createMock(EventDispatcherInterface::class);
        $inner->method('dispatch')->willReturn($output);

        $dispatcher = new FilteredEventDispatcher($inner);
        $result = $dispatcher->dispatch($input);

        $this->assertSame($output, $result);
        $this->assertNotSame($input, $result);
    }

    public function testDispatcherImplementsPsrInterface(): void
    {
        $inner = $this->createMock(EventDispatcherInterface::class);
        $dispatcher = new FilteredEventDispatcher($inner);

        $this->assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
    }

    public function testEmptySuppressWithDiagnosticEventPassesThrough(): void
    {
        $event = new CapabilityIgnored();

        $inner = $this->createMock(EventDispatcherInterface::class);
        $inner->expects($this->once())
            ->method('dispatch')
            ->with($event)
            ->willReturn($event);

        $dispatcher = new FilteredEventDispatcher($inner, []);
        $dispatcher->dispatch($event);
    }
}
