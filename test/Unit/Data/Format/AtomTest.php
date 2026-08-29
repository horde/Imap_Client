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
use Horde_Imap_Client_Data_Format_Atom;

/**
 * Tests for the Atom data format object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2011-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class AtomTest extends TestBase
{
    protected static function getTestObs(): array
    {
        return [
            new Horde_Imap_Client_Data_Format_Atom('Foo'),
            /* Illegal atom character. */
            new Horde_Imap_Client_Data_Format_Atom('Foo('),
            /* This is an invalid atom, but valid (non-quoted) astring. */
            new Horde_Imap_Client_Data_Format_Atom('Foo]'),
            new Horde_Imap_Client_Data_Format_Atom(''),
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
            'Foo',
            'Foo(',
            'Foo]',
            '',
        ]);
    }

    #[DataProvider('escapeProvider')]
    public function testEscape($ob, $expected)
    {
        $this->assertEquals(
            $expected,
            $ob->escape()
        );
    }

    public static function escapeProvider()
    {
        return static::createProviderArray([
            'Foo',
            'Foo(',
            'Foo]',
            '""',
        ]);
    }

    #[DataProvider('verifyProvider')]
    public function testVerify($ob, $expected)
    {
        if ($expected) {
            $this->expectException('Horde_Imap_Client_Data_Format_Exception');
        }

        $ob->verify();

        $this->markTestSkipped('Horde\Imap\Client\Data\Format\AtomTest::testVerify - No Exception should be thrown here. ');
    }

    public static function verifyProvider()
    {
        return static::createProviderArray([
            false,
            true,
            true,
            false,
        ]);
    }

    #[DataProvider('stripNonAtomCharactersProvider')]
    public function testStripNonAtomCharacters($str, $expected)
    {
        $atom = new Horde_Imap_Client_Data_Format_Atom($str);
        $this->assertEquals(
            $expected,
            $atom->stripNonAtomCharacters()
        );
    }

    public static function stripNonAtomCharactersProvider()
    {
        return [
            ['ABC123abc', 'ABC123abc'],
            ['A[{À*"A', 'A[A'],
        ];
    }

}
