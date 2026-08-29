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

namespace Horde\Imap\Client\Test\Unit\Ids;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Horde_Imap_Client_Ids_Pop3;

/**
 * POP3 specific tests for the Ids object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class Pop3Test extends TestCase
{
    #[DataProvider('pop3SequenceStringGenerateProvider')]
    public function testPop3SequenceStringGenerate($in, $expected)
    {
        $this->assertEquals(
            $expected,
            strval(new Horde_Imap_Client_Ids_Pop3($in))
        );
    }

    public static function pop3SequenceStringGenerateProvider()
    {
        return [
            [['ABCDEFGHIJ', 'ABCDE'], 'ABCDEFGHIJ ABCDE'],
            ['ABCDEFGHIJ', 'ABCDEFGHIJ'],
        ];
    }

    #[DataProvider('pop3SequenceStringParseProvider')]
    public function testPop3SequenceStringParse($in, $expected)
    {
        $ids = new Horde_Imap_Client_Ids_Pop3($in);
        $this->assertEquals(
            $expected,
            $ids->ids
        );
    }

    public static function pop3SequenceStringParseProvider()
    {
        return [
            ['ABCDEFGHIJ ABCDE', ['ABCDEFGHIJ', 'ABCDE']],
            ['ABCDEFGHIJ ABC ABCDE', ['ABCDEFGHIJ', 'ABC', 'ABCDE']],
            ['ABCDEFGHIJ', ['ABCDEFGHIJ']],
            // This is not a range in POP3 IDs
            ['10:12', ['10:12']],
        ];
    }

    public function testPop3Sort()
    {
        $ids = new Horde_Imap_Client_Ids_Pop3([
            'ABC',
            'A',
            'AC',
            'AB',
        ]);

        $this->assertEquals(
            'ABC A AC AB',
            $ids->tostring_sort
        );
    }
}
