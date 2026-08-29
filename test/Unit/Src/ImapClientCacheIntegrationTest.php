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
use Horde\Imap\Client\ImapCacheStore;
use Horde\Imap\Client\ImapClient;
use Horde\Imap\Client\ImapFetchQuery;
use Horde\Imap\Client\ImapIdSet;
use Horde\Imap\Client\OpenMode;
use Horde\Imap\Client\SystemFlag;
use Horde\Sasl\Negotiation\SaslPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Transparent opt-in caching across fetch/store/expunge.
 */
#[CoversClass(ImapClient::class)]
#[CoversClass(ImapCacheStore::class)]
class ImapClientCacheIntegrationTest extends TestCase
{
    private function config(): ConnectionConfig
    {
        return new ConnectionConfig(
            hostspec: 'imap.example.test',
            saslPolicy: SaslPolicy::legacyCompatible(),
        );
    }

    private function client(InMemoryImapSocket $socket, ?ImapCacheStore $cache): ImapClient
    {
        return new ImapClient($this->config(), null, null, $socket, $cache);
    }

    private function cache(ArrayCache $backend): ImapCacheStore
    {
        return new ImapCacheStore($backend, 'imap.example.test', 993, 'alice');
    }

    private function socket(string ...$lines): InMemoryImapSocket
    {
        return InMemoryImapSocket::fromParts(
            ...array_map(InMemoryImapSocket::line(...), $lines),
        );
    }

    public function testFetchWritesThroughAndSecondFetchServesFromCache(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1] Server ready.',
            '* OK [UIDVALIDITY 42] .',
            'A1 OK [READ-WRITE] SELECT completed.',
            // First fetch of UIDs 5,7 hits the wire.
            '* 1 FETCH (UID 5 RFC822.SIZE 100 ENVELOPE (NIL "hi" NIL NIL NIL NIL NIL NIL NIL NIL))',
            '* 2 FETCH (UID 7 RFC822.SIZE 200 ENVELOPE (NIL "yo" NIL NIL NIL NIL NIL NIL NIL NIL))',
            'A2 OK FETCH completed.',
        );
        $cache = $this->cache(new ArrayCache());
        $client = $this->client($socket, $cache);

        $client->openMailbox('INBOX', OpenMode::ReadWrite);

        $query = (new ImapFetchQuery())->envelope()->size();
        $first = iterator_to_array($client->fetch('INBOX', new ImapIdSet([5, 7], false), $query));

        self::assertCount(2, $first);
        self::assertSame(100, $first[5]->getSize());
        self::assertSame(200, $first[7]->getSize());

        // Second identical fetch: no new wire command (served from cache).
        $writtenAfterFirst = $socket->written;
        $second = iterator_to_array($client->fetch('INBOX', new ImapIdSet([5, 7], false), $query));

        self::assertSame($writtenAfterFirst, $socket->written, 'no extra FETCH command was sent');
        self::assertCount(2, $second);
        self::assertSame(100, $second[5]->getSize());
        self::assertSame(200, $second[7]->getSize());
    }

    public function testPartialCacheHitFetchesOnlyMissingUids(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1] Server ready.',
            '* OK [UIDVALIDITY 42] .',
            'A1 OK [READ-WRITE] SELECT completed.',
            '* 1 FETCH (UID 5 RFC822.SIZE 100)',
            'A2 OK FETCH completed.',
            // Second fetch: UID 5 cached, UID 9 fetched.
            '* 2 FETCH (UID 9 RFC822.SIZE 300)',
            'A3 OK FETCH completed.',
        );
        $cache = $this->cache(new ArrayCache());
        $client = $this->client($socket, $cache);

        $client->openMailbox('INBOX', OpenMode::ReadWrite);
        $query = (new ImapFetchQuery())->size();

        iterator_to_array($client->fetch('INBOX', new ImapIdSet([5], false), $query));
        $result = iterator_to_array($client->fetch('INBOX', new ImapIdSet([5, 9], false), $query));

        // Only the missing UID 9 was fetched the second time.
        self::assertSame('A3 UID FETCH 9 (RFC822.SIZE)', $socket->written[2]);
        self::assertSame(100, $result[5]->getSize());
        self::assertSame(300, $result[9]->getSize());
    }

    public function testStaleFlagsRefetchedWhenModseqAdvances(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 CONDSTORE] Server ready.',
            '* OK [UIDVALIDITY 42] .',
            '* OK [HIGHESTMODSEQ 100] .',
            'A1 OK [READ-WRITE] SELECT completed.',
            '* 1 FETCH (UID 5 FLAGS (\\Seen) MODSEQ (100))',
            'A2 OK FETCH completed.',
            // Reopen with a higher HIGHESTMODSEQ: cached flags are stale.
            '* OK [UIDVALIDITY 42] .',
            '* OK [HIGHESTMODSEQ 105] .',
            'A3 OK [READ-WRITE] SELECT completed.',
            '* 1 FETCH (UID 5 FLAGS (\\Seen \\Answered) MODSEQ (104))',
            'A4 OK FETCH completed.',
        );
        $cache = $this->cache(new ArrayCache());
        $client = $this->client($socket, $cache);

        $query = (new ImapFetchQuery())->flags()->modseq();

        $client->openMailbox('INBOX', OpenMode::ReadWrite);
        iterator_to_array($client->fetch('INBOX', new ImapIdSet([5], false), $query));

        $client->openMailbox('INBOX', OpenMode::ReadWrite);
        $result = iterator_to_array($client->fetch('INBOX', new ImapIdSet([5], false), $query));

        // written: [0]=A1 SELECT, [1]=A2 FETCH, [2]=A3 SELECT, [3]=A4 FETCH.
        // A second FETCH was sent (A4), because the cached flags were stale.
        self::assertSame('A4 UID FETCH 5 (FLAGS MODSEQ)', $socket->written[3]);
        self::assertContains('\\Answered', $result[5]->getFlags());
    }

    public function testStoreInvalidatesCachedEntry(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1] Server ready.',
            '* OK [UIDVALIDITY 42] .',
            'A1 OK [READ-WRITE] SELECT completed.',
            '* 1 FETCH (UID 5 RFC822.SIZE 100)',
            'A2 OK FETCH completed.',
            'A3 OK STORE completed.',
            // After the store, the cache entry was dropped, so refetch.
            '* 1 FETCH (UID 5 RFC822.SIZE 100)',
            'A4 OK FETCH completed.',
        );
        $cache = $this->cache(new ArrayCache());
        $client = $this->client($socket, $cache);

        $client->openMailbox('INBOX', OpenMode::ReadWrite);
        $query = (new ImapFetchQuery())->size();

        iterator_to_array($client->fetch('INBOX', new ImapIdSet([5], false), $query));
        $client->store('INBOX', ['ids' => new ImapIdSet([5], false), 'add' => [SystemFlag::Seen]]);
        iterator_to_array($client->fetch('INBOX', new ImapIdSet([5], false), $query));

        // The post-store fetch went to the wire (A4), not the cache.
        self::assertSame('A4 UID FETCH 5 (RFC822.SIZE)', $socket->written[3]);
    }

    public function testNoCacheBehavesAsBefore(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1] Server ready.',
            '* OK [UIDVALIDITY 42] .',
            'A1 OK [READ-WRITE] SELECT completed.',
            '* 1 FETCH (UID 5 RFC822.SIZE 100)',
            'A2 OK FETCH completed.',
            '* 1 FETCH (UID 5 RFC822.SIZE 100)',
            'A3 OK FETCH completed.',
        );
        $client = $this->client($socket, null);

        $client->openMailbox('INBOX', OpenMode::ReadWrite);
        $query = (new ImapFetchQuery())->size();

        iterator_to_array($client->fetch('INBOX', new ImapIdSet([5], false), $query));
        iterator_to_array($client->fetch('INBOX', new ImapIdSet([5], false), $query));

        // Without a cache, both fetches hit the wire.
        self::assertSame('A2 UID FETCH 5 (RFC822.SIZE)', $socket->written[1]);
        self::assertSame('A3 UID FETCH 5 (RFC822.SIZE)', $socket->written[2]);
    }

    public function testStreamContentQueryBypassesCache(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1] Server ready.',
            '* OK [UIDVALIDITY 42] .',
            'A1 OK [READ-WRITE] SELECT completed.',
            '* 1 FETCH (UID 5 BODY[] {5}',
            "hello)",
            'A2 OK FETCH completed.',
            '* 1 FETCH (UID 5 BODY[] {5}',
            "hello)",
            'A3 OK FETCH completed.',
        );
        $cache = $this->cache(new ArrayCache());
        $client = $this->client($socket, $cache);

        $client->openMailbox('INBOX', OpenMode::ReadWrite);
        $query = (new ImapFetchQuery())->fullMsg();

        iterator_to_array($client->fetch('INBOX', new ImapIdSet([5], false), $query));
        iterator_to_array($client->fetch('INBOX', new ImapIdSet([5], false), $query));

        // Body content is never cached: both fetches hit the wire.
        self::assertSame('A3 UID FETCH 5 (BODY.PEEK[])', $socket->written[2]);
    }

    public function testHeaderFieldGroupsAreCached(): void
    {
        $headerText = "From: a@b\r\nSubject: hi\r\n";
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('* OK [CAPABILITY IMAP4rev1] Server ready.'),
            InMemoryImapSocket::line('* OK [UIDVALIDITY 42] .'),
            InMemoryImapSocket::line('A1 OK [READ-WRITE] SELECT completed.'),
            InMemoryImapSocket::line('* 1 FETCH (UID 5 BODY[HEADER.FIELDS (FROM SUBJECT)] {' . strlen($headerText) . '}'),
            $headerText . ")\r\n",
            InMemoryImapSocket::line('A2 OK FETCH completed.'),
        );
        $cache = $this->cache(new ArrayCache());
        $client = $this->client($socket, $cache);

        $client->openMailbox('INBOX', OpenMode::ReadWrite);
        $query = (new ImapFetchQuery())->headers('std', ['From', 'Subject']);

        // First fetch populates the cache.
        $first = iterator_to_array($client->fetch('INBOX', new ImapIdSet([5], false), $query));
        self::assertSame('a@b', $first[5]->getHeaders('std')->get('From')->value());

        $writtenAfterFirst = $socket->written;

        // Second fetch is served from cache: no new FETCH command and the
        // reconstructed header group parses back to the same values.
        $second = iterator_to_array($client->fetch('INBOX', new ImapIdSet([5], false), $query));

        self::assertSame($writtenAfterFirst, $socket->written, 'no extra FETCH command was sent');
        self::assertSame('a@b', $second[5]->getHeaders('std')->get('From')->value());
        self::assertSame('hi', $second[5]->getHeaders('std')->get('Subject')->value());
    }
}
