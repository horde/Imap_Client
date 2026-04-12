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
use Horde_Imap_Client_Base_Alerts;
use Horde_Imap_Client_Data_SearchCharset;
use Horde_Imap_Client_Url;

/**
 * Tests for Horde_Imap_Client_Base __get() property access.
 *
 * @copyright  2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class BasePropertyTest extends TestCase
{
    private Base $ob;

    public function setUp(): void
    {
        $this->ob = new Base([
            'username' => 'user',
            'password' => 'pass',
        ]);
    }

    public function testAlertsObProperty(): void
    {
        $this->assertInstanceOf(
            Horde_Imap_Client_Base_Alerts::class,
            $this->ob->alerts_ob
        );
    }

    public function testSearchCharsetCreatedOnDemand(): void
    {
        $this->assertInstanceOf(
            Horde_Imap_Client_Data_SearchCharset::class,
            $this->ob->search_charset
        );
    }

    public function testSearchCharsetReturnsSameInstance(): void
    {
        $first = $this->ob->search_charset;
        $second = $this->ob->search_charset;
        $this->assertSame($first, $second);
    }

    public function testUrlProperty(): void
    {
        $url = $this->ob->url;
        $this->assertEquals('localhost', $url->hostspec);
        $this->assertEquals(143, $url->port);
    }

    public function testUrlReflectsCustomParams(): void
    {
        $ob = new Base([
            'username' => 'user',
            'password' => 'pass',
            'hostspec' => 'mail.example.com',
            'port' => 993,
            'secure' => 'ssl',
        ]);

        $url = $ob->url;
        $this->assertEquals('mail.example.com', $url->hostspec);
        $this->assertEquals(993, $url->port);
    }
}
