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

namespace Horde\Imap\Client\Test\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Horde_Imap_Client_DateTime;

/**
 * Tests for the Imap Client DateTime object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2011-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class DateTimeTest extends TestCase
{
    public static function provider()
    {
        return [
            // Bug #5715
            ['12 Sep 2007 15:49:12 UT', 1189612152],
            // Bug #9847
            ['Fri, 06 Oct 2006 12:15:13 +0100 (GMT+01:00)', 1160133313],
            // Bug #13114; This should resolve to 4/13 8:04:48pm UTC of the
            // current year.
            ['Apr 13 20:4:48', gmmktime(20, 4, 48, 4, 13)],
            // Bad date input
            ['This is a bad date', 0],
            // Bug #14381
            ['Thu, 1 Aug 2013 20:22:47 0000', 1375388567],
        ];
    }

    #[DataProvider('provider')]
    public function testDateTimeParsing($date, $expected)
    {
        $ob = new Horde_Imap_Client_DateTime($date);

        $this->assertEquals(
            $expected,
            intval(strval($ob))
        );
    }

    public function testClone()
    {
        $ob = new Horde_Imap_Client_DateTime('12 Sep 2007 15:49:12 UTC');

        $ob2 = clone $ob;

        $ob2->modify('+1 minute');

        $this->assertEquals(
            1189612152,
            intval(strval($ob))
        );

        $this->assertEquals(
            1189612152 + 60,
            intval(strval($ob2))
        );
    }

    public function testSerialize()
    {
        $ob = new Horde_Imap_Client_DateTime('12 Sep 2007 15:49:12 UTC');

        $ob2 = unserialize(serialize($ob));

        $this->assertEquals(
            1189612152,
            intval(strval($ob2))
        );
    }

}
