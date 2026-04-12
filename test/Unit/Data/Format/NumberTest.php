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

namespace Horde\Imap\Client\Test\Unit\Data\Format;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Horde_Imap_Client_Data_Format_Number;

/**
 * Tests for the Number data format object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2011-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class NumberTest extends TestBase
{
    protected static function getTestObs(): array
    {
        return [
            new Horde_Imap_Client_Data_Format_Number(1),
            new Horde_Imap_Client_Data_Format_Number('1'),
            /* Invalid number. */
            new Horde_Imap_Client_Data_Format_Number('Foo'),
        ];
    }

    #[DataProvider('stringRepresentationProvider')]
    public function testStringRepresentation($ob, $expected)
    {
        $this->assertEquals(
            $expected,
            strval($ob)
        );
    }

    public static function stringRepresentationProvider()
    {
        return static::createProviderArray([
            '1',
            '1',
            '0',
        ]);
    }

    #[DataProvider('stringRepresentationProvider')]
    public function testEscape($ob, $expected)
    {
        $this->assertEquals(
            $expected,
            $ob->escape()
        );
    }

    #[DataProvider('verifyProvider')]
    public function testVerify($ob, $expected)
    {
        if ($expected) {
            $this->expectException('Horde_Imap_Client_Data_Format_Exception');
        }

        $ob->verify();

        $this->markTestSkipped('Horde\Imap\Client\Data\Format\NumberTest::testVerify - No Exception should be thrown here. ');
    }

    public static function verifyProvider()
    {
        return static::createProviderArray([
            false,
            false,
            true,
        ]);
    }

}
