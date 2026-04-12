<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright  2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit\Socket;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Horde_Imap_Client_Data_Fetch;
use Horde_Imap_Client_Fetch_Query;
use Horde_Imap_Client_Ids;
use Horde_Imap_Client_Socket;
use Horde_Imap_Client_Socket_Catenate;
use Horde_Imap_Client_Url_Imap;

/**
 * Tests for Horde_Imap_Client_Socket_Catenate.
 *
 * @copyright  2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class CatenateTest extends TestCase
{
    private function createCatenate(
        int $uid,
        ?string $section,
        string $mailbox = 'INBOX'
    ): array {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'test content');
        rewind($stream);

        $fetchData = $this->createMock(Horde_Imap_Client_Data_Fetch::class);
        $fetchData->method('getFullMsg')->willReturn($stream);
        $fetchData->method('getHeaders')->willReturn($stream);
        $fetchData->method('getBodyPart')->willReturn($stream);
        $fetchData->method('getHeaderText')->willReturn($stream);
        $fetchData->method('getBodyText')->willReturn($stream);
        $fetchData->method('getMimeHeader')->willReturn($stream);

        $fetchResult = [$uid => $fetchData];

        $socket = $this->createMock(Horde_Imap_Client_Socket::class);
        $socket->method('getIdsOb')
            ->with($uid)
            ->willReturn(new Horde_Imap_Client_Ids($uid));
        $socket->method('fetch')
            ->willReturn($fetchResult);

        $url = new Horde_Imap_Client_Url_Imap();
        $url->uid = $uid;
        $url->section = $section;
        $url->mailbox = $mailbox;

        $catenate = new Horde_Imap_Client_Socket_Catenate($socket);

        return [$catenate, $url, $socket, $fetchData];
    }

    public function testFetchFullBody(): void
    {
        [$catenate, $url, $socket, $fetchData] = $this->createCatenate(42, null);

        $fetchData->expects($this->once())
            ->method('getFullMsg')
            ->with(true);

        $result = $catenate->fetchFromUrl($url);
        $this->assertIsResource($result);
    }

    public function testFetchHeaderFields(): void
    {
        [$catenate, $url] = $this->createCatenate(1, 'HEADER.FIELDS (Subject From)');

        $result = $catenate->fetchFromUrl($url);
        $this->assertIsResource($result);
    }

    public function testFetchHeaderFieldsNot(): void
    {
        [$catenate, $url] = $this->createCatenate(1, 'HEADER.FIELDS.NOT (Subject)');

        $result = $catenate->fetchFromUrl($url);
        $this->assertIsResource($result);
    }

    public function testFetchBodyPart(): void
    {
        [$catenate, $url, , $fetchData] = $this->createCatenate(1, '1.2');

        $fetchData->expects($this->once())
            ->method('getBodyPart')
            ->with('1.2', true);

        $result = $catenate->fetchFromUrl($url);
        $this->assertIsResource($result);
    }

    public function testFetchHeader(): void
    {
        [$catenate, $url, , $fetchData] = $this->createCatenate(1, 'HEADER');

        $fetchData->expects($this->once())
            ->method('getHeaderText')
            ->with(0, Horde_Imap_Client_Data_Fetch::HEADER_STREAM);

        $result = $catenate->fetchFromUrl($url);
        $this->assertIsResource($result);
    }

    public function testFetchNestedHeader(): void
    {
        [$catenate, $url, , $fetchData] = $this->createCatenate(1, '2.HEADER');

        $fetchData->expects($this->once())
            ->method('getHeaderText')
            ->with('2', Horde_Imap_Client_Data_Fetch::HEADER_STREAM);

        $result = $catenate->fetchFromUrl($url);
        $this->assertIsResource($result);
    }

    public function testFetchText(): void
    {
        [$catenate, $url, , $fetchData] = $this->createCatenate(1, 'TEXT');

        $fetchData->expects($this->once())
            ->method('getBodyText')
            ->with(0, true);

        $result = $catenate->fetchFromUrl($url);
        $this->assertIsResource($result);
    }

    public function testFetchMime(): void
    {
        [$catenate, $url, , $fetchData] = $this->createCatenate(1, '2.MIME');

        $fetchData->expects($this->once())
            ->method('getMimeHeader')
            ->with('2', Horde_Imap_Client_Data_Fetch::HEADER_STREAM);

        $result = $catenate->fetchFromUrl($url);
        $this->assertIsResource($result);
    }

    public function testUnrecognizedSectionReturnsNull(): void
    {
        [$catenate, $url] = $this->createCatenate(1, 'UNKNOWN');

        $this->assertNull($catenate->fetchFromUrl($url));
    }
}
