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
use Horde\Imap\Client\ImapClient;
use Horde\Imap\Client\ImapIdSet;
use Horde\Imap\Client\MailboxListMode;
use Horde\Imap\Client\StatusFlag;
use Horde\Sasl\Negotiation\SaslPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Expunge VANISHED reporting, deleteMailbox empty-then-retry and the listMailboxes
 * extended options plus LIST-STATUS.
 */
#[CoversClass(ImapClient::class)]
class ImapClientDeferredGapsTest extends TestCase
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

    public function testExpungeReportsVanishedUidsUnderQresync(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 CONDSTORE QRESYNC UIDPLUS] Server ready.',
            '* ENABLED QRESYNC',
            'A1 OK ENABLE completed.',
            // A QRESYNC connection answers EXPUNGE with VANISHED (UIDs).
            '* VANISHED 405,410:412',
            'A2 OK EXPUNGE completed.',
        );
        $client = $this->client($socket);
        $client->enableQresync();

        $result = $client->expunge('INBOX', ['list' => true]);

        // UIDs, not sequence numbers.
        self::assertSame([405, 410, 411, 412], $result->toArray());
        self::assertFalse($result->isSequence());
    }

    public function testExpungeStillReadsPlainExpungeWhenNoVanished(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1] Server ready.',
            '* 3 EXPUNGE',
            '* 4 EXPUNGE',
            'A1 OK EXPUNGE completed.',
        );
        $client = $this->client($socket);

        $result = $client->expunge('INBOX', ['list' => true]);

        self::assertSame([3, 4], $result->toArray());
        self::assertTrue($result->isSequence());
    }

    public function testDeleteMailboxEmptiesAndRetriesOnRejection(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 UIDPLUS] Server ready.',
            'A1 NO Mailbox has messages.',           // first DELETE rejected
            'A2 OK [READ-WRITE] SELECT completed.',  // openMailbox
            'A3 OK STORE completed.',                // flag \Deleted
            'A4 OK EXPUNGE completed.',              // UID EXPUNGE 1:*
            'A5 OK CLOSE completed.',                // close
            'A6 OK DELETE completed.',               // retried DELETE
        );
        $client = $this->client($socket);

        $client->deleteMailbox('Old/Stuff');

        self::assertSame(
            [
                'A1 DELETE Old/Stuff',
                'A2 SELECT Old/Stuff',
                'A3 UID STORE 1:* +FLAGS.SILENT (\\deleted)',
                'A4 UID EXPUNGE 1:*',
                'A5 CLOSE',
                'A6 DELETE Old/Stuff',
            ],
            $socket->written,
        );
    }

    public function testDeleteMailboxSucceedsWithoutRetryNormally(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1] Server ready.',
            'A1 OK DELETE completed.',
        );
        $client = $this->client($socket);

        $client->deleteMailbox('Old/Stuff');

        self::assertSame(['A1 DELETE Old/Stuff'], $socket->written);
    }

    public function testListMailboxesChildrenAndSpecialUseReturnOptions(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 LIST-EXTENDED] Server ready.',
            '* LIST (\\HasChildren \\Sent) "/" "Sent"',
            'A1 OK LIST completed.',
        );
        $client = $this->client($socket);

        $result = $client->listMailboxes('%', MailboxListMode::All, [
            'children' => true,
            'special_use' => true,
        ]);

        self::assertSame(
            ['A1 LIST "" % RETURN (CHILDREN SPECIAL-USE)'],
            $socket->written,
        );
        self::assertContains('\\sent', $result['Sent']['attributes']);
    }

    public function testListMailboxesRemoteAndRecursiveMatchSelectOptions(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 LIST-EXTENDED] Server ready.',
            '* LIST (\\Subscribed) "/" "INBOX"',
            'A1 OK LIST completed.',
        );
        $client = $this->client($socket);

        $client->listMailboxes('%', MailboxListMode::Subscribed, [
            'remote' => true,
            'recursivematch' => true,
        ]);

        self::assertSame(
            ['A1 LIST (SUBSCRIBED REMOTE RECURSIVEMATCH) "" % RETURN (SUBSCRIBED)'],
            $socket->written,
        );
    }

    public function testListMailboxesWithStatusReturnAndParsing(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 LIST-EXTENDED LIST-STATUS] Server ready.',
            '* LIST (\\HasNoChildren) "/" "INBOX"',
            '* STATUS "INBOX" (MESSAGES 42 UNSEEN 3)',
            'A1 OK LIST completed.',
        );
        $client = $this->client($socket);

        $result = $client->listMailboxes('%', MailboxListMode::All, [
            'status' => StatusFlag::Messages->value | StatusFlag::Unseen->value,
        ]);

        self::assertSame(
            ['A1 LIST "" % RETURN (STATUS (MESSAGES UNSEEN))'],
            $socket->written,
        );
        self::assertSame(42, $result['INBOX']['status']['messages']);
        self::assertSame(3, $result['INBOX']['status']['unseen']);
    }

    public function testListStatusOptionIgnoredWithoutCapability(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 LIST-EXTENDED] Server ready.',
            '* LIST (\\HasNoChildren) "/" "INBOX"',
            'A1 OK LIST completed.',
        );
        $client = $this->client($socket);

        $result = $client->listMailboxes('%', MailboxListMode::All, [
            'status' => StatusFlag::Messages->value,
        ]);

        // No RETURN (STATUS ...) since the server lacks LIST-STATUS.
        self::assertSame(['A1 LIST "" %'], $socket->written);
        self::assertArrayNotHasKey('status', $result['INBOX']);
    }
}
