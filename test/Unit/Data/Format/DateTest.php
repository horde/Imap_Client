<?php

declare(strict_types=1);

/**
 * Copyright 2011-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright  2011-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit\Data\Format;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Horde_Imap_Client_Data_Format_Date;
use Horde_Imap_Client_DateTime;

/**
 * Tests for the Date data format object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2011-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class DateTest extends TestBase
{
    protected static function getTestObs(): array
    {
        return [
            new Horde_Imap_Client_Data_Format_Date('January 1, 2010'),
            new Horde_Imap_Client_Data_Format_Date('@1262304000'),
        ];
    }

    #[DataProvider('obsProvider')]
    public function testConstructor($ob)
    {
        $this->assertEquals(
            new Horde_Imap_Client_DateTime('January 1, 2010'),
            $ob->getData()
        );
    }

    #[DataProvider('obsProvider')]
    public function testStringRepresentation($ob)
    {
        $this->assertEquals(
            '1-Jan-2010',
            strval($ob)
        );
    }

    #[DataProvider('obsProvider')]
    public function testEscape($ob)
    {
        $this->assertEquals(
            '1-Jan-2010',
            $ob->escape()
        );
    }

}
