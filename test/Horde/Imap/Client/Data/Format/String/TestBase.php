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
namespace Horde\Imap\Client\Data\Format\String;
use Horde\Imap\Client\Data\Format\TestBase as ExtTestBase;

/**
 * Base class for tests of the Horde_Imap_Client_Data_Format_String object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2014-2016 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */
abstract class TestBase extends ExtTestBase
{
    protected $cname;

    /**
     * @dataProvider stringRepresentationProvider
     */
    public function testStringRepresentation($ob, $expected)
    {
        $this->assertEquals(
            $expected,
            strval($ob)
        );
    }

    abstract public function stringRepresentationProvider();

    /**
     * @dataProvider escapeProvider
     */
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

    abstract public function escapeProvider();

    /**
     * @dataProvider verifyProvider
     */
    public function testVerify($ob, $result)
    {
        $ob->verify();
        if (!$result) {
            $this->expectException('Horde_Imap_Client_Data_Format_Exception');
        }

        $this->markTestSkipped('Horde\Imap\Client\Data\Format\StringTest::testVerify - No Exception should be thrown here. ');
    }

    abstract public function verifyProvider();

    /**
     * @dataProvider binaryProvider
     */
    public function testBinary($ob, $expected)
    {
        if ($expected) {
            $this->assertTrue($ob->binary());
        } else {
            $this->assertFalse($ob->binary());
        }
    }

    abstract public function binaryProvider();

    /**
     * @dataProvider literalProvider
     */
    public function testLiteral($ob, $expected)
    {
        if ($expected) {
            $this->assertTrue($ob->literal());
        } else {
            $this->assertFalse($ob->literal());
        }
    }

    abstract public function literalProvider();

    /**
     * @dataProvider quotedProvider
     */
    public function testQuoted($ob, $expected)
    {
        if ($expected) {
            $this->assertTrue($ob->quoted());
        } else {
            $this->assertFalse($ob->quoted());
        }
    }

    abstract public function quotedProvider();

    /**
     * @dataProvider escapeStreamProvider
     */
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

    abstract public function escapeStreamProvider();

    /**
     * @dataProvider nonasciiInputProvider
     */
    public function testNonasciiInput($result)
    {
        if (!$result)
            $this->expectException('Horde_Imap_Client_Data_Format_Exception');

        new $this->cname('Envoyé');

        $this->markTestSkipped('Horde\Imap\Client\Data\Format\String\NonasciiTest::testNonasciiInput - No Exception should be thrown here. ');
    }

    abstract public function nonasciiInputProvider();

}
