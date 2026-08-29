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

use Horde\Imap\Client\AclRight;
use Horde\Imap\Client\ConnectionConfig;
use Horde\Imap\Client\Exception\CapabilityNotSupportedException;
use Horde\Imap\Client\ImapAcl;
use Horde\Imap\Client\ImapAclRights;
use Horde\Imap\Client\ImapClient;
use Horde\Sasl\Negotiation\SaslPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * ACL, QUOTA and METADATA extensions.
 */
#[CoversClass(ImapClient::class)]
#[CoversClass(ImapAcl::class)]
#[CoversClass(ImapAclRights::class)]
class ImapClientExtensionsTest extends TestCase
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

    public function testAclNormalizesVirtualRights(): void
    {
        // 'c' expands to k,x and drops the virtual letter.
        $acl = new ImapAcl('lrswipc');

        self::assertTrue($acl->has(AclRight::CreateMbox));
        self::assertTrue($acl->has(AclRight::DeleteMbox));
        self::assertFalse($acl->has('c'));
        self::assertStringNotContainsString('c', (string) $acl);
    }

    public function testAclHasWithEnumAndString(): void
    {
        $acl = new ImapAcl('lrs');

        self::assertTrue($acl->has(AclRight::Lookup));
        self::assertTrue($acl->has('r'));
        self::assertFalse($acl->has(AclRight::Administer));
    }

    public function testAclGetStringRfc2086CollapsesRights(): void
    {
        $acl = new ImapAcl('lrsw');
        $acl2 = new ImapAcl('kxte');

        self::assertSame('lrsw', $acl->getString());
        // k+x collapse to c, t+e (with x already consumed) to d.
        self::assertStringContainsString('c', $acl2->getString(rfc2086: true));
    }

    public function testAclDiff(): void
    {
        $acl = new ImapAcl('lrs');
        $diff = $acl->diff('lrw');

        self::assertSame('w', $diff['added']);
        self::assertSame('s', $diff['removed']);
    }

    public function testGetAclParsesResponse(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 ACL] Server ready.',
            '* ACL INBOX fred rwipslxeta -anyone rwip',
            'A1 OK GETACL completed.',
        );
        $client = $this->client($socket);

        $acl = $client->getACL('INBOX');

        self::assertSame(['A1 GETACL INBOX'], $socket->written);
        self::assertArrayHasKey('fred', $acl);
        self::assertArrayHasKey('-anyone', $acl);
        self::assertTrue($acl['fred']->has(AclRight::Administer));
    }

    public function testSetAcl(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 ACL] Server ready.',
            'A1 OK SETACL completed.',
        );
        $client = $this->client($socket);

        $client->setACL('INBOX', 'fred', ['rights' => 'lrsw']);

        self::assertSame(['A1 SETACL INBOX fred lrsw'], $socket->written);
    }

    public function testDeleteAcl(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 ACL] Server ready.',
            'A1 OK DELETEACL completed.',
        );
        $client = $this->client($socket);

        $client->deleteACL('INBOX', 'fred');

        self::assertSame(['A1 DELETEACL INBOX fred'], $socket->written);
    }

    public function testListAclRights(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 ACL] Server ready.',
            '* LISTRIGHTS INBOX fred la r w i p',
            'A1 OK LISTRIGHTS completed.',
        );
        $client = $this->client($socket);

        $rights = $client->listACLRights('INBOX', 'fred');

        self::assertSame(['A1 LISTRIGHTS INBOX fred'], $socket->written);
        self::assertSame(['l', 'a'], $rights->required);
        self::assertSame(['r', 'w', 'i', 'p'], $rights->optional);
    }

    public function testGetMyAclRights(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 ACL] Server ready.',
            '* MYRIGHTS INBOX lrswipkxtea',
            'A1 OK MYRIGHTS completed.',
        );
        $client = $this->client($socket);

        $acl = $client->getMyACLRights('INBOX');

        self::assertSame(['A1 MYRIGHTS INBOX'], $socket->written);
        self::assertTrue($acl->has(AclRight::Administer));
    }

    public function testAclRequiresCapability(): void
    {
        $socket = $this->socket('* OK [CAPABILITY IMAP4rev1] Server ready.');
        $client = $this->client($socket);

        $this->expectException(CapabilityNotSupportedException::class);

        $client->getACL('INBOX');
    }

    public function testGetQuotaParsesResponse(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 QUOTA] Server ready.',
            '* QUOTA "" (STORAGE 10 512 MESSAGE 3 100)',
            'A1 OK GETQUOTA completed.',
        );
        $client = $this->client($socket);

        $quota = $client->getQuota('');

        self::assertSame(['A1 GETQUOTA ""'], $socket->written);
        self::assertSame(10, $quota['']['storage']['usage']);
        self::assertSame(512, $quota['']['storage']['limit']);
        self::assertSame(3, $quota['']['message']['usage']);
    }

    public function testSetQuota(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 QUOTA] Server ready.',
            'A1 OK SETQUOTA completed.',
        );
        $client = $this->client($socket);

        $client->setQuota('', ['storage' => 512]);

        self::assertSame(['A1 SETQUOTA "" (STORAGE 512)'], $socket->written);
    }

    public function testGetQuotaRoot(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 QUOTA] Server ready.',
            '* QUOTAROOT INBOX ""',
            '* QUOTA "" (STORAGE 10 512)',
            'A1 OK GETQUOTAROOT completed.',
        );
        $client = $this->client($socket);

        $quota = $client->getQuotaRoot('INBOX');

        self::assertSame(['A1 GETQUOTAROOT INBOX'], $socket->written);
        self::assertSame(512, $quota['']['storage']['limit']);
    }

    public function testGetMetadataWithOptions(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 METADATA] Server ready.',
            '* METADATA "INBOX" ("/shared/comment" "My comment")',
            'A1 OK GETMETADATA completed.',
        );
        $client = $this->client($socket);

        $meta = $client->getMetadata('INBOX', ['/shared/comment'], ['maxsize' => 1024]);

        self::assertSame(
            ['A1 GETMETADATA INBOX (MAXSIZE 1024) (/shared/comment)'],
            $socket->written,
        );
        self::assertSame('My comment', $meta['INBOX']['/shared/comment']);
    }

    public function testSetMetadata(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 METADATA] Server ready.',
            'A1 OK SETMETADATA completed.',
        );
        $client = $this->client($socket);

        $client->setMetadata('INBOX', ['/shared/comment' => 'hi', '/shared/old' => null]);

        self::assertSame(
            ['A1 SETMETADATA INBOX (/shared/comment hi /shared/old NIL)'],
            $socket->written,
        );
    }

    public function testMetadataServerScopeRequiresMetadataServerCapability(): void
    {
        $socket = $this->socket(
            '* OK [CAPABILITY IMAP4rev1 METADATA-SERVER] Server ready.',
            '* METADATA "" ("/shared/vendor" "acme")',
            'A1 OK GETMETADATA completed.',
        );
        $client = $this->client($socket);

        $meta = $client->getMetadata('', ['/shared/vendor']);

        self::assertSame('acme', $meta['']['/shared/vendor']);
    }

    public function testMetadataRequiresCapability(): void
    {
        $socket = $this->socket('* OK [CAPABILITY IMAP4rev1] Server ready.');
        $client = $this->client($socket);

        $this->expectException(CapabilityNotSupportedException::class);

        $client->getMetadata('INBOX', ['/shared/comment']);
    }
}
