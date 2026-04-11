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

namespace Horde\Imap\Client\Data\Format;

use Horde\Imap\Client\Data\Format\String\TestBase;

/**
 * Tests for the Astring data format object.
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
class AstringTest extends TestBase
{
    protected $cname = 'Horde_Imap_Client_Data_Format_Astring';

    protected function getTestObs()
    {
        return [
            new $this->cname('Foo'),
            new $this->cname('Foo('),
            /* This is an invalid atom, but valid (non-quoted) astring. */
            new $this->cname('Foo]'),
            new $this->cname(''),
        ];
    }

    public function stringRepresentationProvider()
    {
        return $this->createProviderArray([
            'Foo',
            'Foo(',
            'Foo]',
            '',
        ]);
    }

    public function escapeProvider()
    {
        return $this->createProviderArray([
            'Foo',
            '"Foo("',
            'Foo]',
            '""',
        ]);
    }

    public function verifyProvider()
    {
        return $this->createProviderArray([
            true,
            true,
            true,
            true,
        ]);
    }

    public function binaryProvider()
    {
        return $this->createProviderArray([
            false,
            false,
            false,
            false,
        ]);
    }

    public function literalProvider()
    {
        return $this->binaryProvider();
    }

    public function quotedProvider()
    {
        return $this->createProviderArray([
            false,
            true,
            false,
            true,
        ]);
    }

    public function escapeStreamProvider()
    {
        return $this->createProviderArray([
            '"Foo"',
            '"Foo("',
            '"Foo]"',
            '""',
        ]);
    }

    public function nonasciiInputProvider()
    {
        return [
            [false],
        ];
    }

}
