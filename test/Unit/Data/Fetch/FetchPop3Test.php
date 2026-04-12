<?php

declare(strict_types=1);

/**
 * Copyright 2015-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright  2015-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit\Data\Fetch;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Tests for the Horde_Imap_Client_Data_Fetch_Pop3 object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2015-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class FetchPop3Test extends TestBase
{
    protected function _setUp()
    {
        $this->ob_class = 'Horde_Imap_Client_Data_Fetch_Pop3';
    }
}
