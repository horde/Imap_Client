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
use Horde\Imap\Client\SystemFlag;
use Horde\Sasl\Negotiation\SaslPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * APPEND extensions: MULTIAPPEND, literal8, UTF8=ACCEPT
 * wrap, CATENATE.
 */
#[CoversClass(ImapClient::class)]
class ImapClientAppendExtensionsTest extends TestCase
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

    public function testMultiAppendSendsOneCommand(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 MULTIAPPEND UIDPLUS] Server ready.',
            'A1 OK [APPENDUID 38505 3955,3956] APPEND completed.',
        );
        $client = $this->client($socket);

        $result = $client->append('Drafts', [
            ['data' => 'one', 'flags' => [SystemFlag::Seen]],
            ['data' => 'two'],
        ]);

        // Both messages in a single APPEND command.
        self::assertSame(['A1 APPEND Drafts (\\seen) one two'], $socket->written);
        self::assertSame([3955, 3956], $result->toArray());
    }

    public function testWithoutMultiAppendLoopsPerMessage(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 UIDPLUS] Server ready.',
            'A1 OK [APPENDUID 38505 3955] APPEND completed.',
            'A2 OK [APPENDUID 38505 3956] APPEND completed.',
        );
        $client = $this->client($socket);

        $result = $client->append('Drafts', [['data' => 'one'], ['data' => 'two']]);

        self::assertSame(['A1 APPEND Drafts one', 'A2 APPEND Drafts two'], $socket->written);
        self::assertSame([3955, 3956], $result->toArray());
    }

    public function testBinaryBodyUsesLiteral8(): void
    {
        $body = "a\x00b";  // a null byte forces literal8 (~{n})

        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('* OK [CAPABILITY IMAP4rev1 BINARY UIDPLUS] Server ready.'),
            InMemoryImapSocket::line('+ go'),
            InMemoryImapSocket::line('A1 OK [APPENDUID 1 5] APPEND completed.'),
        );
        $client = $this->client($socket);

        $client->append('Drafts', [['data' => $body]]);

        // literal8 marker: ~{3}. (The fake socket rtrims the raw bytes on
        // capture, but the announcement carries the ~ and true length.)
        self::assertSame('A1 APPEND Drafts ~{3}', $socket->written[0]);
    }

    public function testUtf8AcceptWrapsMessage(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev2] Server ready.',
            'A1 OK [APPENDUID 1 5] APPEND completed.',
        );
        $client = $this->client($socket);
        // Prime the enabled state the way login()'s negotiateRev2 would.
        $client->getCapability()->enable('IMAP4REV2');

        $client->append('Drafts', [['data' => 'hi']]);

        // RFC 6855 §4: UTF8 (<literal>) wrapper.
        self::assertSame('A1 APPEND Drafts (UTF8 (hi))', $socket->written[0]);
    }

    public function testCatenateTextAndUrl(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 CATENATE UIDPLUS] Server ready.',
            'A1 OK [APPENDUID 1 5] APPEND completed.',
        );
        $client = $this->client($socket);

        $client->append('Drafts', [[
            'catenate' => [
                ['text' => 'Header: v'],
                ['url' => '/Sent;UID=20/;section=1.2'],
            ],
        ]]);

        self::assertSame(
            ['A1 APPEND Drafts CATENATE (TEXT "Header: v" URL /Sent;UID=20/;section=1.2)'],
            $socket->written,
        );
    }

    public function testCatenateUrlWithoutCapabilityThrows(): void
    {
        $socket = $this->socket('* OK [CAPABILITY IMAP4rev1] Server ready.');
        $client = $this->client($socket);

        $this->expectException(CapabilityNotSupportedException::class);

        $client->append('Drafts', [['catenate' => [['url' => '/Sent;UID=20']]]]);
    }
}
