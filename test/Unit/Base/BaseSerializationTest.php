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

use Exception;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Horde\Imap\Client\Test\Stub\Base;
use Horde_Imap_Client_Base;

/**
 * Tests for Horde_Imap_Client_Base serialization.
 *
 * @copyright  2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class BaseSerializationTest extends TestCase
{
    private function create(array $params = []): Base
    {
        return new Base(array_merge([
            'username' => 'user',
            'password' => 'pass',
        ], $params));
    }

    public function testSerializeContainsVersionAndParams(): void
    {
        $ob = $this->create();
        $data = $ob->__serialize();

        $this->assertArrayHasKey('v', $data);
        $this->assertArrayHasKey('p', $data);
        $this->assertArrayHasKey('i', $data);
        $this->assertEquals(Horde_Imap_Client_Base::VERSION, $data['v']);
    }

    public function testUnserializeThrowsOnVersionMismatch(): void
    {
        $ob = $this->create();

        $this->expectException(Exception::class);
        $ob->__unserialize(['v' => 0, 'i' => [], 'p' => ['username' => 'u']]);
    }

    public function testRoundTripSerialization(): void
    {
        $ob = $this->create([
            'hostspec' => 'mail.example.com',
            'port' => 993,
        ]);

        $serialized = serialize($ob);
        $restored = unserialize($serialized);

        $this->assertEquals('mail.example.com', $restored->getParam('hostspec'));
        $this->assertEquals(993, $restored->getParam('port'));
        $this->assertEquals('user', $restored->getParam('username'));
    }
}
