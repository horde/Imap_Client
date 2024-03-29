<?php
/**
 * Copyright 2011-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category   Horde
 * @copyright  2011-2016 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */
namespace Horde\Imap\Client\Data\Format;
use \Horde_Imap_Client_Data_Format_Atom;

/**
 * Tests for the Atom data format object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2011-2016 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 */
class AtomTest extends TestBase
{
    protected function getTestObs()
    {
        return array(
            new Horde_Imap_Client_Data_Format_Atom('Foo'),
            /* Illegal atom character. */
            new Horde_Imap_Client_Data_Format_Atom('Foo('),
            /* This is an invalid atom, but valid (non-quoted) astring. */
            new Horde_Imap_Client_Data_Format_Atom('Foo]'),
            new Horde_Imap_Client_Data_Format_Atom('')
        );
    }

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

    public function stringRepresentationProvider()
    {
        return $this->createProviderArray(array(
            'Foo',
            'Foo(',
            'Foo]',
            '',
        ));
    }

    /**
     * @dataProvider escapeProvider
     */
    public function testEscape($ob, $expected)
    {
        $this->assertEquals(
            $expected,
            $ob->escape()
        );
    }

    public function escapeProvider()
    {
        return $this->createProviderArray(array(
            'Foo',
            'Foo(',
            'Foo]',
            '""',
        ));
    }

    /**
     * @dataProvider verifyProvider
     */
    public function testVerify($ob, $expected)
    {
        if ($expected) {
            $this->expectException('Horde_Imap_Client_Data_Format_Exception');
        }    

        $ob->verify();
     
        $this->markTestSkipped('Horde\Imap\Client\Data\Format\AtomTest::testVerify - No Exception should be thrown here. ');
    }

    public function verifyProvider()
    {
        return $this->createProviderArray(array(
            false,
            true,
            true,
            false
        ));
    }

    /**
     * @dataProvider stripNonAtomCharactersProvider
     */
    public function testStripNonAtomCharacters($str, $expected)
    {
        $atom = new Horde_Imap_Client_Data_Format_Atom($str);
        $this->assertEquals(
            $expected,
            $atom->stripNonAtomCharacters()
        );
    }

    public function stripNonAtomCharactersProvider()
    {
        return array(
            array('ABC123abc', 'ABC123abc'),
            array('A[{À*"A', 'A[A')
        );
    }

}
