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
use Horde\Imap\Client\Exception\ConnectionException;
use Horde\Imap\Client\Exception\Pop3ProtocolException;
use Horde\Imap\Client\Pop3Client;
use Horde\Imap\Client\Pop3FetchQuery;
use Horde\Imap\Client\Pop3IdSet;
use Horde\Imap\Client\SecureMode;
use Horde\Imap\Client\StatusFlag;
use Horde\Imap\Client\SystemFlag;
use Horde\Sasl\Credentials\PasswordCredentials;
use Horde\Sasl\Credentials\PlainSecret;
use Horde\Sasl\Negotiation\SaslPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Pop3Client::class)]
class Pop3ClientTest extends TestCase
{
    private function config(?SaslPolicy $policy = null): ConnectionConfig
    {
        return new ConnectionConfig(
            hostspec: 'pop3.example.test',
            saslPolicy: $policy ?? SaslPolicy::legacyCompatible(),
        );
    }

    private function credentials(string $user = 'alice', string $pass = 'hunter2'): PasswordCredentials
    {
        return new PasswordCredentials($user, new PlainSecret($pass));
    }

    private function client(InMemoryPop3Socket $socket, ?PasswordCredentials $credentials = null, ?SaslPolicy $policy = null): Pop3Client
    {
        return new Pop3Client($this->config($policy), $credentials, null, $socket);
    }

    public function testGetCapabilityParsesCapaResponse(): void
    {
        $socket = new InMemoryPop3Socket([
            '+OK POP3 ready',
            '+OK Capability list follows',
            'SASL PLAIN CRAM-MD5',
            'UIDL',
            'TOP',
            '.',
        ]);
        $client = $this->client($socket);

        $capability = $client->getCapability();

        self::assertTrue($capability->query('UIDL'));
        self::assertTrue($capability->query('TOP'));
        self::assertSame(['PLAIN', 'CRAM-MD5'], $capability->getParams('SASL'));
        self::assertSame(['CAPA'], array_slice($socket->written, 0, 1));
    }

    public function testGetCapabilityFallsBackWhenCapaUnsupported(): void
    {
        $socket = new InMemoryPop3Socket([
            '+OK POP3 ready',
            '-ERR unknown command',
        ]);
        $client = $this->client($socket);

        $capability = $client->getCapability();

        self::assertTrue($capability->query('USER'));
        self::assertFalse($capability->query('UIDL'));
    }

    public function testGetCapabilityIsCachedAfterFirstCall(): void
    {
        $socket = new InMemoryPop3Socket([
            '+OK POP3 ready',
            '+OK',
            'UIDL',
            '.',
        ]);
        $client = $this->client($socket);

        $client->getCapability();
        $client->getCapability();

        self::assertSame(1, count(array_filter($socket->written, static fn (string $line): bool => $line === 'CAPA')));
    }

    public function testLoginViaSaslPlain(): void
    {
        $socket = new InMemoryPop3Socket([
            '+OK POP3 ready',
            '+OK',
            'SASL PLAIN',
            '.',
            '+OK Logged in',
        ]);
        $client = $this->client($socket, $this->credentials('alice', 'hunter2'));

        $client->login();

        self::assertSame([
            'CAPA',
            'AUTH PLAIN ' . base64_encode("\0alice\0hunter2"),
        ], $socket->written);
    }

    public function testLoginFallsBackToUserPassWhenNoSaslOffered(): void
    {
        $socket = new InMemoryPop3Socket([
            '+OK POP3 ready',
            '+OK',
            'UIDL',
            '.',
            '+OK',
            '+OK Logged in',
        ]);
        $client = $this->client($socket, $this->credentials('alice', 'hunter2'));

        $client->login();

        self::assertSame([
            'CAPA',
            'USER alice',
            'PASS hunter2',
        ], $socket->written);
    }

    public function testLoginUsesApopWhenTimestampPresentAndNoSaslOffered(): void
    {
        $socket = new InMemoryPop3Socket([
            '+OK POP3 ready <1234.abc@pop3.example.test>',
            '+OK',
            'UIDL',
            '.',
            '+OK Logged in',
        ]);
        $client = $this->client($socket, $this->credentials('alice', 'hunter2'));

        $client->login();

        $expectedDigest = hash('md5', '<1234.abc@pop3.example.test>hunter2');

        self::assertSame([
            'CAPA',
            'APOP alice ' . $expectedDigest,
        ], $socket->written);
    }

    public function testLoginFallsBackToUserPassWhenApopRejected(): void
    {
        $socket = new InMemoryPop3Socket([
            '+OK POP3 ready <1234.abc@pop3.example.test>',
            '+OK',
            'UIDL',
            '.',
            '-ERR APOP not supported',
            '+OK',
            '+OK Logged in',
        ]);
        $client = $this->client($socket, $this->credentials('alice', 'hunter2'));

        $client->login();

        self::assertSame([
            'CAPA',
            'APOP alice ' . hash('md5', '<1234.abc@pop3.example.test>hunter2'),
            'USER alice',
            'PASS hunter2',
        ], $socket->written);
    }

    public function testLoginThrowsWithoutCredentials(): void
    {
        $socket = new InMemoryPop3Socket(['+OK POP3 ready']);
        $client = $this->client($socket, null);

        $this->expectException(AuthenticationException::class);

        $client->login();
    }

    public function testLoginThrowsOnEmptyPassword(): void
    {
        $socket = new InMemoryPop3Socket([
            '+OK POP3 ready',
            '+OK',
            'UIDL',
            '.',
        ]);
        $client = $this->client($socket, $this->credentials('alice', ''));

        $this->expectException(AuthenticationException::class);

        $client->login();
    }

    public function testLoginIsIdempotent(): void
    {
        $socket = new InMemoryPop3Socket([
            '+OK POP3 ready',
            '+OK',
            'SASL PLAIN',
            '.',
            '+OK Logged in',
        ]);
        $client = $this->client($socket, $this->credentials());

        $client->login();
        $client->login();

        self::assertSame(2, count($socket->written));
    }

    public function testLogoutSendsQuitAndClosesConnection(): void
    {
        $socket = new InMemoryPop3Socket(['+OK POP3 ready', '+OK', '+OK Bye']);
        $client = $this->client($socket);

        $client->noop();
        $client->logout();

        self::assertContains('QUIT', $socket->written);
    }

    public function testLogoutWithoutPriorConnectionIsANoop(): void
    {
        $socket = new InMemoryPop3Socket();
        $client = $this->client($socket);

        $client->logout();

        self::assertSame([], $socket->written);
    }

    public function testNoopSendsNoopCommand(): void
    {
        $socket = new InMemoryPop3Socket(['+OK POP3 ready', '+OK']);
        $client = $this->client($socket);

        $client->noop();

        self::assertSame(['NOOP'], $socket->written);
    }

    public function testStatusRejectsMailboxesOtherThanInbox(): void
    {
        $socket = new InMemoryPop3Socket(['+OK POP3 ready']);
        $client = $this->client($socket);

        $this->expectException(Pop3ProtocolException::class);

        $client->status('Sent', StatusFlag::Messages->value);
    }

    public function testStatusReturnsMessagesAndRecent(): void
    {
        $socket = new InMemoryPop3Socket(['+OK POP3 ready', '+OK 4 1200']);
        $client = $this->client($socket);

        $status = $client->status('INBOX', StatusFlag::Messages->value | StatusFlag::Recent->value);

        self::assertSame(4, $status->messages);
        self::assertSame(4, $status->recent);
        self::assertSame(['STAT'], $socket->written);
    }

    public function testStatusUidNextFallsBackToStatWhenNoUidl(): void
    {
        $socket = new InMemoryPop3Socket([
            '+OK POP3 ready',
            '-ERR no capa',
            '+OK 4 1200',
        ]);
        $client = $this->client($socket);

        $status = $client->status('INBOX', StatusFlag::UidNext->value);

        self::assertSame(5, $status->uidnext);
    }

    public function testStatusUidNextUsesUidlHashWhenSupported(): void
    {
        $socket = new InMemoryPop3Socket([
            '+OK POP3 ready',
            '+OK',
            'UIDL',
            '.',
            '+OK',
            '1 abc',
            '2 def',
            '.',
        ]);
        $client = $this->client($socket);

        $status = $client->status('INBOX', StatusFlag::UidNext->value);

        self::assertIsString($status->uidnext);
        self::assertNotSame('', $status->uidnext);
    }

    public function testGetIdsObWithNullReturnsEmptySet(): void
    {
        $client = $this->client(new InMemoryPop3Socket());

        $ids = $client->getIdsOb();

        self::assertTrue($ids->isEmpty());
    }

    public function testGetIdsObWithSequenceString(): void
    {
        $client = $this->client(new InMemoryPop3Socket());

        $ids = $client->getIdsOb('uidl-a uidl-b');

        self::assertSame(['uidl-a', 'uidl-b'], $ids->toArray());
    }

    public function testGetIdsObWithArray(): void
    {
        $client = $this->client(new InMemoryPop3Socket());

        $ids = $client->getIdsOb(['uidl-a', 'uidl-b'], false);

        self::assertInstanceOf(Pop3IdSet::class, $ids);
        self::assertSame(['uidl-a', 'uidl-b'], $ids->toArray());
    }

    public function testGetIdsObWithExistingMessageIdSet(): void
    {
        $client = $this->client(new InMemoryPop3Socket());
        $existing = new Pop3IdSet(['uidl-a'], true);

        $ids = $client->getIdsOb($existing);

        self::assertSame(['uidl-a'], $ids->toArray());
    }

    public function testFetchRejectsNonPop3FetchQuery(): void
    {
        $client = $this->client(new InMemoryPop3Socket());

        $this->expectException(Pop3ProtocolException::class);

        iterator_to_array($client->fetch('INBOX', new Pop3IdSet([1], true), new \stdClass()));
    }

    public function testFetchRejectsMailboxesOtherThanInbox(): void
    {
        $client = $this->client(new InMemoryPop3Socket());

        $this->expectException(Pop3ProtocolException::class);

        iterator_to_array($client->fetch('Sent', new Pop3IdSet([1], true), new Pop3FetchQuery()));
    }

    public function testFetchFullMessageBySequence(): void
    {
        $socket = new InMemoryPop3Socket([
            '+OK POP3 ready',
            '+OK message follows',
            'Subject: test',
            '',
            'Body text',
            '.',
        ]);
        $client = $this->client($socket);

        $results = iterator_to_array(
            $client->fetch('INBOX', new Pop3IdSet([1], true), (new Pop3FetchQuery())->fullMsg())
        );

        self::assertSame(["RETR 1"], $socket->written);
        self::assertArrayHasKey(1, $results);
        self::assertSame("Subject: test\r\n\r\nBody text", (string) $results[1]->getFullMsg());
        self::assertSame(1, $results[1]->getSeq());
        self::assertSame('1', $results[1]->getUid());
    }

    public function testFetchWithEmptyIdsFetchesEveryMessage(): void
    {
        $socket = new InMemoryPop3Socket([
            '+OK POP3 ready',
            '+OK 2 500',
            '+OK',
            'Subject: 1',
            '',
            'a',
            '.',
            '+OK',
            'Subject: 2',
            '',
            'b',
            '.',
        ]);
        $client = $this->client($socket);

        $results = iterator_to_array(
            $client->fetch('INBOX', new Pop3IdSet([], true), (new Pop3FetchQuery())->bodyText())
        );

        self::assertSame(['STAT', 'RETR 1', 'RETR 2'], $socket->written);
        self::assertCount(2, $results);
        self::assertSame('a', (string) $results[1]->getBodyText());
        self::assertSame('b', (string) $results[2]->getBodyText());
    }

    public function testFetchHeaderUsesTopWhenSupported(): void
    {
        $socket = new InMemoryPop3Socket([
            '+OK POP3 ready',
            '+OK Capability list follows',
            'TOP',
            '.',
            '+OK',
            'Subject: hi',
            '.',
        ]);
        $client = $this->client($socket);

        $results = iterator_to_array(
            $client->fetch('INBOX', new Pop3IdSet([1], true), (new Pop3FetchQuery())->headerText())
        );

        self::assertSame(['CAPA', 'TOP 1 0'], $socket->written);
        self::assertSame('Subject: hi', (string) $results[1]->getHeaderText());
    }

    public function testFetchHeaderFallsBackToRetrWhenTopUnsupported(): void
    {
        $socket = new InMemoryPop3Socket([
            '+OK POP3 ready',
            '-ERR unknown command',
            '+OK message follows',
            'Subject: hi',
            '',
            'Body',
            '.',
        ]);
        $client = $this->client($socket);

        $results = iterator_to_array(
            $client->fetch('INBOX', new Pop3IdSet([1], true), (new Pop3FetchQuery())->headerText())
        );

        self::assertSame(['CAPA', 'RETR 1'], $socket->written);
        self::assertSame('Subject: hi', (string) $results[1]->getHeaderText());
    }

    public function testFetchUidSizeAndSeqByUid(): void
    {
        $socket = new InMemoryPop3Socket([
            '+OK POP3 ready',
            '+OK',
            '1 uidl-a',
            '2 uidl-b',
            '.',
            '+OK',
            '1 100',
            '2 200',
            '.',
        ]);
        $client = $this->client($socket);

        $query = (new Pop3FetchQuery())->uid()->size()->seq();
        $results = iterator_to_array(
            $client->fetch('INBOX', new Pop3IdSet(['uidl-b']), $query)
        );

        self::assertSame(['UIDL', 'LIST'], $socket->written);
        self::assertArrayHasKey('uidl-b', $results);
        self::assertSame('uidl-b', $results['uidl-b']->getUid());
        self::assertSame(200, $results['uidl-b']->getSize());
        self::assertSame(2, $results['uidl-b']->getSeq());
    }

    public function testFetchImapDateParsesDateHeaderViaTop(): void
    {
        $socket = new InMemoryPop3Socket([
            '+OK POP3 ready',
            '+OK Capability list follows',
            'TOP',
            '.',
            '+OK',
            'Date: Mon, 02 Jan 2006 15:04:05 +0000',
            '.',
        ]);
        $client = $this->client($socket);

        $results = iterator_to_array(
            $client->fetch('INBOX', new Pop3IdSet([1], true), (new Pop3FetchQuery())->imapDate())
        );

        self::assertSame('2006-01-02', $results[1]->getImapDate()->format('Y-m-d'));
    }

    public function testStoreAddDeletedSendsDeleForEachId(): void
    {
        $socket = new InMemoryPop3Socket([
            '+OK POP3 ready',
            '+OK deleted',
            '+OK deleted',
        ]);
        $client = $this->client($socket);

        $result = $client->store('INBOX', [
            'ids' => new Pop3IdSet([1, 2], true),
            'add' => [SystemFlag::Deleted],
        ]);

        self::assertSame(['DELE 1', 'DELE 2'], $socket->written);
        self::assertSame([1, 2], $result->toArray());
    }

    public function testStoreRemoveDeletedSendsRset(): void
    {
        $socket = new InMemoryPop3Socket(['+OK POP3 ready', '+OK']);
        $client = $this->client($socket);

        $result = $client->store('INBOX', [
            'ids' => new Pop3IdSet([1], true),
            'remove' => [SystemFlag::Deleted],
        ]);

        self::assertSame(['RSET'], $socket->written);
        self::assertTrue($result->isEmpty());
    }

    public function testStoreReplaceWithoutDeletedSendsRset(): void
    {
        $socket = new InMemoryPop3Socket(['+OK POP3 ready', '+OK']);
        $client = $this->client($socket);

        $client->store('INBOX', [
            'ids' => new Pop3IdSet([1], true),
            'replace' => [SystemFlag::Seen],
        ]);

        self::assertSame(['RSET'], $socket->written);
    }

    public function testStoreWithNoDeletedFlagIsNoop(): void
    {
        $socket = new InMemoryPop3Socket(['+OK POP3 ready']);
        $client = $this->client($socket);

        $result = $client->store('INBOX', [
            'ids' => new Pop3IdSet([1], true),
            'add' => [SystemFlag::Seen],
        ]);

        self::assertSame([], $socket->written);
        self::assertTrue($result->isEmpty());
    }

    public function testStoreResolvesUidsToSequenceNumbersForDele(): void
    {
        $socket = new InMemoryPop3Socket([
            '+OK POP3 ready',
            '+OK',
            '1 uidl-a',
            '2 uidl-b',
            '.',
            '+OK deleted',
        ]);
        $client = $this->client($socket);

        $client->store('INBOX', [
            'ids' => new Pop3IdSet(['uidl-b']),
            'add' => [SystemFlag::Deleted],
        ]);

        self::assertSame(['UIDL', 'DELE 2'], $socket->written);
    }

    public function testExpungeCommitsViaLogoutAndReturnsDeletedIds(): void
    {
        $socket = new InMemoryPop3Socket([
            '+OK POP3 ready',
            '+OK deleted',
            '+OK bye',
        ]);
        $client = $this->client($socket);

        $client->store('INBOX', [
            'ids' => new Pop3IdSet([1], true),
            'add' => [SystemFlag::Deleted],
        ]);

        $expunged = $client->expunge('INBOX', ['list' => true]);

        self::assertSame(['DELE 1', 'QUIT'], $socket->written);
        self::assertSame([1], $expunged->toArray());
    }

    public function testExpungeWithoutListOptionReturnsEmptySet(): void
    {
        $socket = new InMemoryPop3Socket([
            '+OK POP3 ready',
            '+OK deleted',
            '+OK bye',
        ]);
        $client = $this->client($socket);

        $client->store('INBOX', [
            'ids' => new Pop3IdSet([1], true),
            'add' => [SystemFlag::Deleted],
        ]);

        $expunged = $client->expunge('INBOX', []);

        self::assertTrue($expunged->isEmpty());
    }
}
