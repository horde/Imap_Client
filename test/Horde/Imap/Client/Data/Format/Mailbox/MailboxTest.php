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

use Horde_Imap_Client_Data_Format_Mailbox;

/**
 * Tests for the Mailbox data format object.
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
class MailboxTest extends TestBase
{
    protected $cname = 'Horde_Imap_Client_Data_Format_Mailbox';

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
            ['Envoyé', 'Envoy&AOk-'],
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
            ['Envoyé', false],
        ];
    }

    public function escapeStreamProvider()
    {
        return [
            ['Foo', '"Foo"'],
            ['Foo(', '"Foo("'],
            ['Foo%Bar', '"Foo%Bar"'],
            ['Foo*Bar', '"Foo*Bar"'],
            ['Envoyé', '"Envoy&AOk-"'],
        ];
    }

    /**
     * @testdox Mailbox with null byte is handled correctly (behavior differs in PHP 8.2+)
     */
    public function testBadInput()
    {
        /* @todo: Change in Horde_Imap_Client 3.0 to detect Exception, instead
         * of blank mailbox name. */
        $ob = new Horde_Imap_Client_Data_Format_Mailbox("foo\0");

        /* binary() call creates the blank string representation. */
        $this->assertFalse($ob->binary());

        $result = $ob->escape();

        if (version_compare(PHP_VERSION, '8.2', '>=')) {
            // PHP 8.2+: mb_convert_encoding now encodes null bytes
            $this->assertEquals('foo&AAA-', $result);
        } else {
            // PHP < 8.2: null bytes truncate the string
            $this->assertEquals('', $result);
        }
    }

}
