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

namespace Horde\Imap\Client\Data\Format\Mailbox;

/**
 * Tests for the ListMailbox data format object.
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
class ListMailboxTest extends TestBase
{
    protected $cname = 'Horde_Imap_Client_Data_Format_ListMailbox';

    public function stringRepresentationProvider()
    {
        return [
            ['Foo', 'Foo'],
            ['Foo(', 'Foo('],
            ['Foo]', 'Foo]'],
            ['Foo%Bar', 'Foo%Bar'],
            ['Foo*Bar', 'Foo*Bar'],
            ['Envoyé', 'Envoyé'],
        ];
    }

    public function escapeProvider()
    {
        return [
            ['Foo', 'Foo'],
            ['Foo(', '"Foo("'],
            ['Foo]', 'Foo]'],
            /* Don't escape '%'. */
            ['Foo%Bar', 'Foo%Bar'],
            /* Don't escape '*'. */
            ['Foo*Bar', 'Foo*Bar'],
            ['Envoyé', 'Envoy&AOk-'],
        ];
    }

    public function verifyProvider()
    {
        return [
            ['Foo', false],
            ['Foo(', false],
            ['Foo]', false],
            ['Foo%Bar', false],
            ['Foo*Bar', false],
            ['Envoyé', false],
        ];
    }

    public function binaryProvider()
    {
        return [
            ['Foo', false],
            ['Foo(', false],
            ['Foo]', false],
            ['Foo%Bar', false],
            ['Foo*Bar', false],
            ['Envoyé', false],
        ];
    }

    public function literalProvider()
    {
        return [
            ['Foo', false],
            ['Foo(', false],
            ['Foo]', false],
            ['Foo%Bar', false],
            ['Foo*Bar', false],
            ['Envoyé', false],
        ];
    }

    public function quotedProvider()
    {
        return [
            ['Foo', false],
            ['Foo(', true],
            ['Foo]', false],
            ['Foo%Bar', false],
            ['Foo*Bar', false],
            ['Envoyé', false],
        ];
    }

    public function escapeStreamProvider()
    {
        return [
            ['Foo', '"Foo"'],
            ['Foo(', '"Foo("'],
            ['Foo]', '"Foo]"'],
            ['Foo%Bar', '"Foo%Bar"'],
            ['Foo*Bar', '"Foo*Bar"'],
            ['Envoyé', '"Envoy&AOk-"'],
        ];
    }

}
