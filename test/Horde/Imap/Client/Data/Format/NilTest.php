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
namespace Horde\Imap\Client\Data\Format;
use \Horde_Imap_Client_Data_Format_Nil;

/**
 * Tests for the Nil data format object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2011-2016 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */
class NilTest extends TestBase
{
    protected function getTestObs()
    {
        return array(
            new Horde_Imap_Client_Data_Format_Nil(),
            /* Argument is ignored. */
            new Horde_Imap_Client_Data_Format_Nil('Foo')
        );
    }

    /**
     * @dataProvider obsProvider
     */
    public function testStringRepresentation($ob)
    {
        $this->assertEquals(
            '',
            strval($ob)
        );
    }

    /**
     * @dataProvider obsProvider
     */
    public function testEscape($ob)
    {
        $this->assertEquals(
            'NIL',
            $ob->escape()
        );
    }

}
