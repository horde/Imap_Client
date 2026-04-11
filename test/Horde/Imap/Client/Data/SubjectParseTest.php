<?php

/**
 * Copyright 2011-2026 Horde LLC (http://www.horde.org/)
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

namespace Horde\Imap\Client\Data;

use PHPUnit\Framework\TestCase;
use Horde_Imap_Client_Data_BaseSubject;

/**
 * Tests for Subject parsing.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2011-2016 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 * @coversNothing
 */
class SubjectParseTest extends TestCase
{
    /**
     * @dataProvider subjectParseProvider
     */
    public function testSubjectParse($subject, $expected)
    {
        $this->assertEquals(
            $expected,
            strval(new Horde_Imap_Client_Data_BaseSubject($subject))
        );
    }

    public function subjectParseProvider()
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
