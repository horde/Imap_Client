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
use Horde\Imap\Client\ImapNamespace;
use Horde\Imap\Client\ImapNamespaceList;
use Horde\Imap\Client\MailboxListMode;
use Horde\Imap\Client\NamespaceType;
use Horde\Imap\Client\OpenMode;
use Horde\Imap\Client\SpecialUse;
use Horde\Sasl\Negotiation\SaslPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Mailbox management (create/delete/rename/subscribe,
 * listMailboxes, getNamespaces) on {@see ImapClient}.
 */
#[CoversClass(ImapClient::class)]
#[CoversClass(ImapNamespace::class)]
#[CoversClass(ImapNamespaceList::class)]
class ImapClientMailboxManagementTest extends TestCase
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

    public function testCreateMailboxSendsCreate(): void
    {
        $socket = $this->socket(
            '* OK Server ready.',
            'A1 OK CREATE completed.',
        );
        $client = $this->client($socket);

        $client->createMailbox('Work/Reports');

        self::assertSame(['A1 CREATE Work/Reports'], $socket->written);
    }

    public function testCreateMailboxWithSpecialUse(): void
    {
        $socket = $this->socket(
            '* OK Server ready.',
            'A1 OK CREATE completed.',
        );
        $client = $this->client($socket);

        $client->createMailbox('Archive', [SpecialUse::Archive]);

        self::assertSame(['A1 CREATE Archive (USE (\\Archive))'], $socket->written);
    }

    public function testCreateMailboxWrapsRejection(): void
    {
        $socket = $this->socket(
            '* OK Server ready.',
            'A1 NO Mailbox already exists.',
        );
        $client = $this->client($socket);

        $this->expectException(ServerResponseException::class);

        $client->createMailbox('INBOX');
    }

    public function testDeleteMailboxSendsDelete(): void
    {
        $socket = $this->socket(
            '* OK Server ready.',
            'A1 OK DELETE completed.',
        );
        $client = $this->client($socket);

        $client->deleteMailbox('Old/Stuff');

        self::assertSame(['A1 DELETE Old/Stuff'], $socket->written);
    }

    public function testDeleteMailboxClosesSelectedMailboxFirst(): void
    {
        $socket = $this->socket(
            '* OK Server ready.',
            'A1 OK [READ-WRITE] SELECT completed.',
            'A2 OK CLOSE completed.',
            'A3 OK DELETE completed.',
        );
        $client = $this->client($socket);

        $client->openMailbox('Trash', OpenMode::ReadWrite);
        $client->deleteMailbox('Trash');

        self::assertSame(['A1 SELECT Trash', 'A2 CLOSE', 'A3 DELETE Trash'], $socket->written);
        self::assertNull($client->selectedMailbox());
    }

    public function testRenameMailboxSendsRename(): void
    {
        $socket = $this->socket(
            '* OK Server ready.',
            'A1 OK RENAME completed.',
        );
        $client = $this->client($socket);

        $client->renameMailbox('Old', 'New');

        self::assertSame(['A1 RENAME Old New'], $socket->written);
    }

    public function testRenameMailboxClosesSelectedSourceFirst(): void
    {
        $socket = $this->socket(
            '* OK Server ready.',
            'A1 OK [READ-WRITE] SELECT completed.',
            'A2 OK CLOSE completed.',
            'A3 OK RENAME completed.',
        );
        $client = $this->client($socket);

        $client->openMailbox('Drafts', OpenMode::ReadWrite);
        $client->renameMailbox('Drafts', 'Old Drafts');

        self::assertSame(
            ['A1 SELECT Drafts', 'A2 CLOSE', 'A3 RENAME Drafts "Old Drafts"'],
            $socket->written,
        );
    }

    public function testSubscribeMailboxSendsSubscribe(): void
    {
        $socket = $this->socket(
            '* OK Server ready.',
            'A1 OK SUBSCRIBE completed.',
        );
        $client = $this->client($socket);

        $client->subscribeMailbox('Lists/Horde');

        self::assertSame(['A1 SUBSCRIBE Lists/Horde'], $socket->written);
    }

    public function testSubscribeMailboxSendsUnsubscribe(): void
    {
        $socket = $this->socket(
            '* OK Server ready.',
            'A1 OK UNSUBSCRIBE completed.',
        );
        $client = $this->client($socket);

        $client->subscribeMailbox('Lists/Horde', false);

        self::assertSame(['A1 UNSUBSCRIBE Lists/Horde'], $socket->written);
    }

    public function testListMailboxesBaseListParsesEntries(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1] Server ready.',
            '* LIST (\\HasNoChildren) "/" "INBOX"',
            '* LIST (\\HasChildren) "/" "Work"',
            'A1 OK LIST completed.',
        );
        $client = $this->client($socket);

        $result = $client->listMailboxes('%', MailboxListMode::All);

        self::assertSame(['A1 LIST "" %'], $socket->written);
        self::assertArrayHasKey('INBOX', $result);
        self::assertArrayHasKey('Work', $result);
        self::assertSame('/', $result['INBOX']['delimiter']);
        self::assertSame('INBOX', $result['INBOX']['mailbox']);
        self::assertSame(['\\hasnochildren'], $result['INBOX']['attributes']);
    }

    public function testListMailboxesFlatReturnsNames(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1] Server ready.',
            '* LIST (\\HasNoChildren) "/" "INBOX"',
            '* LIST (\\HasChildren) "/" "Work"',
            'A1 OK LIST completed.',
        );
        $client = $this->client($socket);

        $result = $client->listMailboxes('%', MailboxListMode::All, ['flat' => true]);

        self::assertSame(['INBOX', 'Work'], $result);
    }

    public function testListMailboxesSubscribedUsesLsubWithoutListExtended(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1] Server ready.',
            '* LSUB () "/" "INBOX"',
            '* LSUB () "/" "Work"',
            'A1 OK LSUB completed.',
        );
        $client = $this->client($socket);

        $result = $client->listMailboxes('%', MailboxListMode::Subscribed);

        self::assertSame(['A1 LSUB "" %'], $socket->written);
        self::assertArrayHasKey('Work', $result);
    }

    public function testListMailboxesSubscribedUsesListExtendedWhenAvailable(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 LIST-EXTENDED] Server ready.',
            '* LIST (\\Subscribed) "/" "INBOX"',
            '* LIST (\\Subscribed) "/" "Work"',
            'A1 OK LIST completed.',
        );
        $client = $this->client($socket);

        $result = $client->listMailboxes('%', MailboxListMode::Subscribed);

        self::assertSame(['A1 LIST (SUBSCRIBED) "" % RETURN (SUBSCRIBED)'], $socket->written);
        self::assertArrayHasKey('Work', $result);
    }

    public function testListMailboxesUnsubscribedFiltersSubscribedEntries(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 LIST-EXTENDED] Server ready.',
            '* LIST (\\Subscribed) "/" "INBOX"',
            '* LIST () "/" "Junk"',
            'A1 OK LIST completed.',
        );
        $client = $this->client($socket);

        $result = $client->listMailboxes('%', MailboxListMode::Unsubscribed);

        self::assertSame(['A1 LIST "" % RETURN (SUBSCRIBED)'], $socket->written);
        self::assertArrayNotHasKey('INBOX', $result);
        self::assertArrayHasKey('Junk', $result);
    }

    public function testListMailboxesInfersAttributesUnderListExtended(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 LIST-EXTENDED] Server ready.',
            '* LIST (\\NoInferiors) "/" "Feeds"',
            'A1 OK LIST completed.',
        );
        $client = $this->client($socket);

        $result = $client->listMailboxes('%', MailboxListMode::All, ['attributes' => true]);

        self::assertContains('\\hasnochildren', $result['Feeds']['attributes']);
        self::assertContains('\\noinferiors', $result['Feeds']['attributes']);
    }

    public function testGetNamespacesParsesPersonalOtherAndShared(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 NAMESPACE] Server ready.',
            '* NAMESPACE (("" "/")) (("Other Users/" "/")) (("Public Folders/" "/"))',
            'A1 OK NAMESPACE completed.',
        );
        $client = $this->client($socket);

        $namespaces = $client->getNamespaces();

        self::assertSame(['A1 NAMESPACE'], $socket->written);
        self::assertCount(3, $namespaces);

        $personal = $namespaces->get('');
        self::assertNotNull($personal);
        self::assertSame(NamespaceType::Personal, $personal->type);
        self::assertSame('/', $personal->delimiter);

        $other = $namespaces->get('Other Users/');
        self::assertNotNull($other);
        self::assertSame(NamespaceType::Other, $other->type);

        $shared = $namespaces->get('Public Folders/');
        self::assertNotNull($shared);
        self::assertSame(NamespaceType::Shared, $shared->type);
    }

    public function testGetNamespacesHandlesNilGroups(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 NAMESPACE] Server ready.',
            '* NAMESPACE (("" "/")) NIL NIL',
            'A1 OK NAMESPACE completed.',
        );
        $client = $this->client($socket);

        $namespaces = $client->getNamespaces();

        self::assertCount(1, $namespaces);
        self::assertNotNull($namespaces->get(''));
    }

    public function testGetNamespacesReturnsEmptyListWithoutCapability(): void
    {
        $socket = $this->socket('* OK [CAPABILITY IMAP4rev1] Server ready.');
        $client = $this->client($socket);

        $namespaces = $client->getNamespaces();

        self::assertCount(0, $namespaces);
        self::assertSame([], $socket->written);
    }

    public function testNamespaceBaseStripsTrailingDelimiter(): void
    {
        $ns = new ImapNamespace('Other Users/', NamespaceType::Other, '/');

        self::assertSame('Other Users', $ns->base());
    }

    public function testNamespaceStripNamespaceRemovesPrefix(): void
    {
        $ns = new ImapNamespace('Other Users/', NamespaceType::Other, '/');

        self::assertSame('bob/INBOX', $ns->stripNamespace('Other Users/bob/INBOX'));
        self::assertSame('INBOX', $ns->stripNamespace('INBOX'));
    }

    public function testNamespaceListGetForMailboxMatchesPrefix(): void
    {
        $list = new ImapNamespaceList([
            new ImapNamespace('', NamespaceType::Personal, '/'),
            new ImapNamespace('Other Users/', NamespaceType::Other, '/'),
        ]);

        $match = $list->getForMailbox('Other Users/bob/INBOX');
        self::assertNotNull($match);
        self::assertSame('Other Users/', $match->name);

        $fallback = $list->getForMailbox('INBOX');
        self::assertNotNull($fallback);
        self::assertSame('', $fallback->name);
    }
}
