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
use Horde\Imap\Client\Exception\ServerResponseException;
use Horde\Imap\Client\ImapClient;
use Horde\Imap\Client\ImapSearchQuery;
use Horde\Sasl\Negotiation\SaslPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * BADCHARSET retry for non-UTF8 connections (RFC 3501
 * §6.4.4). A search whose non-ASCII text the server rejects in one
 * charset is re-encoded and retried in a charset the server accepts.
 */
#[CoversClass(ImapClient::class)]
class ImapClientBadCharsetTest extends TestCase
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

    public function testSearchRetriesInServerAcceptedCharset(): void
    {
        $utf8 = 'naïve';                                   // 6 bytes UTF-8
        $iso = mb_convert_encoding($utf8, 'ISO-8859-1', 'UTF-8'); // 5 bytes

        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('* OK [CAPABILITY IMAP4rev1] Server ready.'),
            // First attempt: CHARSET UTF-8, rejected with BADCHARSET.
            InMemoryImapSocket::line('+ send literal'),
            InMemoryImapSocket::line('A1 NO [BADCHARSET (US-ASCII ISO-8859-1)] Unsupported charset.'),
            // Retry: CHARSET ISO-8859-1, accepted.
            InMemoryImapSocket::line('+ send literal'),
            InMemoryImapSocket::line('* SEARCH 4 8'),
            InMemoryImapSocket::line('A2 OK SEARCH completed.'),
        );
        $client = $this->client($socket);

        $result = $client->search('INBOX', (new ImapSearchQuery())->text($utf8));

        // First send announced a 6-byte UTF-8 literal.
        self::assertSame('A1 UID SEARCH CHARSET UTF-8 BODY {' . strlen($utf8) . '}', $socket->written[0]);
        // Retry announced a 5-byte ISO-8859-1 literal with the re-encoded value.
        self::assertSame('A2 UID SEARCH CHARSET ISO-8859-1 BODY {' . strlen($iso) . '}', $socket->written[3]);
        self::assertSame($iso, $socket->written[4]);
        self::assertSame([4, 8], $result->match->toArray());
    }

    public function testSearchDoesNotRetryOnNonBadcharsetRejection(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('* OK [CAPABILITY IMAP4rev1] Server ready.'),
            InMemoryImapSocket::line('+ send literal'),
            InMemoryImapSocket::line('A1 NO Some other error.'),
        );
        $client = $this->client($socket);

        $this->expectException(ServerResponseException::class);

        $client->search('INBOX', (new ImapSearchQuery())->text('naïve'));
    }

    public function testSearchDoesNotRetryWhenNoAlternativeCharsetOffered(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('* OK [CAPABILITY IMAP4rev1] Server ready.'),
            InMemoryImapSocket::line('+ send literal'),
            // BADCHARSET whose only offered charset is the one we already sent.
            InMemoryImapSocket::line('A1 NO [BADCHARSET (UTF-8)] Unsupported.'),
        );
        $client = $this->client($socket);

        $this->expectException(ServerResponseException::class);

        $client->search('INBOX', (new ImapSearchQuery())->text('naïve'));
    }
}
