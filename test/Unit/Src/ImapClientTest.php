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
use Horde\Imap\Client\Exception\AuthenticationException;
use Horde\Imap\Client\Exception\CapabilityNotSupportedException;
use Horde\Imap\Client\Exception\ConnectionException;
use Horde\Imap\Client\Exception\MailboxNotFoundException;
use Horde\Imap\Client\ImapClient;
use Horde\Imap\Client\ImapFetchQuery;
use Horde\Imap\Client\ImapIdSet;
use Horde\Imap\Client\OpenMode;
use Horde\Imap\Client\StatusFlag;
use Horde\Sasl\Credentials\PasswordCredentials;
use Horde\Sasl\Credentials\PlainSecret;
use Horde\Sasl\Negotiation\SaslPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImapClient::class)]
class ImapClientTest extends TestCase
{
    private function config(?SaslPolicy $policy = null): ConnectionConfig
    {
        return new ConnectionConfig(
            hostspec: 'imap.example.test',
            saslPolicy: $policy ?? SaslPolicy::legacyCompatible(),
        );
    }

    private function credentials(string $user = 'alice', string $pass = 'hunter2'): PasswordCredentials
    {
        return new PasswordCredentials($user, new PlainSecret($pass));
    }

    private function client(
        InMemoryImapSocket $socket,
        ?PasswordCredentials $credentials = null,
        ?SaslPolicy $policy = null,
    ): ImapClient {
        return new ImapClient($this->config($policy), $credentials, null, $socket);
    }

    private function socket(string ...$lines): InMemoryImapSocket
    {
        return InMemoryImapSocket::fromParts(
            ...array_map(InMemoryImapSocket::line(...), $lines),
        );
    }

    public function testGetCapabilityReadsGreetingPiggybackWithoutARoundTrip(): void
    {
        $socket = $this->socket('* OK [CAPABILITY IMAP4rev1 AUTH=PLAIN] Server ready.');
        $client = $this->client($socket);

        $capability = $client->getCapability();

        self::assertTrue($capability->query('IMAP4REV1'));
        self::assertTrue($capability->query('AUTH', 'PLAIN'));
        self::assertSame([], $socket->written);
    }

    public function testGetCapabilitySendsCapabilityWhenGreetingCarriesNone(): void
    {
        $socket = $this->socket(
            '* OK Server ready.',
            '* CAPABILITY IMAP4rev1 IDLE',
            'A1 OK CAPABILITY completed.',
        );
        $client = $this->client($socket);

        $capability = $client->getCapability();

        self::assertTrue($capability->query('IDLE'));
        self::assertSame(['A1 CAPABILITY'], $socket->written);
    }

    public function testGetCapabilityIsCachedAfterFirstCall(): void
    {
        $socket = $this->socket(
            '* OK Server ready.',
            '* CAPABILITY IMAP4rev1',
            'A1 OK CAPABILITY completed.',
        );
        $client = $this->client($socket);

        $client->getCapability();
        $client->getCapability();

        self::assertSame(['A1 CAPABILITY'], $socket->written);
    }

    public function testConnectThrowsOnByeGreeting(): void
    {
        $socket = $this->socket('* BYE Too many connections.');
        $client = $this->client($socket);

        $this->expectException(ConnectionException::class);

        $client->getCapability();
    }

    public function testLoginViaSaslPlain(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 AUTH=PLAIN] Server ready.',
            'A1 OK AUTHENTICATE completed.',
            '* CAPABILITY IMAP4rev1',
            'A2 OK CAPABILITY completed.',
        );
        $client = $this->client($socket, $this->credentials('alice', 'hunter2'));

        $client->login();

        self::assertSame([
            'A1 AUTHENTICATE PLAIN ' . base64_encode("\0alice\0hunter2"),
            'A2 CAPABILITY',
        ], $socket->written);
    }

    public function testLoginFallsBackToNativeLoginWhenNoSaslOffered(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1] Server ready.',
            'A1 OK Logged in.',
            '* CAPABILITY IMAP4rev1',
            'A2 OK CAPABILITY completed.',
        );
        $client = $this->client($socket, $this->credentials('alice', 'hunter2'));

        $client->login();

        self::assertSame([
            'A1 LOGIN alice hunter2',
            'A2 CAPABILITY',
        ], $socket->written);
    }

    public function testLoginThrowsWhenLoginDisabledAndNoSaslOffered(): void
    {
        $socket = $this->socket('* OK [CAPABILITY IMAP4rev1 LOGINDISABLED] Server ready.');
        $client = $this->client($socket, $this->credentials());

        $this->expectException(AuthenticationException::class);

        $client->login();
    }

    public function testLoginThrowsWithoutCredentials(): void
    {
        $socket = $this->socket('* OK Server ready.');
        $client = $this->client($socket, null);

        $this->expectException(AuthenticationException::class);

        $client->login();
    }

    public function testLoginIsIdempotent(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 AUTH=PLAIN] Server ready.',
            'A1 OK AUTHENTICATE completed.',
            '* CAPABILITY IMAP4rev1',
            'A2 OK CAPABILITY completed.',
        );
        $client = $this->client($socket, $this->credentials());

        $client->login();
        $client->login();

        self::assertSame(2, count($socket->written));
    }

    public function testLoginNegotiatesRev2WhenOffered(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 AUTH=PLAIN] Server ready.',
            'A1 OK AUTHENTICATE completed.',
            '* CAPABILITY IMAP4rev1 IMAP4rev2',
            'A2 OK CAPABILITY completed.',
            '* ENABLED IMAP4rev2',
            'A3 OK ENABLE completed.',
        );
        $client = $this->client($socket, $this->credentials());

        $client->login();

        self::assertSame([
            'A1 AUTHENTICATE PLAIN ' . base64_encode("\0alice\0hunter2"),
            'A2 CAPABILITY',
            'A3 ENABLE IMAP4rev2',
        ], $socket->written);
    }

    public function testAlreadyPreAuthenticatedGreetingSkipsLogin(): void
    {
        $socket = $this->socket('* PREAUTH Already authenticated as alice.');
        $client = $this->client($socket, $this->credentials());

        $client->login();

        self::assertSame([], $socket->written);
    }

    public function testLogoutSendsLogoutAndClosesConnection(): void
    {
        $socket = $this->socket(
            '* OK Server ready.',
            'A1 OK NOOP completed.',
            '* BYE Logging out.',
            'A2 OK LOGOUT completed.',
        );
        $client = $this->client($socket);

        $client->noop();
        $client->logout();

        self::assertSame(['A1 NOOP', 'A2 LOGOUT'], $socket->written);
    }

    public function testLogoutWithoutPriorConnectionIsANoop(): void
    {
        $socket = $this->socket();
        $client = $this->client($socket);

        $client->logout();

        self::assertSame([], $socket->written);
    }

    public function testNoopSendsNoopCommand(): void
    {
        $socket = $this->socket('* OK Server ready.', 'A1 OK NOOP completed.');
        $client = $this->client($socket);

        $client->noop();

        self::assertSame(['A1 NOOP'], $socket->written);
    }

    public function testOpenMailboxSendsSelectForReadWrite(): void
    {
        $socket = $this->socket(
            '* OK Server ready.',
            '* 10 EXISTS',
            '* 2 RECENT',
            'A1 OK [READ-WRITE] SELECT completed.',
        );
        $client = $this->client($socket);

        $client->openMailbox('INBOX', OpenMode::ReadWrite);

        self::assertSame(['A1 SELECT INBOX'], $socket->written);
        self::assertSame('INBOX', $client->selectedMailbox());
        self::assertTrue($client->isReadWrite());
    }

    public function testOpenMailboxSendsExamineForReadonly(): void
    {
        $socket = $this->socket(
            '* OK Server ready.',
            'A1 OK [READ-ONLY] EXAMINE completed.',
        );
        $client = $this->client($socket);

        $client->openMailbox('INBOX', OpenMode::Readonly);

        self::assertSame(['A1 EXAMINE INBOX'], $socket->written);
        self::assertFalse($client->isReadWrite());
    }

    public function testOpenMailboxWrapsRejectionAsMailboxNotFound(): void
    {
        $socket = $this->socket(
            '* OK Server ready.',
            'A1 NO Mailbox does not exist.',
        );
        $client = $this->client($socket);

        $this->expectException(MailboxNotFoundException::class);

        $client->openMailbox('NoSuchBox', OpenMode::ReadWrite);
    }

    public function testStatusParsesUntaggedStatusResponse(): void
    {
        $socket = $this->socket(
            '* OK Server ready.',
            '* STATUS INBOX (MESSAGES 10 UIDNEXT 100 UNSEEN 3)',
            'A1 OK STATUS completed.',
        );
        $client = $this->client($socket);

        $status = $client->status('INBOX', StatusFlag::All->value);

        self::assertSame(10, $status->messages);
        self::assertSame(100, $status->uidnext);
        self::assertSame(3, $status->unseen);
        self::assertSame(['A1 STATUS INBOX (MESSAGES RECENT UIDNEXT UIDVALIDITY UNSEEN)'], $socket->written);
    }

    public function testStatusRequestsOnlyRequestedItems(): void
    {
        $socket = $this->socket(
            '* OK Server ready.',
            '* STATUS INBOX (MESSAGES 10)',
            'A1 OK STATUS completed.',
        );
        $client = $this->client($socket);

        $client->status('INBOX', StatusFlag::Messages->value);

        self::assertSame(['A1 STATUS INBOX (MESSAGES)'], $socket->written);
    }

    public function testCloseSendsCloseCommand(): void
    {
        $socket = $this->socket(
            '* OK Server ready.',
            'A1 OK [READ-WRITE] SELECT completed.',
            'A2 OK CLOSE completed.',
        );
        $client = $this->client($socket);

        $client->openMailbox('INBOX', OpenMode::ReadWrite);
        $client->close();

        self::assertSame(['A1 SELECT INBOX', 'A2 CLOSE'], $socket->written);
        self::assertNull($client->selectedMailbox());
    }

    public function testUnselectSendsUnselectWhenSupported(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 UNSELECT] Server ready.',
            'A1 OK [READ-WRITE] SELECT completed.',
            'A2 OK UNSELECT completed.',
        );
        $client = $this->client($socket);

        $client->openMailbox('INBOX', OpenMode::ReadWrite);
        $client->unselect();

        self::assertSame(['A1 SELECT INBOX', 'A2 UNSELECT'], $socket->written);
    }

    public function testUnselectThrowsWhenNotSupported(): void
    {
        $socket = $this->socket('* OK [CAPABILITY IMAP4rev1] Server ready.');
        $client = $this->client($socket);

        $this->expectException(CapabilityNotSupportedException::class);

        $client->unselect();
    }

    public function testGetIdsObReturnsEmptySetForNull(): void
    {
        $ids = $this->client($this->socket())->getIdsOb();

        self::assertInstanceOf(ImapIdSet::class, $ids);
        self::assertTrue($ids->isEmpty());
    }

    public function testGetIdsObParsesSequenceString(): void
    {
        $ids = $this->client($this->socket())->getIdsOb('1:3,7');

        self::assertSame([1, 2, 3, 7], $ids->toArray());
        self::assertFalse($ids->isSequence());
    }

    public function testGetIdsObBuildsFromArrayWithSequenceFlag(): void
    {
        $ids = $this->client($this->socket())->getIdsOb([4, 2, 4], true);

        self::assertSame([4, 2], $ids->toArray());
        self::assertTrue($ids->isSequence());
    }

    public function testGetIdsObCopiesExistingSetChangingSequenceFlag(): void
    {
        $client = $this->client($this->socket());
        $original = $client->getIdsOb([1, 2, 3], false);
        $copy = $client->getIdsOb($original, true);

        self::assertNotSame($original, $copy);
        self::assertSame([1, 2, 3], $copy->toArray());
        self::assertTrue($copy->isSequence());
    }

    public function testFetchEmptyIdSetYieldsNothingAndSendsNoCommand(): void
    {
        $socket = $this->socket('* OK Server ready.');
        $client = $this->client($socket);
        $query = (new ImapFetchQuery())->flags();

        $results = iterator_to_array($client->fetch('INBOX', $client->getIdsOb(), $query));

        self::assertSame([], $results);
        self::assertSame([], $socket->written);
    }

    public function testFetchUsesUidFetchAndKeysResultsByUid(): void
    {
        $socket = $this->socket(
            '* OK Server ready.',
            '* 1 FETCH (UID 100 FLAGS (\Seen))',
            '* 2 FETCH (UID 101 FLAGS (\Answered))',
            'A1 OK FETCH completed.',
        );
        $client = $this->client($socket);
        $query = (new ImapFetchQuery())->flags();

        $results = iterator_to_array($client->fetch('INBOX', $client->getIdsOb('100,101'), $query));

        self::assertSame(['A1 UID FETCH 100:101 (FLAGS)'], $socket->written);
        self::assertSame([100, 101], array_keys($results));
        self::assertSame(['\Seen'], $results[100]->getFlags());
        self::assertSame(['\Answered'], $results[101]->getFlags());
    }

    public function testFetchInSequenceModeKeysResultsBySequenceNumber(): void
    {
        $socket = $this->socket(
            '* OK Server ready.',
            '* 1 FETCH (FLAGS (\Seen))',
            '* 2 FETCH (FLAGS (\Draft))',
            'A1 OK FETCH completed.',
        );
        $client = $this->client($socket);
        $query = (new ImapFetchQuery())->flags();

        $results = iterator_to_array($client->fetch('INBOX', $client->getIdsOb('1:2', true), $query));

        self::assertSame(['A1 FETCH 1:2 (FLAGS)'], $socket->written);
        self::assertSame([1, 2], array_keys($results));
    }

    public function testFetchInSequenceModeAppendsUidWhenRequested(): void
    {
        $socket = $this->socket(
            '* OK Server ready.',
            '* 1 FETCH (FLAGS (\Seen) UID 100)',
            'A1 OK FETCH completed.',
        );
        $client = $this->client($socket);
        $query = (new ImapFetchQuery())->flags()->uid();

        $results = iterator_to_array($client->fetch('INBOX', $client->getIdsOb('1', true), $query));

        self::assertSame(['A1 FETCH 1 (FLAGS UID)'], $socket->written);
        self::assertSame([1], array_keys($results));
        self::assertSame(100, $results[1]->getUid());
    }

    public function testFetchSkipsInterleavedNonFetchResponses(): void
    {
        $socket = $this->socket(
            '* OK Server ready.',
            '* 5 EXPUNGE',
            '* 1 FETCH (UID 100 FLAGS (\Seen))',
            'A1 OK FETCH completed.',
        );
        $client = $this->client($socket);
        $query = (new ImapFetchQuery())->flags();

        $results = iterator_to_array($client->fetch('INBOX', $client->getIdsOb('100'), $query));

        self::assertSame([100], array_keys($results));
    }

    public function testImplementsProtocolInterfaces(): void
    {
        $client = $this->client($this->socket());

        self::assertInstanceOf(\Horde\Imap\Client\ImapProtocol::class, $client);
        self::assertInstanceOf(\Horde\Imap\Client\MailboxProtocol::class, $client);
        self::assertInstanceOf(\Horde\Imap\Client\ImapAclAware::class, $client);
        self::assertInstanceOf(\Horde\Imap\Client\ImapQuotaAware::class, $client);
        self::assertInstanceOf(\Horde\Imap\Client\ImapMetadataAware::class, $client);
    }
}
