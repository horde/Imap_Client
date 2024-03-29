<?php
/**
 * Copyright 2011-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category   Horde
 * @copyright  2014-2016 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */
namespace Horde\Imap\Client\Url;
use PHPUnit\Framework\TestCase;

/**
 * Tests for base URL parsing.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2014-2016 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */
class BaseTest extends TestCase
{
    /**
     * @dataProvider badUrlProvider
     */
    public function testBadUrl($classname)
    {
        $url = new $classname('NOT A VALID URL');

        $this->assertNull($url->hostspec);
    }

    public function badUrlProvider()
    {
        return array(
            array('Horde_Imap_Client_Url'),
            array('Horde_Imap_Client_Url_Imap'),
            array('Horde_Imap_Client_Url_Imap_Relative'),
            array('Horde_Imap_Client_Url_Pop3')
        );
    }

}
