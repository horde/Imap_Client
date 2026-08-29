<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Stub;

use Horde_Imap_Client_Utf7imap;

/**
 * Stub for testing the IMAP UTF7-IMAP conversion library.
 * Needed to change protected static member variables.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class Utf7imap extends Horde_Imap_Client_Utf7imap
{
    public static function setMbstring($val)
    {
        self::$_mbstring = (bool) $val;
    }
}
