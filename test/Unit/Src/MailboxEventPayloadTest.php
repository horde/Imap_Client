<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit\Src;

use Horde\Imap\Client\Event\ImapEvent;
use Horde\Imap\Client\Event\MailboxExpunged;
use Horde\Imap\Client\Event\MailboxSelected;
use Horde\Imap\Client\ImapIdSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The typed domain events MailboxExpunged and MailboxSelected. They carry
 * the payload an external cache (for example a search-result cache the
 * library does not own) needs to decide invalidation. They remain
 * plain ImapEvent subclasses so the base getMessage()/getContext()
 * contract is preserved for generic listeners.
 */
#[CoversClass(MailboxExpunged::class)]
#[CoversClass(MailboxSelected::class)]
class MailboxEventPayloadTest extends TestCase
{
    public function testMailboxExpungedExposesTypedFields(): void
    {
        $ids = new ImapIdSet([5, 7, 9], false);
        $event = new MailboxExpunged('INBOX', $ids, 42);

        self::assertSame('INBOX', $event->mailbox);
        self::assertSame($ids, $event->vanished);
        self::assertSame(42, $event->uidvalidity);
        self::assertFalse($event->vanished->isSequence());
    }

    public function testMailboxExpungedPreservesBaseContract(): void
    {
        $event = new MailboxExpunged('INBOX', new ImapIdSet([5, 7], false), 42);

        // A generic ImapEvent listener still gets a message and context.
        self::assertInstanceOf(ImapEvent::class, $event);
        self::assertSame('2 message(s) removed from INBOX', $event->getMessage());
        self::assertSame(
            [
                'mailbox' => 'INBOX',
                'ids' => [5, 7],
                'sequence' => false,
                'uidvalidity' => 42,
            ],
            $event->getContext(),
        );
    }

    public function testMailboxExpungedDistinguishesSequenceNumbers(): void
    {
        // A plain EXPUNGE reports sequence numbers, not UIDs. The event must
        // flag that so a listener invalidates conservatively.
        $event = new MailboxExpunged('INBOX', new ImapIdSet([1, 2], true), 42);

        self::assertTrue($event->vanished->isSequence());
        self::assertTrue($event->getContext()['sequence']);
    }

    public function testMailboxSelectedExposesSyncState(): void
    {
        $event = new MailboxSelected('INBOX', 42, 100, 715);

        self::assertSame('INBOX', $event->mailbox);
        self::assertSame(42, $event->uidvalidity);
        self::assertSame(100, $event->uidnext);
        self::assertSame(715, $event->highestmodseq);
        self::assertSame(
            [
                'mailbox' => 'INBOX',
                'uidvalidity' => 42,
                'uidnext' => 100,
                'highestmodseq' => 715,
            ],
            $event->getContext(),
        );
    }
}
