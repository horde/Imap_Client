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

namespace Horde\Imap\Client\Test\Unit\Data;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Horde_Imap_Client_Data_BaseSubject;

/**
 * Tests for Subject parsing.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2011-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class SubjectParseTest extends TestCase
{
    #[DataProvider('subjectParseProvider')]
    public function testSubjectParse($subject, $expected)
    {
        $this->assertEquals(
            $expected,
            strval(new Horde_Imap_Client_Data_BaseSubject($subject))
        );
    }

    public static function subjectParseProvider()
    {
        // Format: Test string, Expected parse result
        return [
            ['Test', 'Test'],
            ['Re: Test', 'Test'],
            ['re: Test', 'Test'],
            ['Fwd: Test', 'Test'],
            ['fwd: Test', 'Test'],
            [' Fw: Test', 'Test'],
            ['fw:  Test', 'Test'],
            ['fwd [foo] :  Test', 'Test'],
            ['Fwd: Re: Test', 'Test'],
            ['Fwd: Re: Test (fwd)', 'Test'],
            ['  re    :   Test  (fwd)', 'Test'],
            ['  re :   [foo]Test(Fwd)', 'Test'],
            ["re \t: \tTest", 'Test'],
            ['Re:', ''],
            [' RE :  ', ''],
            ['Fwd:', ''],
            ['  FWD  :   ', ''],
            // This used to throw an undefined index error.
            ['fwd', 'fwd'],
            // Tabs
            ["Re: re:re: fwd:[fwd: \t  Test]  (fwd)  (fwd)(fwd) ", 'Test'],
        ];
    }

}
