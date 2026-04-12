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
use Horde\Imap\Client\Test\Stub\Base;
use Horde_Imap_Client;
use Horde_Imap_Client_Cache;
use Horde_Imap_Client_Cache_Backend;

/**
 * Tests for Horde_Imap_Client_Base _initCache() logic.
 *
 * @copyright  2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class BaseInitCacheTest extends TestCase
{
    public function testReturnsFalseWhenNoCacheFields(): void
    {
        $ob = new Base([
            'username' => 'user',
            'password' => 'pass',
        ]);

        $this->assertFalse($ob->initCache());
    }

    public function testReturnsFalseWhenNoBackend(): void
    {
        $ob = new Base([
            'username' => 'user',
            'password' => 'pass',
            'cache' => ['fields' => [Horde_Imap_Client::FETCH_ENVELOPE]],
        ]);

        $this->assertFalse($ob->initCache());
    }

    public function testReturnsTrueWhenBackendProvided(): void
    {
        $backend = $this->createMock(Horde_Imap_Client_Cache_Backend::class);
        $ob = new Base([
            'username' => 'user',
            'password' => 'pass',
            'cache' => ['backend' => $backend],
        ]);

        $this->assertTrue($ob->initCache());
    }

    public function testCreatesCacheObject(): void
    {
        $backend = $this->createMock(Horde_Imap_Client_Cache_Backend::class);
        $ob = new Base([
            'username' => 'user',
            'password' => 'pass',
            'cache' => ['backend' => $backend],
        ]);

        $ob->initCache();

        $this->assertInstanceOf(
            Horde_Imap_Client_Cache::class,
            $ob->getCache()
        );
    }
}
