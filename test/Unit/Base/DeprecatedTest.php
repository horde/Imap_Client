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

namespace Horde\Imap\Client\Test\Unit\Base;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Horde_Imap_Client;
use Horde_Imap_Client_Base;
use Horde_Imap_Client_Base_Deprecated;

/**
 * Tests for Horde_Imap_Client_Base_Deprecated.
 *
 * @copyright  2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class DeprecatedTest extends TestCase
{
    public function testParseCacheIdWithModseq(): void
    {
        $result = Horde_Imap_Client_Base_Deprecated::parseCacheId('V12345|H67890');

        $this->assertEquals(12345, $result['uidvalidity']);
        $this->assertEquals(67890, $result['highestmodseq']);
        $this->assertArrayNotHasKey('messages', $result);
        $this->assertArrayNotHasKey('uidnext', $result);
    }

    public function testParseCacheIdWithoutModseq(): void
    {
        $result = Horde_Imap_Client_Base_Deprecated::parseCacheId('V12345|U100|M50');

        $this->assertEquals(12345, $result['uidvalidity']);
        $this->assertEquals(100, $result['uidnext']);
        $this->assertEquals(50, $result['messages']);
        $this->assertArrayNotHasKey('highestmodseq', $result);
    }

    public function testParseCacheIdIgnoresUnknownParts(): void
    {
        $result = Horde_Imap_Client_Base_Deprecated::parseCacheId('V1|X999|custom');

        $this->assertEquals(['uidvalidity' => 1], $result);
    }

    public function testGetCacheIdWithCondstore(): void
    {
        $base = $this->createMock(Horde_Imap_Client_Base::class);
        $base->method('status')
            ->willReturn([
                'uidvalidity' => 1,
                'highestmodseq' => 99,
                'messages' => 5,
                'uidnext' => 10,
            ]);

        $result = Horde_Imap_Client_Base_Deprecated::getCacheId(
            $base,
            'INBOX',
            true
        );

        $this->assertEquals('V1|H99', $result);
    }

    public function testGetCacheIdWithoutCondstore(): void
    {
        $base = $this->createMock(Horde_Imap_Client_Base::class);
        $base->method('status')
            ->willReturn([
                'uidvalidity' => 1,
                'highestmodseq' => 0,
                'messages' => 5,
                'uidnext' => 10,
            ]);

        $result = Horde_Imap_Client_Base_Deprecated::getCacheId(
            $base,
            'INBOX',
            false
        );

        $this->assertEquals('V1|U10|M5', $result);
    }

    public function testGetCacheIdWithAdditionalData(): void
    {
        $base = $this->createMock(Horde_Imap_Client_Base::class);
        $base->method('status')
            ->willReturn([
                'uidvalidity' => 1,
                'highestmodseq' => 99,
                'messages' => 5,
                'uidnext' => 10,
            ]);

        $result = Horde_Imap_Client_Base_Deprecated::getCacheId(
            $base,
            'INBOX',
            true,
            ['extra']
        );

        $this->assertEquals('V1|H99|extra', $result);
    }

    public function testRoundTrip(): void
    {
        $base = $this->createMock(Horde_Imap_Client_Base::class);
        $base->method('status')
            ->willReturn([
                'uidvalidity' => 42,
                'highestmodseq' => 0,
                'messages' => 15,
                'uidnext' => 200,
            ]);

        $id = Horde_Imap_Client_Base_Deprecated::getCacheId($base, 'INBOX', false);
        $parsed = Horde_Imap_Client_Base_Deprecated::parseCacheId($id);

        $this->assertEquals(42, $parsed['uidvalidity']);
        $this->assertEquals(200, $parsed['uidnext']);
        $this->assertEquals(15, $parsed['messages']);
    }
}
