<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Unit\Src;

use Horde\Imap\Client\Event\CacheStored;
use Horde\Imap\Client\Event\ConnectionEstablished;
use Horde\Imap\Client\Event\DiagnosticEvent;
use Horde\Imap\Client\FilteredEventDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[CoversClass(FilteredEventDispatcher::class)]
class FilteredEventDispatcherTest extends TestCase
{
    public function testSuppressesDiagnosticByDefault(): void
    {
        $inner = $this->createMock(EventDispatcherInterface::class);
        $inner->expects($this->never())->method('dispatch');

        $dispatcher = new FilteredEventDispatcher($inner);
        $event = new CacheStored('stored', ['key' => 'uid:42']);

        $result = $dispatcher->dispatch($event);

        $this->assertSame($event, $result);
    }

    public function testPassesLifecycleEventThrough(): void
    {
        $event = new ConnectionEstablished('connected');

        $inner = $this->createMock(EventDispatcherInterface::class);
        $inner->expects($this->once())
            ->method('dispatch')
            ->with($event)
            ->willReturn($event);

        $dispatcher = new FilteredEventDispatcher($inner);
        $result = $dispatcher->dispatch($event);

        $this->assertSame($event, $result);
    }

    public function testEmptySuppressPassesEverything(): void
    {
        $event = new CacheStored('stored');

        $inner = $this->createMock(EventDispatcherInterface::class);
        $inner->expects($this->once())
            ->method('dispatch')
            ->with($event)
            ->willReturn($event);

        $dispatcher = new FilteredEventDispatcher($inner, []);
        $result = $dispatcher->dispatch($event);

        $this->assertSame($event, $result);
    }

    public function testCustomSuppressList(): void
    {
        $inner = $this->createMock(EventDispatcherInterface::class);
        $inner->expects($this->never())->method('dispatch');

        $dispatcher = new FilteredEventDispatcher($inner, [ConnectionEstablished::class]);
        $event = new ConnectionEstablished('connected');

        $result = $dispatcher->dispatch($event);
        $this->assertSame($event, $result);
    }
}
