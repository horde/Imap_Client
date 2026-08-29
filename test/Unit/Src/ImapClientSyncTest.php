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
use Horde\Imap\Client\Exception\CapabilityNotSupportedException;
use Horde\Imap\Client\ImapClient;
use Horde\Imap\Client\ImapIdSet;
use Horde\Imap\Client\ImapResponseParser;
use Horde\Imap\Client\ImapTokenizer;
use Horde\Imap\Client\ImapVanishedParser;
use Horde\Imap\Client\OpenMode;
use Horde\Imap\Client\StatusFlag;
use Horde\Sasl\Negotiation\SaslPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * CONDSTORE/QRESYNC sync primitives.
 */
#[CoversClass(ImapClient::class)]
#[CoversClass(ImapVanishedParser::class)]
class ImapClientSyncTest extends TestCase
{
    private function config(): ConnectionConfig
    {
        return new ConnectionConfig(
            hostspec: 'imap.example.test',
            saslPolicy: SaslPolicy::legacyCompatible(),
        );
    }

    private function client(InMemoryImapSocket $socket): ImapClient
    {
        return new ImapClient($this->config(), null, null, $socket);
    }

    private function socket(string ...$lines): InMemoryImapSocket
    {
        return InMemoryImapSocket::fromParts(
            ...array_map(InMemoryImapSocket::line(...), $lines),
        );
    }

    private function response(string $line): \Horde\Imap\Client\ImapResponse
    {
        $tokens = (new ImapTokenizer($this->socket($line)))->readLine();

        return ImapResponseParser::parse($tokens);
    }

    public function testStatusRequestsHighestModSeqWhenAsked(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 CONDSTORE] Server ready.',
            '* STATUS INBOX (HIGHESTMODSEQ 715194045007)',
            'A1 OK STATUS completed.',
        );
        $client = $this->client($socket);

        $status = $client->status('INBOX', StatusFlag::HighestModSeq->value);

        self::assertSame(['A1 STATUS INBOX (HIGHESTMODSEQ)'], $socket->written);
        self::assertSame(715194045007, $status->highestmodseq);
    }

    public function testStatusAllIncludesHighestModSeqOnlyWithCondstore(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 CONDSTORE] Server ready.',
            '* STATUS INBOX (MESSAGES 3 HIGHESTMODSEQ 42)',
            'A1 OK STATUS completed.',
        );
        $client = $this->client($socket);

        $client->status('INBOX', StatusFlag::All->value);

        self::assertSame(
            ['A1 STATUS INBOX (MESSAGES RECENT UIDNEXT UIDVALIDITY UNSEEN HIGHESTMODSEQ)'],
            $socket->written,
        );
    }

    public function testStatusAllOmitsHighestModSeqWithoutCondstore(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1] Server ready.',
            '* STATUS INBOX (MESSAGES 3)',
            'A1 OK STATUS completed.',
        );
        $client = $this->client($socket);

        $client->status('INBOX', StatusFlag::All->value);

        self::assertSame(
            ['A1 STATUS INBOX (MESSAGES RECENT UIDNEXT UIDVALIDITY UNSEEN)'],
            $socket->written,
        );
    }

    public function testVanishedParserEarlierForm(): void
    {
        $result = ImapVanishedParser::parse([$this->response('* VANISHED (EARLIER) 41,43:45')]);

        self::assertSame([41, 43, 44, 45], $result->toArray());
        self::assertFalse($result->isSequence());
    }

    public function testVanishedParserPlainForm(): void
    {
        $result = ImapVanishedParser::parse([$this->response('* VANISHED 3,5')]);

        self::assertSame([3, 5], $result->toArray());
    }

    public function testEnableQresync(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 CONDSTORE QRESYNC] Server ready.',
            '* ENABLED QRESYNC',
            'A1 OK ENABLE completed.',
        );
        $client = $this->client($socket);

        self::assertTrue($client->enableQresync());
        self::assertSame(['A1 ENABLE QRESYNC'], $socket->written);
    }

    public function testEnableQresyncThrowsWhenUnsupported(): void
    {
        $socket = $this->socket('* OK [CAPABILITY IMAP4rev1] Server ready.');
        $client = $this->client($socket);

        $this->expectException(CapabilityNotSupportedException::class);

        $client->enableQresync();
    }

    public function testVanishedSendsFetchWithChangedSince(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 CONDSTORE QRESYNC] Server ready.',
            '* ENABLED QRESYNC',
            'A1 OK ENABLE completed.',
            '* VANISHED (EARLIER) 300:310,405',
            'A2 OK FETCH completed.',
        );
        $client = $this->client($socket);
        $client->enableQresync();

        $result = $client->vanished('INBOX', 41010);

        self::assertSame('A2 UID FETCH 1:* UID (VANISHED CHANGEDSINCE 41010)', $socket->written[1]);
        self::assertContains(405, $result->toArray());
        self::assertContains(300, $result->toArray());
    }

    public function testVanishedWithExplicitIds(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 CONDSTORE QRESYNC] Server ready.',
            '* ENABLED QRESYNC',
            'A1 OK ENABLE completed.',
            'A2 OK FETCH completed.',
        );
        $client = $this->client($socket);
        $client->enableQresync();

        $client->vanished('INBOX', 100, ['ids' => new ImapIdSet([300, 301, 302], false)]);

        self::assertSame('A2 UID FETCH 300:302 UID (VANISHED CHANGEDSINCE 100)', $socket->written[1]);
    }

    public function testVanishedThrowsWhenQresyncNotEnabled(): void
    {
        $socket = $this->socket('* OK [CAPABILITY IMAP4rev1 CONDSTORE QRESYNC] Server ready.');
        $client = $this->client($socket);

        $this->expectException(CapabilityNotSupportedException::class);

        $client->vanished('INBOX', 100);
    }

    public function testOpenMailboxQresyncBundlesVanishedAndChanges(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 CONDSTORE QRESYNC] Server ready.',
            '* ENABLED QRESYNC',
            'A1 OK ENABLE completed.',
            '* 100 EXISTS',
            '* VANISHED (EARLIER) 41,43:45',
            '* 49 FETCH (UID 117 FLAGS (\\Seen \\Deleted) MODSEQ (12345))',
            'A2 OK [READ-WRITE] SELECT completed.',
        );
        $client = $this->client($socket);
        $client->enableQresync();

        $result = $client->openMailboxQresync('INBOX', OpenMode::ReadWrite, 67890, 90060115194045);

        self::assertSame(
            'A2 SELECT INBOX (QRESYNC (67890 90060115194045))',
            $socket->written[1],
        );
        self::assertSame([41, 43, 44, 45], $result->vanished->toArray());
        self::assertArrayHasKey(117, $result->changed);
        self::assertSame('INBOX', $client->selectedMailbox());
    }

    public function testOpenMailboxQresyncWithKnownUids(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 CONDSTORE QRESYNC] Server ready.',
            '* ENABLED QRESYNC',
            'A1 OK ENABLE completed.',
            'A2 OK [READ-WRITE] SELECT completed.',
        );
        $client = $this->client($socket);
        $client->enableQresync();

        $client->openMailboxQresync(
            'INBOX',
            OpenMode::ReadWrite,
            67890,
            90060,
            new ImapIdSet([1, 2, 3], false),
        );

        self::assertSame(
            'A2 SELECT INBOX (QRESYNC (67890 90060 1:3))',
            $socket->written[1],
        );
    }

    public function testOpenMailboxQresyncThrowsWhenNotEnabled(): void
    {
        $socket = $this->socket('* OK [CAPABILITY IMAP4rev1 CONDSTORE QRESYNC] Server ready.');
        $client = $this->client($socket);

        $this->expectException(CapabilityNotSupportedException::class);

        $client->openMailboxQresync('INBOX', OpenMode::ReadWrite, 1, 2);
    }
}
