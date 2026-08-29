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

namespace Horde\Imap\Client\Test\Unit\Data\Format\Mailbox;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the Mailbox data format object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
abstract class TestBase extends TestCase
{
    protected static string $cname;

    #[DataProvider('stringRepresentationProvider')]
    public function testStringRepresentation($mbox, $str)
    {
        $m = new static::$cname($mbox);

        $this->assertEquals(
            $str,
            strval($m)
        );
    }

    abstract public static function stringRepresentationProvider();

    #[DataProvider('escapeProvider')]
    public function testEscape($mbox, $str)
    {
        $m = new static::$cname($mbox);

        $this->assertEquals(
            $str,
            $m->escape()
        );
    }

    abstract public static function escapeProvider();

    #[DataProvider('verifyProvider')]
    public function testVerify($mbox, $exception)
    {
        if ($exception) {
            $this->expectException('Exception');
        }

        $m = new static::$cname($mbox);

        $this->markTestSkipped('Horde\Imap\Client\Data\Format\Mailbox\MailboxUtf8Test::testVerify - No Exception should be thrown here. ');
    }

    abstract public static function verifyProvider();

    #[DataProvider('binaryProvider')]
    public function testBinary($mbox, $expected)
    {
        $m = new static::$cname($mbox);

        if ($expected) {
            $this->assertTrue($m->binary());
        } else {
            $this->assertFalse($m->binary());
        }
    }

    abstract public static function binaryProvider();

    #[DataProvider('literalProvider')]
    public function testLiteral($mbox, $expected)
    {
        $m = new static::$cname($mbox);

        if ($expected) {
            $this->assertTrue($m->literal());
        } else {
            $this->assertFalse($m->literal());
        }
    }

    abstract public static function literalProvider();

    #[DataProvider('quotedProvider')]
    public function testQuoted($mbox, $expected)
    {
        $m = new static::$cname($mbox);

        if ($expected) {
            $this->assertTrue($m->quoted());
        } else {
            $this->assertFalse($m->quoted());
        }
    }

    abstract public static function quotedProvider();

    #[DataProvider('escapeStreamProvider')]
    public function testEscapeStream($mbox, $expected)
    {
        $m = new static::$cname($mbox);

        $this->assertEquals(
            $expected,
            stream_get_contents($m->escapeStream(), -1, 0)
        );
    }

}
