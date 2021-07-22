<?php
/**
 * Copyright 2013-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category   Horde
 * @copyright  2013-2016 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */
namespace Horde\Imap\Client\Cache;
use \Horde_Test_Factory_Hashtable;
use \Horde_Imap_Client_Cache_Backend_Hashtable;

/**
 * Tests for the Horde_HashTable cache driver.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2013-2016 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */
class HashtableTest extends TestBase
{
    protected function _getBackend()
    {
        $factory_hashtable = new Horde_Test_Factory_Hashtable();

        if (!class_exists('Horde_Imap_Client_Cache_Backend_Hashtable')) {
            $this->markTestSkipped('HashTable not available.');
        } else {
            return new Horde_Imap_Client_Cache_Backend_Hashtable(array(
                'hashtable' => $factory_hashtable->create()
            ));
        }
    }

}
