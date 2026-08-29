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

use Horde\Imap\Client\ConnectionConfig;
use Horde\Imap\Client\Event\MailboxExpunged;
use Horde\Imap\Client\Event\MailboxSelected;
use Horde\Imap\Client\ImapClient;
use Horde\Imap\Client\ImapIdSet;
use Horde\Imap\Client\OpenMode;
use Horde\Sasl\Negotiation\SaslPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The client dispatches MailboxSelected on open and MailboxExpunged on
 * message removal (EXPUNGE and server-side MOVE), so an external cache can
 * react without the library owning any caching policy. This is the
 * behaviour the library relies on to keep search-result caching out of
 * scope (see doc/SEARCH_CACHE.md).
 */
#[CoversClass(ImapClient::class)]
class MailboxEventDispatchTest extends TestCase
{
    /** @var list<object> */
    private array $events = [];

    private function dispatcher(): EventDispatcherInterface
    {
        $sink = &$this->events;

        return new class ($sink) implements EventDispatcherInterface {
            /** @param list<object> $sink */
            public function __construct(private array &$sink) {}

            public function dispatch(object $event): object
            {
                $this->sink[] = $event;

                return $event;
            }
        };
    }

    private function config(): ConnectionConfig
    {
        return new ConnectionConfig(
            hostspec: 'imap.example.test',
            saslPolicy: SaslPolicy::legacyCompatible(),
        );
    }

    private function client(InMemoryImapSocket $socket): ImapClient
    {
        return new ImapClient($this->config(), null, $this->dispatcher(), $socket, null);
    }

    private function socket(string ...$lines): InMemoryImapSocket
    {
        return InMemoryImapSocket::fromParts(
            ...array_map(InMemoryImapSocket::line(...), $lines),
        );
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return list<T>
     */
    private function eventsOf(string $class): array
    {
        return array_values(array_filter(
            $this->events,
            static fn(object $e): bool => $e instanceof $class,
        ));
    }

    public function testOpenMailboxDispatchesMailboxSelectedWithSyncState(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1] Server ready.',
            '* OK [UIDVALIDITY 42] .',
            '* OK [UIDNEXT 100] .',
            '* OK [HIGHESTMODSEQ 715] .',
            'A1 OK [READ-WRITE] SELECT completed.',
        );
        $client = $this->client($socket);

        $client->openMailbox('INBOX', OpenMode::ReadWrite);

        $selected = $this->eventsOf(MailboxSelected::class);
        self::assertCount(1, $selected);
        self::assertSame('INBOX', $selected[0]->mailbox);
        self::assertSame(42, $selected[0]->uidvalidity);
        self::assertSame(100, $selected[0]->uidnext);
        self::assertSame(715, $selected[0]->highestmodseq);
    }

    public function testExpungeDispatchesMailboxExpungedWithVanishedUids(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 QRESYNC] Server ready.',
            '* OK [UIDVALIDITY 42] .',
            'A1 OK [READ-WRITE] SELECT completed.',
            // A QRESYNC connection answers EXPUNGE with VANISHED (UIDs).
            '* VANISHED 405,410:412',
            'A2 OK EXPUNGE completed.',
        );
        $client = $this->client($socket);

        $client->openMailbox('INBOX', OpenMode::ReadWrite);
        $client->expunge('INBOX');

        $expunged = $this->eventsOf(MailboxExpunged::class);
        self::assertCount(1, $expunged);
        self::assertSame('INBOX', $expunged[0]->mailbox);
        self::assertFalse($expunged[0]->vanished->isSequence());
        self::assertSame([405, 410, 411, 412], $expunged[0]->vanished->toArray());
        self::assertSame(42, $expunged[0]->uidvalidity);
    }

    public function testServerMoveDispatchesMailboxExpungedForSource(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 MOVE UIDPLUS QRESYNC] Server ready.',
            '* OK [UIDVALIDITY 42] .',
            'A1 OK [READ-WRITE] SELECT completed.',
            // A server-side MOVE removes the source and reports it via
            // VANISHED on the MOVE reply.
            '* VANISHED 7',
            'A2 OK [COPYUID 1 7 88] MOVE completed.',
        );
        $client = $this->client($socket);

        $client->openMailbox('INBOX', OpenMode::ReadWrite);
        $client->move('INBOX', 'Archive', ['ids' => new ImapIdSet([7], false)]);

        $expunged = $this->eventsOf(MailboxExpunged::class);
        self::assertCount(1, $expunged);
        self::assertSame('INBOX', $expunged[0]->mailbox);
        self::assertSame([7], $expunged[0]->vanished->toArray());
    }
}
