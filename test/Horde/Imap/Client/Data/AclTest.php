<?php
/**
 * Copyright 2011-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category   Horde
 * @copyright  2011-2016 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */
namespace Horde\Imap\Client\Data;
use PHPUnit\Framework\TestCase;
use \Horde_Imap_Client_Data_Acl;

/**
 * Tests for the Imap Client ACL data object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2011-2016 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */
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

    /**
     * @dataProvider bug10079Provider
     */
    public function testBug10079($rights, $expected)
    {
        $ob = new Horde_Imap_Client_Data_Acl($rights);

        $this->assertEquals(
            $expected,
            strval($ob)
        );
    }

    public function bug10079Provider()
    {
        return array(
            // RFC 2086 rights string
            array('lrswipcda', 'lrswipakxte'),
            // RFC 4314 rights string
            array('lrswipakte', 'lrswipakte')
        );
    }

}
