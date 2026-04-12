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
use Horde_Imap_Client_Base_Password;

/**
 * Tests for Horde_Imap_Client_Base getParam/setParam.
 *
 * @copyright  2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class BaseParamsTest extends TestCase
{
    private Base $ob;

    public function setUp(): void
    {
        $this->ob = new Base([
            'username' => 'user',
            'password' => 'pass',
        ]);
    }

    public function testGetParamReturnsNullForMissing(): void
    {
        $this->assertNull($this->ob->getParam('nonexistent'));
    }

    public function testSetAndGetParam(): void
    {
        $this->ob->setParam('foo', 'bar');
        $this->assertEquals('bar', $this->ob->getParam('foo'));
    }

    public function testPasswordObjectSupport(): void
    {
        $pwObj = $this->createMock(Horde_Imap_Client_Base_Password::class);
        $pwObj->method('getPassword')->willReturn('secret123');

        $ob = new Base([
            'username' => 'user',
            'password' => $pwObj,
        ]);

        $this->assertEquals('secret123', $ob->getParam('password'));
    }
}
