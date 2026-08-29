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

use DateTimeImmutable;
use Horde\Imap\Client\ConnectionConfig;
use Horde\Imap\Client\Exception\ServerResponseException;
use Horde\Imap\Client\ImapClient;
use Horde\Imap\Client\ImapIdSet;
use Horde\Imap\Client\OpenMode;
use Horde\Imap\Client\SystemFlag;
use Horde\Sasl\Negotiation\SaslPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Store, expunge, copy/move and append on {@see ImapClient}.
 */
#[CoversClass(ImapClient::class)]
class ImapClientMessageOpsTest extends TestCase
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

    public function testStoreAddFlagsSilentByDefault(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1] Server ready.',
            'A1 OK STORE completed.',
        );
        $client = $this->client($socket);

        $result = $client->store('INBOX', [
            'ids' => new ImapIdSet([1, 2, 3], false),
            'add' => [SystemFlag::Seen],
        ]);

        self::assertSame(['A1 UID STORE 1:3 +FLAGS.SILENT (\\seen)'], $socket->written);
        self::assertTrue($result->isEmpty());
    }

    public function testStoreNonSilentAndSequenceMode(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1] Server ready.',
            'A1 OK STORE completed.',
        );
        $client = $this->client($socket);

        $client->store('INBOX', [
            'ids' => new ImapIdSet([5], true),
            'remove' => ['\\Flagged'],
            'silent' => false,
        ]);

        self::assertSame(['A1 STORE 5 -FLAGS (\\Flagged)'], $socket->written);
    }

    public function testStoreReplaceFlags(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1] Server ready.',
            'A1 OK STORE completed.',
        );
        $client = $this->client($socket);

        $client->store('INBOX', [
            'ids' => new ImapIdSet([1], false),
            'replace' => [SystemFlag::Seen, SystemFlag::Flagged],
        ]);

        self::assertSame(['A1 UID STORE 1 FLAGS.SILENT (\\seen \\flagged)'], $socket->written);
    }

    public function testStoreUnchangedSinceReturnsModified(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 CONDSTORE] Server ready.',
            'A1 OK [MODIFIED 7,9] Conditional STORE failed for some.',
        );
        $client = $this->client($socket);

        $result = $client->store('INBOX', [
            'ids' => new ImapIdSet([5, 7, 9], false),
            'add' => [SystemFlag::Seen],
            'unchangedsince' => 320,
        ]);

        self::assertSame(
            ['A1 UID STORE 5,7,9 (UNCHANGEDSINCE 320) +FLAGS.SILENT (\\seen)'],
            $socket->written,
        );
        self::assertSame([7, 9], $result->toArray());
    }

    public function testStoreRecentFlagIsDropped(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1] Server ready.',
            'A1 OK STORE completed.',
        );
        $client = $this->client($socket);

        $client->store('INBOX', [
            'ids' => new ImapIdSet([1], false),
            'add' => [SystemFlag::Seen, SystemFlag::Recent],
        ]);

        self::assertSame(['A1 UID STORE 1 +FLAGS.SILENT (\\seen)'], $socket->written);
    }

    public function testExpungePlainWithoutUidplus(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1] Server ready.',
            '* 3 EXPUNGE',
            '* 4 EXPUNGE',
            'A1 OK EXPUNGE completed.',
        );
        $client = $this->client($socket);

        $result = $client->expunge('INBOX', ['list' => true]);

        self::assertSame(['A1 EXPUNGE'], $socket->written);
        self::assertSame([3, 4], $result->toArray());
    }

    public function testExpungeUidExpungeWithUidplus(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 UIDPLUS] Server ready.',
            '* 1 EXPUNGE',
            'A1 OK EXPUNGE completed.',
        );
        $client = $this->client($socket);

        $client->expunge('INBOX', ['ids' => new ImapIdSet([100, 101], false), 'list' => true]);

        self::assertSame(['A1 UID EXPUNGE 100:101'], $socket->written);
    }

    public function testExpungeDeleteFlagsFirst(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 UIDPLUS] Server ready.',
            'A1 OK STORE completed.',
            'A2 OK EXPUNGE completed.',
        );
        $client = $this->client($socket);

        $client->expunge('INBOX', ['ids' => new ImapIdSet([5], false), 'delete' => true]);

        self::assertSame(
            ['A1 UID STORE 5 +FLAGS.SILENT (\\deleted)', 'A2 UID EXPUNGE 5'],
            $socket->written,
        );
    }

    public function testExpungeWithoutListReturnsEmpty(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1] Server ready.',
            '* 2 EXPUNGE',
            'A1 OK EXPUNGE completed.',
        );
        $client = $this->client($socket);

        $result = $client->expunge('INBOX');

        self::assertTrue($result->isEmpty());
    }

    public function testCopyReturnsCopyUidDestinationSet(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 UIDPLUS] Server ready.',
            'A1 OK [COPYUID 38505 304,319 3956,3957] COPY completed.',
        );
        $client = $this->client($socket);

        $result = $client->copy('Archive', 'Archive', [
            'ids' => new ImapIdSet([304, 319], false),
        ]);

        self::assertSame(['A1 UID COPY 304,319 Archive'], $socket->written);
        self::assertSame([3956, 3957], $result->toArray());
    }

    public function testMoveUsesServerMoveWhenAvailable(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 MOVE UIDPLUS] Server ready.',
            'A1 OK [COPYUID 1 7 42] MOVE completed.',
        );
        $client = $this->client($socket);

        $result = $client->move('Trash', 'Trash', [
            'ids' => new ImapIdSet([7], false),
        ]);

        self::assertSame(['A1 UID MOVE 7 Trash'], $socket->written);
        self::assertSame([42], $result->toArray());
    }

    public function testMoveFallsBackToCopyPlusExpunge(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 UIDPLUS] Server ready.',
            'A1 OK [COPYUID 1 7 42] COPY completed.',
            'A2 OK STORE completed.',
            'A3 OK EXPUNGE completed.',
        );
        $client = $this->client($socket);
        // Establish a selected mailbox so the client-side expunge targets it.
        // (openMailbox not needed; copyOrMove reads selectedMailbox, which
        //  is null here and expunge does not use the name on the wire.)

        $client->move('Trash', 'Trash', [
            'ids' => new ImapIdSet([7], false),
        ]);

        self::assertSame(
            [
                'A1 UID COPY 7 Trash',
                'A2 UID STORE 7 +FLAGS.SILENT (\\deleted)',
                'A3 UID EXPUNGE 7',
            ],
            $socket->written,
        );
    }

    public function testCopyEmptyIdsSendsNothing(): void
    {
        $socket = $this->socket('* OK [CAPABILITY IMAP4rev1] Server ready.');
        $client = $this->client($socket);

        $result = $client->copy('Archive', 'Archive', ['ids' => new ImapIdSet([], false)]);

        self::assertSame([], $socket->written);
        self::assertTrue($result->isEmpty());
    }

    public function testCopyCreatesDestinationOnTryCreate(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 UIDPLUS] Server ready.',
            'A1 NO [TRYCREATE] Mailbox does not exist.',
            'A2 OK CREATE completed.',
            'A3 OK [COPYUID 1 7 42] COPY completed.',
        );
        $client = $this->client($socket);

        $result = $client->copy('Archive', 'Archive', [
            'ids' => new ImapIdSet([7], false),
            'create' => true,
        ]);

        self::assertSame(
            ['A1 UID COPY 7 Archive', 'A2 CREATE Archive', 'A3 UID COPY 7 Archive'],
            $socket->written,
        );
        self::assertSame([42], $result->toArray());
    }

    public function testAppendInlineBodyReturnsAppendUid(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 UIDPLUS] Server ready.',
            'A1 OK [APPENDUID 38505 3955] APPEND completed.',
        );
        $client = $this->client($socket);

        // A short body with no CRLF encodes as a bare atom inline.
        $result = $client->append('Drafts', [['data' => 'hello']]);

        self::assertSame(['A1 APPEND Drafts hello'], $socket->written);
        self::assertSame([3955], $result->toArray());
    }

    public function testAppendWithFlagsAndInternalDateLiteralBody(): void
    {
        $socket = InMemoryImapSocket::fromParts(
            InMemoryImapSocket::line('* OK [CAPABILITY IMAP4rev1 UIDPLUS] Server ready.'),
            InMemoryImapSocket::line('+ Ready for literal data.'),
            InMemoryImapSocket::line('A1 OK [APPENDUID 38505 3955] APPEND completed.'),
        );
        $client = $this->client($socket);

        $body = "Subject: hi\r\n\r\nbody line\r\n";
        $result = $client->append('Drafts', [[
            'data' => $body,
            'flags' => [SystemFlag::Seen],
            'internaldate' => new DateTimeImmutable('2024-02-01T12:00:00+00:00'),
        ]]);

        self::assertSame(
            'A1 APPEND Drafts (\\seen) "1-Feb-2024 12:00:00 +0000" {' . strlen($body) . '}',
            $socket->written[0],
        );
        // The fake socket rtrims trailing CRLF when capturing a write; the
        // literal-size announcement above still reflects the true length.
        self::assertSame(rtrim($body, "\r\n"), $socket->written[1]);
        self::assertSame([3955], $result->toArray());
    }

    public function testAppendWrapsServerRejection(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1] Server ready.',
            'A1 NO Over quota.',
        );
        $client = $this->client($socket);

        $this->expectException(ServerResponseException::class);

        $client->append('Drafts', [['data' => 'hello']]);
    }
}
