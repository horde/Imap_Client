<?php

declare(strict_types=1);

/**
 * Copyright 2011-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright  2014-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit\Url;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for base URL parsing.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class BaseTest extends TestCase
{
    #[DataProvider('badUrlProvider')]
    public function testBadUrl($classname)
    {
        $url = new $classname('NOT A VALID URL');

        $this->assertNull($url->hostspec);
    }

    public static function badUrlProvider()
    {
        return [
            ['Horde_Imap_Client_Url'],
            ['Horde_Imap_Client_Url_Imap'],
            ['Horde_Imap_Client_Url_Imap_Relative'],
            ['Horde_Imap_Client_Url_Pop3'],
        ];
    }
}
