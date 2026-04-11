<?php

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
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

/**
 * Tests for the UTF-8 Mailbox data format object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2014-2016 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Imap_Client
 * @subpackage UnitTests
 * @coversNothing
 */
class MailboxUtf8Test extends TestBase
{
    protected $cname = 'Horde_Imap_Client_Data_Format_Mailbox_Utf8';

    public function stringRepresentationProvider()
    {
        return [
            ['Foo', 'Foo'],
            ['Foo(', 'Foo('],
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
            ['Foo%Bar', '"Foo%Bar"'],
            ['Foo*Bar', '"Foo*Bar"'],
            ['Envoyé', '"Envoyé"'],
        ];
    }

    public function verifyProvider()
    {
        return [
            ['Foo', false],
            ['Foo(', false],
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
            ['Foo%Bar', true],
            ['Foo*Bar', true],
            ['Envoyé', true],
        ];
    }

    public function escapeStreamProvider()
    {
        return [
            ['Foo', '"Foo"'],
            ['Foo(', '"Foo("'],
            ['Foo%Bar', '"Foo%Bar"'],
            ['Foo*Bar', '"Foo*Bar"'],
            ['Envoyé', '"Envoyé"'],
        ];
    }

    public function testBadInput()
    {
        $this->expectException('Horde_Imap_Client_Data_Format_Exception');

        new $this->cname("foo\1");
    }

}
