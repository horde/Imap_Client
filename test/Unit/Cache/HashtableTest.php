<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright  2013-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit\Cache;

use PHPUnit\Framework\Attributes\CoversNothing;
use Horde_HashTable_Memory;
use Horde_Imap_Client_Cache_Backend_Hashtable;

/**
 * Tests for the Horde_HashTable cache driver.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2013-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class HashtableTest extends TestBase
{
    protected function _getBackend()
    {
        return new Horde_Imap_Client_Cache_Backend_Hashtable([
            'hashtable' => new Horde_HashTable_Memory(),
        ]);
    }
}
