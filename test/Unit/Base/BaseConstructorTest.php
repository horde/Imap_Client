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

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Horde\Imap\Client\Test\Stub\Base;
use Horde_Imap_Client;
use Horde_Imap_Client_Cache_Backend;

/**
 * Tests for Horde_Imap_Client_Base constructor logic.
 *
 * @copyright  2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class BaseConstructorTest extends TestCase
{
    private function create(array $params = []): Base
    {
        return new Base(array_merge([
            'username' => 'user',
            'password' => 'pass',
        ], $params));
    }

    public function testRequiresUsername(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Base([]);
    }

    public function testDefaultHostspec(): void
    {
        $ob = $this->create();
        $this->assertEquals('localhost', $ob->getParam('hostspec'));
    }

    public function testDefaultTimeout(): void
    {
        $ob = $this->create();
        $this->assertEquals(30, $ob->getParam('timeout'));
    }

    public function testDefaultReadTimeout(): void
    {
        $ob = $this->create();
        $this->assertEquals(120, $ob->getParam('read_timeout'));
    }

    public function testDefaultSecure(): void
    {
        $ob = $this->create();
        $this->assertFalse($ob->getParam('secure'));
    }

    public function testPortDefaultsTo143WhenNoSsl(): void
    {
        $ob = $this->create(['secure' => false]);
        $this->assertEquals(143, $ob->getParam('port'));
    }

    public function testPortDefaultsTo993WithSsl(): void
    {
        $ob = $this->create(['secure' => 'ssl']);
        $this->assertEquals(993, $ob->getParam('port'));
    }

    public function testPortDefaultsTo993WithSslv2(): void
    {
        $ob = $this->create(['secure' => 'sslv2']);
        $this->assertEquals(993, $ob->getParam('port'));
    }

    public function testPortDefaultsTo993WithSslv3(): void
    {
        $ob = $this->create(['secure' => 'sslv3']);
        $this->assertEquals(993, $ob->getParam('port'));
    }

    public function testPortDefaultsTo143WithTls(): void
    {
        $ob = $this->create(['secure' => 'tls']);
        $this->assertEquals(143, $ob->getParam('port'));
    }

    public function testPortDefaultsTo143WithTrue(): void
    {
        $ob = $this->create(['secure' => true]);
        $this->assertEquals(143, $ob->getParam('port'));
    }

    public function testExplicitPortOverridesDefault(): void
    {
        $ob = $this->create(['port' => 999]);
        $this->assertEquals(999, $ob->getParam('port'));
    }

    public function testCacheFieldsEmptyWhenNoCacheSet(): void
    {
        $ob = $this->create();
        $cache = $ob->getParam('cache');
        $this->assertEmpty($cache['fields']);
    }

    public function testCacheFieldsDefaultWhenBackendProvided(): void
    {
        $backend = $this->createMock(Horde_Imap_Client_Cache_Backend::class);
        $ob = $this->create([
            'cache' => ['backend' => $backend],
        ]);

        $cache = $ob->getParam('cache');
        $this->assertNotEmpty($cache['fields']);
        $this->assertArrayHasKey(Horde_Imap_Client::FETCH_ENVELOPE, $cache['fields']);
    }

    public function testChangedFlagSetAfterConstruction(): void
    {
        $ob = $this->create();
        $this->assertTrue($ob->changed);
    }
}
