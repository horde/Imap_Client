<?php
/**
 * Copyright 2014-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category   Horde
 * @copyright  2014-2016 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */

/**
 * Base class for live server testing.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2014-2016 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */
class Horde_Imap_Client_Live_Base extends Horde_Test_Case
{
    public static $live;

    public static function tearDownAfterClass(): void
    {
        self::$live = null;
    }

    public function onNotSuccessfulTest(Exception $e)
    {
        if ($e instanceof Horde_Imap_Client_Exception) {
            $e->setMessage($e->getMessage() . ' [' . self::$live->url . ']');
        }
        parent::onNotSuccessfulTest($e);
    }

}
