<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @copyright  2014-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Imap\Client\Test\Unit\Data\Format\String;

use Horde\Imap\Client\Test\Unit\Data\Format\TestBase as ExtTestBase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Base class for tests of the Horde_Imap_Client_Data_Format_String object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
abstract class TestBase extends ExtTestBase
{
    protected static string $cname;

    #[DataProvider('stringRepresentationProvider')]
    public function testStringRepresentation($ob, $expected)
    {
        $this->assertEquals(
            $expected,
            strval($ob)
        );
    }

    abstract public static function stringRepresentationProvider();

    #[DataProvider('escapeProvider')]
    public function testEscape($ob, $expected)
    {
        if (!$expected) {
            $this->expectException('Horde_Imap_Client_Data_Format_Exception');
        }

        $this->assertEquals(
            $expected,
            $ob->escape()
        );

    }

    abstract public static function escapeProvider();

    #[DataProvider('verifyProvider')]
    public function testVerify($ob, $result)
    {
        $ob->verify();
        if (!$result) {
            $this->expectException('Horde_Imap_Client_Data_Format_Exception');
        }

        $this->markTestSkipped('Horde\Imap\Client\Data\Format\StringTest::testVerify - No Exception should be thrown here. ');
    }

    abstract public static function verifyProvider();

    #[DataProvider('binaryProvider')]
    public function testBinary($ob, $expected)
    {
        if ($expected) {
            $this->assertTrue($ob->binary());
        } else {
            $this->assertFalse($ob->binary());
        }
    }

    abstract public static function binaryProvider();

    #[DataProvider('literalProvider')]
    public function testLiteral($ob, $expected)
    {
        if ($expected) {
            $this->assertTrue($ob->literal());
        } else {
            $this->assertFalse($ob->literal());
        }
    }

    abstract public static function literalProvider();

    #[DataProvider('quotedProvider')]
    public function testQuoted($ob, $expected)
    {
        if ($expected) {
            $this->assertTrue($ob->quoted());
        } else {
            $this->assertFalse($ob->quoted());
        }
    }

    abstract public static function quotedProvider();

    #[DataProvider('escapeStreamProvider')]
    public function testEscapeStream($ob, $expected)
    {
        if (!$expected) {
            $this->expectException('Horde_Imap_Client_Data_Format_Exception');
        }

        $this->assertEquals(
            $expected,
            stream_get_contents($ob->escapeStream(), -1, 0)
        );

    }

    abstract public static function escapeStreamProvider();

    #[DataProvider('nonasciiInputProvider')]
    public function testNonasciiInput($result)
    {
        if (!$result) {
            $this->expectException('Horde_Imap_Client_Data_Format_Exception');
        }

        new static::$cname('Envoyé');

        $this->markTestSkipped('Horde\Imap\Client\Data\Format\String\NonasciiTest::testNonasciiInput - No Exception should be thrown here. ');
    }

    abstract public static function nonasciiInputProvider();

}
