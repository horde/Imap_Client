<?php

declare(strict_types=1);

/**
 * Copyright 2011-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright  2011-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit\Data;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Horde_Imap_Client_Data_Acl;

/**
 * Tests for the Imap Client ACL data object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2011-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class AclTest extends TestCase
{
    public function testIterator()
    {
        $ob = new Horde_Imap_Client_Data_Acl('lrs');

        $this->assertNotEmpty(count(iterator_to_array($ob)));
    }

    public function testSerialization()
    {
        $this->assertInstanceOf(
            'Horde_Imap_Client_Data_Acl',
            unserialize(serialize(new Horde_Imap_Client_Data_Acl('lrs')))
        );
    }

    #[DataProvider('bug10079Provider')]
    public function testBug10079($rights, $expected)
    {
        $ob = new Horde_Imap_Client_Data_Acl($rights);

        $this->assertEquals(
            $expected,
            strval($ob)
        );
    }

    public static function bug10079Provider()
    {
        return [
            // RFC 2086 rights string
            ['lrswipcda', 'lrswipakxte'],
            // RFC 4314 rights string
            ['lrswipakte', 'lrswipakte'],
        ];
    }

}
