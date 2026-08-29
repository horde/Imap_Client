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
use Horde\Imap\Client\Exception\ImapProtocolException;
use Horde\Imap\Client\ImapClient;
use Horde\Imap\Client\ImapCommand;
use Horde\Imap\Client\ImapConnection;
use Horde\Imap\Client\ImapInteraction;
use Horde\Imap\Client\ImapWireString;
use Horde\Imap\Client\MailboxListMode;
use Horde\Imap\Client\StatusFlag;
use Horde\Sasl\Negotiation\SaslPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Concurrent command pipelining (RFC 3501 §5.5) and its
 * two consumers, statusMultiple() and listMailboxesMulti().
 */
#[CoversClass(ImapClient::class)]
#[CoversClass(ImapInteraction::class)]
class ImapClientPipelineTest extends TestCase
{
    private function client(InMemoryImapSocket $socket): ImapClient
    {
        return new ImapClient(
            new ConnectionConfig(hostspec: 'imap.example.test', saslPolicy: SaslPolicy::legacyCompatible()),
            null,
            null,
            $socket,
        );
    }

    private function socket(string ...$lines): InMemoryImapSocket
    {
        return InMemoryImapSocket::fromParts(
            ...array_map(InMemoryImapSocket::line(...), $lines),
        );
    }

    public function testSendPipelineWritesAllThenCollectsByTag(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('* STATUS INBOX (MESSAGES 3)'),
            InMemoryImapSocket::line('A1 OK STATUS completed.'),
            InMemoryImapSocket::line('* STATUS Sent (MESSAGES 9)'),
            InMemoryImapSocket::line('A2 OK STATUS completed.'),
        );
        $interaction = new ImapInteraction(new ImapConnection($socket));

        $c1 = new ImapCommand('A1', 'STATUS', [new ImapWireString('INBOX'), '(MESSAGES)']);
        $c2 = new ImapCommand('A2', 'STATUS', [new ImapWireString('Sent'), '(MESSAGES)']);
        $results = $interaction->sendPipeline([$c1, $c2]);

        // Both commands written before any response was read.
        self::assertSame(['A1 STATUS INBOX (MESSAGES)', 'A2 STATUS Sent (MESSAGES)'], $socket->written);
        self::assertArrayHasKey('A1', $results);
        self::assertArrayHasKey('A2', $results);
        // Untagged routed to the right command by completion order.
        self::assertCount(1, $results['A1']->untagged);
        self::assertCount(1, $results['A2']->untagged);
    }

    public function testSendPipelineRefusesLiteralCommand(): void
    {
        $interaction = new ImapInteraction(new ImapConnection($this->socket()));

        // A CRLF body forces a synchronizing literal, which cannot pipeline.
        $command = new ImapCommand('A1', 'APPEND', [new ImapWireString("a\r\nb")]);

        $this->expectException(ImapProtocolException::class);

        $interaction->sendPipeline([$command]);
    }

    public function testStatusMultiplePipelinesAllMailboxes(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1] Server ready.',
            '* STATUS INBOX (MESSAGES 10 UNSEEN 2)',
            'A1 OK STATUS completed.',
            '* STATUS Sent (MESSAGES 5 UNSEEN 0)',
            'A2 OK STATUS completed.',
        );
        $client = $this->client($socket);

        $result = $client->statusMultiple(['INBOX', 'Sent'], StatusFlag::Messages->value | StatusFlag::Unseen->value);

        self::assertSame(
            ['A1 STATUS INBOX (MESSAGES UNSEEN)', 'A2 STATUS Sent (MESSAGES UNSEEN)'],
            $socket->written,
        );
        self::assertSame(10, $result['INBOX']->messages);
        self::assertSame(5, $result['Sent']->messages);
    }

    public function testStatusMultipleSkipsRejectedMailbox(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1] Server ready.',
            '* STATUS INBOX (MESSAGES 10)',
            'A1 OK STATUS completed.',
            'A2 NO Mailbox does not exist.',
        );
        $client = $this->client($socket);

        $result = $client->statusMultiple(['INBOX', 'Bogus'], StatusFlag::Messages->value);

        self::assertArrayHasKey('INBOX', $result);
        self::assertArrayNotHasKey('Bogus', $result);
    }

    public function testListMailboxesMultiPipelinesPatterns(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 LIST-EXTENDED] Server ready.',
            '* LIST (\\HasNoChildren) "/" "INBOX"',
            'A1 OK LIST completed.',
            '* LIST (\\HasChildren) "/" "Work"',
            'A2 OK LIST completed.',
        );
        $client = $this->client($socket);

        $result = $client->listMailboxesMulti(['INBOX', 'Work%'], MailboxListMode::All);

        self::assertSame(
            ['A1 LIST "" INBOX', 'A2 LIST "" Work%'],
            $socket->written,
        );
        self::assertArrayHasKey('INBOX', $result);
        self::assertArrayHasKey('Work', $result);
    }

    public function testListMailboxesMultiSinglePatternDelegates(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 LIST-EXTENDED] Server ready.',
            '* LIST (\\HasNoChildren) "/" "INBOX"',
            'A1 OK LIST completed.',
        );
        $client = $this->client($socket);

        $result = $client->listMailboxesMulti(['INBOX'], MailboxListMode::All);

        self::assertSame(['A1 LIST "" INBOX'], $socket->written);
        self::assertArrayHasKey('INBOX', $result);
    }
}
