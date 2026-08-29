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
use Horde\Imap\Client\Event\DiagnosticEvent;
use Horde\Imap\Client\Event\ImapEvent;
use Horde\Imap\Client\Event\MailboxExpunged;
use Horde\Imap\Client\Event\MailboxSelected;
use Horde\Imap\Client\Event\SlowCommand;
use Horde\Imap\Client\ImapIdSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImapEvent::class)]
#[CoversClass(ConnectionEstablished::class)]
#[CoversClass(ConnectionClosed::class)]
#[CoversClass(AuthenticationSucceeded::class)]
#[CoversClass(AuthenticationFailed::class)]
#[CoversClass(CapabilityNegotiated::class)]
#[CoversClass(MailboxSelected::class)]
#[CoversClass(MailboxExpunged::class)]
#[CoversClass(AlertReceived::class)]
#[CoversClass(SlowCommand::class)]
#[CoversClass(DiagnosticEvent::class)]
#[CoversClass(CacheStored::class)]
#[CoversClass(CacheRetrieved::class)]
#[CoversClass(CacheDeleted::class)]
#[CoversClass(CapabilityIgnored::class)]
class EventHierarchyTest extends TestCase
{
    public function testImapEventMessageAndContext(): void
    {
        $event = new ConnectionEstablished('connected', ['host' => 'mail.example.com']);
        $this->assertSame('connected', $event->getMessage());
        $this->assertSame(['host' => 'mail.example.com'], $event->getContext());
    }

    public function testImapEventDefaults(): void
    {
        $event = new ConnectionClosed();
        $this->assertSame('', $event->getMessage());
        $this->assertSame([], $event->getContext());
    }

    public static function lifecycleEventProvider(): array
    {
        return [
            [ConnectionEstablished::class],
            [ConnectionClosed::class],
            [AuthenticationSucceeded::class],
            [AuthenticationFailed::class],
            [CapabilityNegotiated::class],
            [AlertReceived::class],
            [SlowCommand::class],
        ];
    }

    #[DataProvider('lifecycleEventProvider')]
    public function testLifecycleEventsExtendImapEvent(string $class): void
    {
        $event = new $class();
        $this->assertInstanceOf(ImapEvent::class, $event);
        $this->assertNotInstanceOf(DiagnosticEvent::class, $event);
    }

    /**
     * MailboxSelected and MailboxExpunged carry typed payloads so they are
     * asserted separately from the zero-argument lifecycle events. They are
     * still plain ImapEvent (non-diagnostic) subclasses so the default
     * FilteredEventDispatcher lets them through to listeners.
     */
    public function testTypedDomainEventsExtendImapEvent(): void
    {
        $selected = new MailboxSelected('INBOX', 42, 100, 715);
        $this->assertInstanceOf(ImapEvent::class, $selected);
        $this->assertNotInstanceOf(DiagnosticEvent::class, $selected);

        $expunged = new MailboxExpunged('INBOX', new ImapIdSet([5], false), 42);
        $this->assertInstanceOf(ImapEvent::class, $expunged);
        $this->assertNotInstanceOf(DiagnosticEvent::class, $expunged);
    }

    public static function diagnosticEventProvider(): array
    {
        return [
            [CacheStored::class],
            [CacheRetrieved::class],
            [CacheDeleted::class],
            [CapabilityIgnored::class],
        ];
    }

    #[DataProvider('diagnosticEventProvider')]
    public function testDiagnosticEventsExtendBoth(string $class): void
    {
        $event = new $class();
        $this->assertInstanceOf(DiagnosticEvent::class, $event);
        $this->assertInstanceOf(ImapEvent::class, $event);
    }
}
