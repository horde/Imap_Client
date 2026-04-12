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

namespace Horde\Imap\Client\Test\Unit\Data\Format\Mailbox;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Tests for the ListMailbox data format object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2011-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class ListMailboxTest extends TestBase
{
    protected static string $cname = 'Horde_Imap_Client_Data_Format_ListMailbox';

    public static function stringRepresentationProvider()
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

    public static function escapeProvider()
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

    public static function verifyProvider()
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

    public static function binaryProvider()
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

    public static function literalProvider()
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

    public static function quotedProvider()
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

    public static function escapeStreamProvider()
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
