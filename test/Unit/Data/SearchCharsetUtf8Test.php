<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright  2014-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit\Data;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Horde_Imap_Client_Data_SearchCharset_Utf8;

/**
 * Tests for the SearchCharset_Utf8 object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class SearchCharsetUtf8Test extends TestCase
{
    public function testQuery()
    {
        $s = new Horde_Imap_Client_Data_SearchCharset_Utf8();
        $s->setValid('ISO-8859-1', false);

        $this->assertTrue($s->query('UTF-8', true));
        $this->assertTrue($s->query('US-ASCII', true));
        $this->assertFalse($s->query('iso-8859-1', true));
    }

    public function testRemoval()
    {
        $s = new Horde_Imap_Client_Data_SearchCharset_Utf8();
        $s->setValid('UTF-8');

        $this->assertTrue($s->query('UTF-8', true));

        $s->setValid('utf-8', false);

        $this->assertTrue($s->query('UTF-8', true));
    }

    public function testCharsetsProperty()
    {
        $s = new Horde_Imap_Client_Data_SearchCharset_Utf8();
        $s->setValid('UTF-8');
        $s->setValid('UTF-8');

        $this->assertEquals(
            ['US-ASCII', 'UTF-8'],
            $s->charsets
        );
    }

    public function testObserver()
    {
        $s = new Horde_Imap_Client_Data_SearchCharset_Utf8();

        $mock = $this->getMockBuilder('SplObserver')
                        ->getMock();
        $mock->expects($this->never())
            ->method('update')
            ->with($this->equalTo($s));
        $s->attach($mock);

        $s->setValid('utf-8');
        /* This should be ignored. */
        $s->setValid('UTF-8');
    }

    public function testSerialize()
    {
        $s = new Horde_Imap_Client_Data_SearchCharset_Utf8();

        $s_copy = unserialize(serialize($s));

        $s_copy->query('UTF-8', true);

        $this->markTestIncomplete();
    }

}
