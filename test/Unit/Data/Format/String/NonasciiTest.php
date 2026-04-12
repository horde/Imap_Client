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

namespace Horde\Imap\Client\Test\Unit\Data\Format\String;

use Horde\Imap\Client\Test\Unit\Data\Format\StringTest;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Tests for the String/Nonascii data format object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2011-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class NonasciiTest extends StringTest
{
    protected static string $cname = 'Horde_Imap_Client_Data_Format_String_Nonascii';

    public static function nonasciiInputProvider()
    {
        return [
            [true],
        ];
    }

}
