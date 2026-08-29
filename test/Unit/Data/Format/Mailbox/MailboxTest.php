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

namespace Horde\Imap\Client\Test\Unit\Data\Format\Mailbox;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\TestDox;
use Horde_Imap_Client_Data_Format_Mailbox;

/**
 * Tests for the Mailbox data format object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2011-2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class MailboxTest extends TestBase
{
    protected static string $cname = 'Horde_Imap_Client_Data_Format_Mailbox';

    public static function stringRepresentationProvider()
    {
        return [
            ['Foo', 'Foo'],
            ['Foo(', 'Foo('],
            ['Foo%Bar', 'Foo%Bar'],
            ['Foo*Bar', 'Foo*Bar'],
            ['Envoyé', 'Envoyé'],
        ];
    }

    public static function escapeProvider()
    {
        return [
            ['Foo', 'Foo'],
            ['Foo(', '"Foo("'],
            ['Foo%Bar', '"Foo%Bar"'],
            ['Foo*Bar', '"Foo*Bar"'],
            ['Envoyé', 'Envoy&AOk-'],
        ];
    }

    public static function verifyProvider()
    {
        return [
            ['Foo', false],
            ['Foo(', false],
            ['Foo%Bar', false],
            ['Foo*Bar', false],
            ['Envoyé', false],
        ];
    }

    public static function binaryProvider()
    {
        return [
            ['Foo', false],
            ['Foo(', false],
            ['Foo%Bar', false],
            ['Foo*Bar', false],
            ['Envoyé', false],
        ];
    }

    public static function literalProvider()
    {
        return [
            ['Foo', false],
            ['Foo(', false],
            ['Foo%Bar', false],
            ['Foo*Bar', false],
            ['Envoyé', false],
        ];
    }

    public static function quotedProvider()
    {
        return [
            ['Foo', false],
            ['Foo(', true],
            ['Foo%Bar', true],
            ['Foo*Bar', true],
            ['Envoyé', false],
        ];
    }

    public static function escapeStreamProvider()
    {
        return [
            ['Foo', '"Foo"'],
            ['Foo(', '"Foo("'],
            ['Foo%Bar', '"Foo%Bar"'],
            ['Foo*Bar', '"Foo*Bar"'],
            ['Envoyé', '"Envoy&AOk-"'],
        ];
    }

    #[TestDox('Mailbox with null byte is handled correctly (behavior differs in PHP 8.2+)')]
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
