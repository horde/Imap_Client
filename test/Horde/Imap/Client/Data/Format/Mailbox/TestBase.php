<?php
/**
 * Copyright 2014-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category   Horde
 * @copyright  2014-2016 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */
namespace Horde\Imap\Client\Data\Format\Mailbox;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Mailbox data format object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2014-2016 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */
abstract class TestBase extends TestCase
{
    protected $cname;

    /**
     * @dataProvider stringRepresentationProvider
     */
    public function testStringRepresentation($mbox, $str)
    {
        $m = new $this->cname($mbox);

        $this->assertEquals(
            $str,
            strval($m)
        );
    }

    abstract public function stringRepresentationProvider();

    /**
     * @dataProvider escapeProvider
     */
    public function testEscape($mbox, $str)
    {
        $m = new $this->cname($mbox);

        $this->assertEquals(
            $str,
            $m->escape()
        );
    }

    abstract public function escapeProvider();

    /**
     * @dataProvider verifyProvider
     */
    public function testVerify($mbox, $exception)
    {
        if ($exception) {
            $this->expectException('Exception');
        }

        $m = new $this->cname($mbox);

        $this->markTestSkipped('Horde\Imap\Client\Data\Format\Mailbox\MailboxUtf8Test::testVerify - No Exception should be thrown here. ');
    }

    abstract public function verifyProvider();

    /**
     * @dataProvider binaryProvider
     */
    public function testBinary($mbox, $expected)
    {
        $m = new $this->cname($mbox);

        if ($expected) {
            $this->assertTrue($m->binary());
        } else {
            $this->assertFalse($m->binary());
        }
    }

    abstract public function binaryProvider();

    /**
     * @dataProvider literalProvider
     */
    public function testLiteral($mbox, $expected)
    {
        $m = new $this->cname($mbox);

        if ($expected) {
            $this->assertTrue($m->literal());
        } else {
            $this->assertFalse($m->literal());
        }
    }

    abstract public function literalProvider();

    /**
     * @dataProvider quotedProvider
     */
    public function testQuoted($mbox, $expected)
    {
        $m = new $this->cname($mbox);

        if ($expected) {
            $this->assertTrue($m->quoted());
        } else {
            $this->assertFalse($m->quoted());
        }
    }

    abstract public function quotedProvider();

    /**
     * @dataProvider escapeStreamProvider
     */
    public function testEscapeStream($mbox, $expected)
    {
        $m = new $this->cname($mbox);

        $this->assertEquals(
            $expected,
            stream_get_contents($m->escapeStream(), -1, 0)
        );
    }

}
