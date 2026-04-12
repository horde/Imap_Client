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

namespace Horde\Imap\Client\Test\Unit\Data\Format;

use Horde\Imap\Client\Test\Unit\Data\Format\String\TestBase;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Tests for the Nstring data format object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2011-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversNothing]
class NstringTest extends TestBase
{
    protected static string $cname = 'Horde_Imap_Client_Data_Format_Nstring';

    protected static function getTestObs(): array
    {
        return [
            new static::$cname('Foo'),
            new static::$cname('Foo('),
            /* This is an invalid atom, but valid nstring. */
            new static::$cname('Foo]'),
            new static::$cname(),
        ];
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

    public static function escapeProvider()
    {
        return static::createProviderArray([
            '"Foo"',
            '"Foo("',
            '"Foo]"',
            'NIL',
        ]);
    }

    public static function verifyProvider()
    {
        return static::createProviderArray([
            true,
            true,
            true,
            true,
        ]);
    }

    public static function binaryProvider()
    {
        return static::createProviderArray([
            false,
            false,
            false,
            false,
        ]);
    }

    public static function literalProvider()
    {
        return static::binaryProvider();
    }

    public static function quotedProvider()
    {
        return static::createProviderArray([
            true,
            true,
            true,
            false,
        ]);
    }

    public static function escapeStreamProvider()
    {
        return static::escapeProvider();
    }

    public static function nonasciiInputProvider()
    {
        return [
            [false],
        ];
    }

}
