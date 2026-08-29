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
use Horde\Imap\Client\Exception\SyncException;
use Horde\Imap\Client\ImapClient;
use Horde\Imap\Client\SyncCriteria;
use Horde\Sasl\Negotiation\SaslPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The token-based {@see ImapClient::sync()}.
 */
#[CoversClass(ImapClient::class)]
class ImapClientTokenSyncTest extends TestCase
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

    public function testGetSyncTokenEncodesState(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 CONDSTORE] Server ready.',
            '* STATUS INBOX (UIDVALIDITY 42 UIDNEXT 100 HIGHESTMODSEQ 715)',
            'A1 OK STATUS completed.',
        );
        $client = $this->client($socket);

        $token = $client->getSyncToken('INBOX');

        self::assertSame('V42,H715,U100', base64_decode($token, true));
    }

    public function testSyncDetectsNewFlagAndVanished(): void
    {
        $token = base64_encode('V42,H715,U100');
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 CONDSTORE QRESYNC] Server ready.',
            '* ENABLED QRESYNC',
            'A1 OK ENABLE completed.',
            // sync(): STATUS then new-msgs SEARCH, flag SEARCH, vanished FETCH.
            '* STATUS INBOX (UIDVALIDITY 42 UIDNEXT 130 HIGHESTMODSEQ 900)',
            'A2 OK STATUS completed.',
            '* SEARCH 100 101 105',
            'A3 OK SEARCH completed.',
            '* SEARCH 5 9',
            'A4 OK SEARCH completed.',
            '* VANISHED (EARLIER) 1:3',
            'A5 OK FETCH completed.',
        );
        $client = $this->client($socket);
        $client->enableQresync();

        $result = $client->sync('INBOX', $token);

        // written[0]=ENABLE, [1]=STATUS, [2]=new-msgs SEARCH,
        // [3]=flag SEARCH, [4]=vanished FETCH.
        self::assertSame('A3 UID SEARCH UID 100:*', $socket->written[2]);
        self::assertSame('A4 UID SEARCH MODSEQ 716', $socket->written[3]);
        self::assertSame('A5 UID FETCH 1:* UID (VANISHED CHANGEDSINCE 715)', $socket->written[4]);

        self::assertSame([100, 101, 105], $result->newMsgs->toArray());
        self::assertSame([5, 9], $result->flagChanges->toArray());
        self::assertSame([1, 2, 3], $result->vanished->toArray());
    }

    public function testSyncOnlyRequestedCriteria(): void
    {
        $token = base64_encode('V42,H715,U100');
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 CONDSTORE] Server ready.',
            '* STATUS INBOX (UIDVALIDITY 42 UIDNEXT 130 HIGHESTMODSEQ 900)',
            'A1 OK STATUS completed.',
            '* SEARCH 100 101',
            'A2 OK SEARCH completed.',
        );
        $client = $this->client($socket);

        $result = $client->sync('INBOX', $token, [SyncCriteria::NewMessages]);

        self::assertSame(['A1 STATUS INBOX (UIDNEXT UIDVALIDITY HIGHESTMODSEQ)', 'A2 UID SEARCH UID 100:*'], $socket->written);
        self::assertSame([100, 101], $result->newMsgs->toArray());
        self::assertTrue($result->flagChanges->isEmpty());
        self::assertTrue($result->vanished->isEmpty());
    }

    public function testSyncThrowsOnUidValidityChange(): void
    {
        $token = base64_encode('V42,H715,U100');
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 CONDSTORE] Server ready.',
            '* STATUS INBOX (UIDVALIDITY 99 UIDNEXT 130 HIGHESTMODSEQ 900)',
            'A1 OK STATUS completed.',
        );
        $client = $this->client($socket);

        $this->expectException(SyncException::class);

        $client->sync('INBOX', $token);
    }

    public function testSyncThrowsOnMalformedToken(): void
    {
        $socket = $this->socket('* OK [CAPABILITY IMAP4rev1] Server ready.');
        $client = $this->client($socket);

        $this->expectException(SyncException::class);

        $client->sync('INBOX', '!!!not base64!!!');
    }
}
